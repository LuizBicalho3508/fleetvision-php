<?php
// ARQUIVO: api_estoque.php
require_once 'db.php';
require_once 'utils/Security.php';
require_once 'TraccarApi.php'; // Para sincronizar com Traccar se necessário

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); exit(json_encode(['success' => false, 'error' => 'Sessão expirada']));
}

$tenant_id = $_SESSION['tenant_id'];
$user_role = $_SESSION['user_role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $user_role !== 'admin' && $user_role !== 'superadmin') {
    http_response_code(403); exit(json_encode(['success' => false, 'error' => 'Acesso negado']));
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    switch ($action) {
        case 'get_stock':
            $search = $_GET['search'] ?? '';
            $sql = "SELECT * FROM saas_stock WHERE tenant_id = ?";
            $params = [$tenant_id];

            if ($search) {
                $sql .= " AND (identifier ILIKE ? OR model ILIKE ? OR sim_number ILIKE ?)";
                $term = "%$search%";
                array_push($params, $term, $term, $term);
            }
            $sql .= " ORDER BY id DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stock = $stmt->fetchAll();

            // Adiciona flag se está em uso
            foreach($stock as &$s) {
                $stmtUse = $pdo->prepare("SELECT v.name, c.name as client_name FROM saas_vehicles v LEFT JOIN saas_customers c ON v.client_id = c.id WHERE v.traccar_device_id = ? OR v.identifier = ?");
                $stmtUse->execute([$s['traccar_device_id'], $s['identifier']]);
                if($usage = $stmtUse->fetch()) {
                    $s['is_used'] = true;
                    $s['vehicle_name'] = $usage['name'];
                    $s['client_name'] = $usage['client_name'];
                } else {
                    $s['is_used'] = false;
                }
            }
            echo json_encode(['success' => true, 'data' => $stock]);
            break;

        case 'save':
            $id = $input['id'] ?? null;
            $model = trim($input['model'] ?? 'Genérico');
            $imei = trim($input['imei'] ?? ''); // Identificador único
            $sim = trim($input['sim_number'] ?? '');
            $operator = trim($input['operator'] ?? '');
            
            if (empty($imei)) throw new Exception("IMEI/Identificador é obrigatório");

            // Verifica duplicidade
            $checkSql = "SELECT id FROM saas_stock WHERE identifier = ? AND tenant_id = ?";
            $checkParams = [$imei, $tenant_id];
            if ($id) { $checkSql .= " AND id != ?"; $checkParams[] = $id; }
            $stmtCheck = $pdo->prepare($checkSql);
            $stmtCheck->execute($checkParams);
            if ($stmtCheck->fetch()) throw new Exception("Este IMEI já está cadastrado no estoque.");

            // Aqui você poderia chamar TraccarApi para criar o device lá também, se desejar automatizar
            // Por simplicidade, assumimos que o ID do traccar será preenchido ou sincronizado depois
            // Ou geramos um ID temporário se não tiver integração direta agora
            
            // Para manter compatibilidade com a frota, precisamos garantir que traccar_device_id exista 
            // ou criar uma lógica de sincronização. Vamos salvar os dados locais.
            
            if ($id) {
                $sql = "UPDATE saas_stock SET model=?, identifier=?, imei=?, sim_number=?, operator=? WHERE id=? AND tenant_id=?";
                $pdo->prepare($sql)->execute([$model, $imei, $imei, $sim, $operator, $id, $tenant_id]);
            } else {
                // Tenta criar no Traccar (Opcional - Requer TraccarApi configurada)
                // $traccarApi = new TraccarApi(); 
                // $device = $traccarApi->createDevice($name, $imei); 
                // $traccarId = $device['id'];
                $traccarId = null; // Deixamos null por enquanto, pode ser preenchido via sync

                $sql = "INSERT INTO saas_stock (tenant_id, model, identifier, imei, sim_number, operator, traccar_device_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
                $pdo->prepare($sql)->execute([$tenant_id, $model, $imei, $imei, $sim, $operator, $traccarId]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $input['id'] ?? null;
            // Verifica uso
            $stmtGet = $pdo->prepare("SELECT traccar_device_id, identifier FROM saas_stock WHERE id = ?");
            $stmtGet->execute([$id]);
            $item = $stmtGet->fetch();
            
            if($item) {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM saas_vehicles WHERE traccar_device_id = ? OR identifier = ?");
                $stmtCheck->execute([$item['traccar_device_id'], $item['identifier']]);
                if ($stmtCheck->fetchColumn() > 0) throw new Exception("Equipamento em uso num veículo. Desvincule lá primeiro.");
            }

            $pdo->prepare("DELETE FROM saas_stock WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);
            echo json_encode(['success' => true]);
            break;

        default: throw new Exception("Ação desconhecida");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}