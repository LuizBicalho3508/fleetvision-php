<?php
// ARQUIVO: api_frota.php
require_once 'db.php';
require_once 'utils/Security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); exit(json_encode(['success' => false, 'error' => 'Sessão expirada']));
}

$tenant_id = $_SESSION['tenant_id'];
$user_role = $_SESSION['user_role'] ?? 'user';

// Se for cliente final, apenas leitura (se necessário expandir no futuro)
// Por enquanto, assumimos que apenas Admins editam frota via API
if ($user_role !== 'admin' && $user_role !== 'superadmin') {
    // Permite apenas GET se quiser liberar leitura para cliente via API
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(403); exit(json_encode(['success' => false, 'error' => 'Sem permissão de escrita']));
    }
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    switch ($action) {
        case 'get_vehicles':
            $search = $_GET['search'] ?? '';
            $sql = "
                SELECT v.*, 
                       COALESCE(s.identifier, s.imei, v.identifier, 'S/ Rastreador') as imei_display, 
                       s.model as tracker_model,
                       c.name as client_name,
                       icon.url as icon_url,
                       icon.name as icon_name
                FROM saas_vehicles v 
                LEFT JOIN saas_stock s ON (v.traccar_device_id = s.traccar_device_id OR v.identifier = s.identifier)
                LEFT JOIN saas_customers c ON v.client_id = c.id
                LEFT JOIN saas_custom_icons icon ON CAST(v.category AS VARCHAR) = CAST(icon.id AS VARCHAR)
                WHERE v.tenant_id = ?
            ";
            $params = [$tenant_id];

            if ($search) {
                $sql .= " AND (v.name ILIKE ? OR v.plate ILIKE ? OR s.identifier ILIKE ? OR c.name ILIKE ?)";
                $term = "%$search%";
                array_push($params, $term, $term, $term, $term);
            }
            
            $sql .= " ORDER BY v.name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'save':
            if (empty($input['name'])) throw new Exception("Nome é obrigatório");
            
            $id = !empty($input['id']) ? $input['id'] : null;
            $name = trim($input['name']);
            $plate = trim($input['plate'] ?? '');
            $model = trim($input['model'] ?? '');
            $category = $input['category'] ?? null;
            $client_id = !empty($input['client_id']) ? $input['client_id'] : null;
            $device_id = !empty($input['traccar_device_id']) ? $input['traccar_device_id'] : null;
            $idle = $input['idle_threshold'] ?? 5;
            $identifier = null;

            // Lógica de Vínculo com Estoque
            if ($device_id) {
                // Verifica duplicidade
                $checkParams = [$device_id, $tenant_id];
                $checkSql = "SELECT id, name FROM saas_vehicles WHERE traccar_device_id = ? AND tenant_id = ?";
                if ($id) { $checkSql .= " AND id != ?"; $checkParams[] = $id; }
                
                $stmtC = $pdo->prepare($checkSql);
                $stmtC->execute($checkParams);
                if ($dup = $stmtC->fetch()) throw new Exception("Rastreador já em uso no veículo: " . $dup['name']);

                // Pega identificador do estoque
                $stmtS = $pdo->prepare("SELECT COALESCE(identifier, imei) FROM saas_stock WHERE traccar_device_id = ?");
                $stmtS->execute([$device_id]);
                $identifier = $stmtS->fetchColumn();
            }

            // Fallback de identificador
            if (!$identifier) {
                $identifier = "TEMP-" . time() . rand(100,999);
            }

            if ($id) {
                $sql = "UPDATE saas_vehicles SET name=?, plate=?, model=?, category=?, traccar_device_id=?, client_id=?, idle_threshold=?, identifier=? WHERE id=? AND tenant_id=?";
                $pdo->prepare($sql)->execute([$name, $plate, $model, $category, $device_id, $client_id, $idle, $identifier, $id, $tenant_id]);
            } else {
                $sql = "INSERT INTO saas_vehicles (tenant_id, name, plate, model, category, traccar_device_id, client_id, idle_threshold, identifier, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
                $pdo->prepare($sql)->execute([$tenant_id, $name, $plate, $model, $category, $device_id, $client_id, $idle, $identifier]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $input['id'] ?? null;
            if (!$id) throw new Exception("ID inválido");
            $pdo->prepare("DELETE FROM saas_vehicles WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);
            echo json_encode(['success' => true]);
            break;
            
        case 'toggle_status':
            $id = $input['id'] ?? null;
            $status = ($input['active'] == 'true' || $input['active'] === true) ? 'active' : 'inactive';
            $pdo->prepare("UPDATE saas_vehicles SET status = ? WHERE id = ? AND tenant_id = ?")->execute([$status, $id, $tenant_id]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Ação desconhecida");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}