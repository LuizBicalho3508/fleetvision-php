<?php
// ARQUIVO: api_usuarios.php
session_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// 1. Segurança Básica
if (!isset($_SESSION['user_id'])) { 
    http_response_code(403); 
    exit(json_encode(['error' => 'Sessão expirada.'])); 
}

if (!file_exists('db.php')) {
    http_response_code(500);
    exit(json_encode(['error' => 'Erro crítico: db.php não encontrado.']));
}
require 'db.php';

// 2. Contexto do Usuário
$tenant_id  = $_SESSION['tenant_id'];
$user_id    = $_SESSION['user_id'];
$user_role  = $_SESSION['user_role'] ?? 'user';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // =========================================================================
    // 👥 LISTAR USUÁRIOS
    // =========================================================================
    case 'get_users':
        try {
            $sql = "SELECT u.id, u.name, u.email, u.role_id, u.customer_id, u.branch_id, u.active, u.tenant_id,
                           r.name as role_name, b.name as branch_name, c.name as customer_name, t.name as tenant_name 
                    FROM saas_users u 
                    LEFT JOIN saas_roles r ON u.role_id = r.id 
                    LEFT JOIN saas_branches b ON u.branch_id = b.id 
                    LEFT JOIN saas_customers c ON u.customer_id = c.id
                    LEFT JOIN saas_tenants t ON u.tenant_id = t.id";

            if ($user_role === 'superadmin') {
                $sql .= " ORDER BY t.name ASC, u.name ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
            } else {
                $sql .= " WHERE u.tenant_id = ? ORDER BY u.name ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$tenant_id]);
            }
            
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    // =========================================================================
    // 🛡️ LISTAR PERFIS (Necessário para o modal de usuários)
    // =========================================================================
    case 'get_roles':
        // Busca perfis para o dropdown.
        $target_tenant = $_GET['tenant_id'] ?? $tenant_id;
        
        // Segurança: Apenas Superadmin pode listar perfis de outros tenants
        if ($user_role !== 'superadmin' && $target_tenant != $tenant_id) {
            $target_tenant = $tenant_id;
        }

        try {
            // Busca ID do tenant admin para trazer perfis globais
            $stmtAdmin = $pdo->prepare("SELECT id FROM saas_tenants WHERE slug = 'admin' LIMIT 1");
            $stmtAdmin->execute();
            $adminTenantId = $stmtAdmin->fetchColumn() ?: 0;

            // Busca perfis do tenant alvo OU perfis globais (do admin)
            $sql = "SELECT id, name, tenant_id FROM saas_roles WHERE tenant_id = ? OR tenant_id = ? ORDER BY tenant_id ASC, name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$target_tenant, $adminTenantId]);
            
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Marca quais são globais para facilitar identificação no front
            foreach($roles as &$r) {
                if ($r['tenant_id'] != $target_tenant) {
                    $r['name'] .= ' (Global)';
                }
            }

            echo json_encode($roles);
        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    // =========================================================================
    // 💾 SALVAR USUÁRIO (CRIAR / EDITAR)
    // =========================================================================
    case 'save_user':
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $pass = $input['password'] ?? '';
        
        // Converte vazios para NULL (Evita erro de constraint no banco)
        $role = !empty($input['role_id']) ? $input['role_id'] : null;
        $cust = !empty($input['customer_id']) ? $input['customer_id'] : null;
        $branch = !empty($input['branch_id']) ? $input['branch_id'] : null;
        $active = isset($input['active']) ? (bool)$input['active'] : true;
        
        // Define o tenant alvo (Superadmin pode definir, outros usam o da sessão)
        $target_tenant_id = ($user_role === 'superadmin' && !empty($input['tenant_id'])) ? $input['tenant_id'] : $tenant_id;
        
        if (empty($name) || empty($email)) {
            http_response_code(400); 
            exit(json_encode(['error' => 'Nome e Email são obrigatórios.']));
        }

        try {
            // 1. Validação de Perfil (Global ou Local)
            if ($role) {
                $stmtAdmin = $pdo->prepare("SELECT id FROM saas_tenants WHERE slug = 'admin' LIMIT 1");
                $stmtAdmin->execute();
                $adminTenantId = $stmtAdmin->fetchColumn() ?: 0;

                $stmtCheckRole = $pdo->prepare("SELECT id FROM saas_roles WHERE id = ? AND (tenant_id = ? OR tenant_id = ?)");
                $stmtCheckRole->execute([$role, $target_tenant_id, $adminTenantId]);
                
                if (!$stmtCheckRole->fetch()) {
                    http_response_code(400);
                    exit(json_encode(['error' => "O perfil selecionado não é válido para esta empresa."]));
                }
            }

            // 2. Verifica Duplicidade de Email
            $sqlEmail = "SELECT id FROM saas_users WHERE email = ? AND tenant_id = ?";
            $paramsEmail = [$email, $target_tenant_id];
            if ($id) { 
                $sqlEmail .= " AND id != ?";
                $paramsEmail[] = $id;
            }
            $stmtEmail = $pdo->prepare($sqlEmail);
            $stmtEmail->execute($paramsEmail);
            if ($stmtEmail->fetch()) {
                http_response_code(400);
                exit(json_encode(['error' => "Este email já está cadastrado nesta empresa."]));
            }

            if ($id) {
                // UPDATE
                $sql = "UPDATE saas_users SET name=?, email=?, role_id=?, customer_id=?, branch_id=?, active=?, tenant_id=? WHERE id=?";
                $params = [$name, $email, $role, $cust, $branch, $active ? 'true' : 'false', $target_tenant_id, $id];
                
                if ($user_role !== 'superadmin') {
                    $sql .= " AND tenant_id=?";
                    $params[] = $tenant_id;
                }

                if (!empty($pass)) { 
                    $sql = str_replace('active=?,', 'active=?, password=?,', $sql); 
                    // Insere senha na posição 6 (após active)
                    array_splice($params, 6, 0, password_hash($pass, PASSWORD_DEFAULT));
                }
                
                $pdo->prepare($sql)->execute($params);
            } else {
                // INSERT
                if(empty($pass)) {
                    http_response_code(400);
                    exit(json_encode(['error' => 'Senha é obrigatória para novos usuários.']));
                }

                $stmt = $pdo->prepare("INSERT INTO saas_users (tenant_id, name, email, password, role_id, customer_id, branch_id, status, active) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                $stmt->execute([$target_tenant_id, $name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $cust, $branch, $active ? 'true' : 'false']);
            }
            echo json_encode(['success' => true]);

        } catch (PDOException $e) { 
            http_response_code(500);
            echo json_encode(['error' => 'Erro SQL: ' . $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
        }
        break;

    // =========================================================================
    // 🗑️ EXCLUIR USUÁRIO
    // =========================================================================
    case 'delete_user':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if (!$id || $id == $user_id) { 
            http_response_code(400); 
            exit(json_encode(['error' => 'Operação inválida ou tentativa de excluir a si mesmo.'])); 
        }
        
        try {
            $sql = "DELETE FROM saas_users WHERE id = ?";
            $params = [$id];
            
            if ($user_role !== 'superadmin') {
                $sql .= " AND tenant_id = ?";
                $params[] = $tenant_id;
            }
            
            $pdo->prepare($sql)->execute($params);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action inválida em api_usuarios.php']);
        break;
}
?>