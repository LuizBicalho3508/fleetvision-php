<?php
// ARQUIVO: api_login.php
// Responsável pela autenticação via API (Mobile/Externo) com Bloqueio Financeiro

session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require 'db.php';

try {
    // 1. Recebe Dados
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        throw new Exception("Email e senha são obrigatórios.", 400);
    }

    // 2. Busca Usuário e Dados do Tenant
    $stmt = $pdo->prepare("
        SELECT u.*, t.slug as tenant_slug 
        FROM saas_users u
        JOIN saas_tenants t ON u.tenant_id = t.id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Credenciais inválidas.", 401);
    }

    // 3. Verifica Senha
    if (!password_verify($password, $user['password'])) {
        throw new Exception("Credenciais inválidas.", 401);
    }

    // 4. Verifica se o Usuário está Ativo (Bloqueio Manual de Usuário)
    // Nota: O campo pode vir como 1/0 ou 't'/'f' dependendo do banco (Postgres/MySQL)
    $isActive = $user['active'] == 1 || $user['active'] === true || $user['active'] === 't';
    if (!$isActive) {
        throw new Exception("Usuário inativo.", 403);
    }

    // =================================================================
    // 🛑 PORTEIRO FINANCEIRO (BLOQUEIO DE INADIMPLÊNCIA)
    // =================================================================
    
    // Regra: Se não for SuperAdmin E tiver um cliente vinculado...
    if ($user['role_id'] != 1 && !empty($user['customer_id'])) { // Assumindo role_id 1 = SuperAdmin
        
        // Busca o status financeiro do Cliente Pai
        $stmtFin = $pdo->prepare("SELECT financial_status FROM saas_customers WHERE id = ?");
        $stmtFin->execute([$user['customer_id']]);
        $finStatus = $stmtFin->fetchColumn();

        if ($finStatus === 'overdue') {
            throw new Exception("ACESSO SUSPENSO. Pendência financeira detectada. Contate o administrador.", 403);
        }
    }
    // =================================================================

    // 5. Sucesso - Gera Sessão/Token
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['tenant_id'] = $user['tenant_id'];
    $_SESSION['user_role'] = $user['role_id']; // Ou slug da role se preferir
    $_SESSION['user_email'] = $user['email'];

    echo json_encode([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'data' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'tenant_slug' => $user['tenant_slug'],
            'role_id' => $user['role_id']
        ]
    ]);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>