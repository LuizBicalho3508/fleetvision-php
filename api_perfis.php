<?php
// ARQUIVO: api_perfis.php
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

$tenant_id = $_SESSION['tenant_id'];
$action = $_REQUEST['action'] ?? '';

// 2. Roteador de Ações
switch ($action) {

    case 'get_profiles':
        try {
            $stmt = $pdo->prepare("SELECT * FROM saas_roles WHERE tenant_id = ? ORDER BY id DESC");
            $stmt->execute([$tenant_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    case 'save_profile':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $name = $input['name'] ?? '';
        // Garante que permissions seja sempre um JSON válido
        $permissions = is_array($input['permissions'] ?? []) ? json_encode($input['permissions']) : ($input['permissions'] ?? '[]');

        if (empty($name)) { 
            http_response_code(400); 
            exit(json_encode(['error' => 'Nome do perfil é obrigatório'])); 
        }

        try {
            if ($id) {
                // Atualizar
                $stmt = $pdo->prepare("UPDATE saas_roles SET name = ?, permissions = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$name, $permissions, $id, $tenant_id]);
            } else {
                // Criar Novo
                $stmt = $pdo->prepare("INSERT INTO saas_roles (tenant_id, name, permissions) VALUES (?, ?, ?)");
                $stmt->execute([$tenant_id, $name, $permissions]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    case 'delete_profile':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if (!$id) { 
            http_response_code(400); 
            exit(json_encode(['error' => 'ID inválido'])); 
        }

        try {
            // Verifica se há usuários usando este perfil antes de excluir
            $check = $pdo->prepare("SELECT COUNT(*) FROM saas_users WHERE role_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                http_response_code(400);
                exit(json_encode(['error' => 'Não é possível excluir: existem usuários vinculados a este perfil.']));
            }

            $stmt = $pdo->prepare("DELETE FROM saas_roles WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action inválida ou não encontrada em api_perfis.php']);
        break;
}
?>