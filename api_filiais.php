<?php
// ARQUIVO: api_filiais.php
require_once 'db.php';
require_once 'utils/Security.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) exit;

$tenant_id = $_SESSION['tenant_id'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM saas_branches WHERE tenant_id = ? ORDER BY name");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    elseif ($action === 'save') {
        $id = $input['id'] ?? null;
        $name = $input['name'];
        if(!$name) throw new Exception("Nome obrigatório");

        if($id) {
            $pdo->prepare("UPDATE saas_branches SET name=?, address=?, phone=? WHERE id=? AND tenant_id=?")
                ->execute([$name, $input['address']??'', $input['phone']??'', $id, $tenant_id]);
        } else {
            $pdo->prepare("INSERT INTO saas_branches (tenant_id, name, address, phone, created_at) VALUES (?, ?, ?, ?, NOW())")
                ->execute([$tenant_id, $name, $input['address']??'', $input['phone']??'']);
        }
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'delete') {
        // Valida se tem uso
        $check = $pdo->prepare("SELECT COUNT(*) FROM saas_users WHERE branch_id = ?");
        $check->execute([$input['id']]);
        if($check->fetchColumn() > 0) throw new Exception("Filial em uso por usuários.");
        
        $pdo->prepare("DELETE FROM saas_branches WHERE id=? AND tenant_id=?")->execute([$input['id'], $tenant_id]);
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }