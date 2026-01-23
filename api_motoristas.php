<?php
// ARQUIVO: api_motoristas.php
require_once 'db.php';
require_once 'utils/Security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); exit(json_encode(['success' => false, 'error' => 'Sessão expirada']));
}

$tenant_id = $_SESSION['tenant_id'];
$user_role = $_SESSION['user_role'] ?? 'user';

// Apenas Admins editam (ajuste conforme necessário)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $user_role !== 'admin' && $user_role !== 'superadmin') {
    http_response_code(403); exit(json_encode(['success' => false, 'error' => 'Acesso negado']));
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    switch ($action) {
        case 'get_drivers':
            $search = $_GET['search'] ?? '';
            $sql = "SELECT * FROM saas_drivers WHERE tenant_id = ?";
            $params = [$tenant_id];

            if ($search) {
                $sql .= " AND (name ILIKE ? OR cnh ILIKE ? OR phone ILIKE ?)";
                $term = "%$search%";
                array_push($params, $term, $term, $term);
            }
            $sql .= " ORDER BY name ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Verifica status da CNH
            $today = date('Y-m-d');
            foreach($data as &$d) {
                if($d['cnh_expiry']) {
                    if($d['cnh_expiry'] < $today) $d['cnh_status'] = 'expired';
                    elseif($d['cnh_expiry'] < date('Y-m-d', strtotime('+30 days'))) $d['cnh_status'] = 'warning';
                    else $d['cnh_status'] = 'ok';
                } else {
                    $d['cnh_status'] = 'none';
                }
            }

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'save':
            $id = $input['id'] ?? null;
            $name = trim($input['name'] ?? '');
            $cnh = trim($input['cnh'] ?? '');
            $validity = !empty($input['cnh_expiry']) ? $input['cnh_expiry'] : null;
            $phone = trim($input['phone'] ?? '');
            $rfid = trim($input['rfid'] ?? '');

            if (empty($name)) throw new Exception("Nome é obrigatório");

            if ($id) {
                $sql = "UPDATE saas_drivers SET name=?, cnh=?, cnh_expiry=?, phone=?, rfid=? WHERE id=? AND tenant_id=?";
                $pdo->prepare($sql)->execute([$name, $cnh, $validity, $phone, $rfid, $id, $tenant_id]);
            } else {
                $sql = "INSERT INTO saas_drivers (tenant_id, name, cnh, cnh_expiry, phone, rfid, status) VALUES (?, ?, ?, ?, ?, ?, 'active')";
                $pdo->prepare($sql)->execute([$tenant_id, $name, $cnh, $validity, $phone, $rfid]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $input['id'] ?? null;
            // Verifica vínculo com veículo
            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM saas_vehicles WHERE driver_id = ? AND tenant_id = ?");
            $stmtC->execute([$id, $tenant_id]);
            if ($stmtC->fetchColumn() > 0) throw new Exception("Motorista vinculado a um veículo. Desvincule antes de excluir.");

            $pdo->prepare("DELETE FROM saas_drivers WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);
            echo json_encode(['success' => true]);
            break;

        default: throw new Exception("Ação desconhecida");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}