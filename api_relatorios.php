<?php
// ARQUIVO: api_relatorios.php
require_once 'db.php';
require_once 'utils/Security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); exit(json_encode(['success' => false, 'error' => 'Sessão expirada']));
}

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'user';

// Restrição de Cliente
$restriction = "";
if ($user_role !== 'admin' && $user_role !== 'superadmin') {
    $stmtC = $pdo->prepare("SELECT customer_id FROM saas_users WHERE id = ?");
    $stmtC->execute([$user_id]);
    $custId = $stmtC->fetchColumn();
    if ($custId) {
        $restriction = " AND v.client_id = $custId ";
    } else {
        $restriction = " AND v.user_id = $user_id ";
    }
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    switch ($action) {
        // Rota no Mapa (Histórico)
        case 'get_route_history':
            $deviceId = $_GET['device_id'] ?? null;
            $from = $_GET['from'] ?? null; // Formato: YYYY-MM-DD HH:mm:ss
            $to = $_GET['to'] ?? null;

            if (!$deviceId || !$from || !$to) throw new Exception("Parâmetros inválidos");

            // Valida se o veículo pertence ao Tenant/Cliente
            $stmtV = $pdo->prepare("SELECT traccar_device_id, name FROM saas_vehicles v WHERE id = ? AND tenant_id = ? $restriction");
            $stmtV->execute([$deviceId, $tenant_id]);
            $vehicle = $stmtV->fetch();

            if (!$vehicle) throw new Exception("Veículo não encontrado ou acesso negado.");
            $traccarId = $vehicle['traccar_device_id'];

            if (!$traccarId) throw new Exception("Veículo sem rastreador vinculado.");

            // Busca posições no banco do Traccar (tc_positions)
            // Limita a 5000 pontos para não travar o mapa
            $sql = "
                SELECT latitude, longitude, speed, course, fixtime, attributes, address
                FROM tc_positions 
                WHERE deviceid = ? AND fixtime BETWEEN ? AND ?
                ORDER BY fixtime ASC
                LIMIT 5000
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$traccarId, $from, $to]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Processa dados para o frontend (reduz tamanho do JSON)
            $route = array_map(function($r) {
                $attrs = json_decode($r['attributes'], true);
                return [
                    'lat' => (float)$r['latitude'],
                    'lng' => (float)$r['longitude'],
                    'spd' => round($r['speed'] * 1.852), // Knots para KM/h
                    'time' => date('d/m H:i', strtotime($r['fixtime'])),
                    'ign' => $attrs['ignition'] ?? false,
                    'addr' => $r['address']
                ];
            }, $rows);

            echo json_encode(['success' => true, 'data' => $route, 'vehicle' => $vehicle['name']]);
            break;

        // Relatório de Eventos (Alertas)
        case 'get_events_report':
            $from = $_GET['from'] ?? date('Y-m-d 00:00:00');
            $to = $_GET['to'] ?? date('Y-m-d 23:59:59');

            $sql = "
                SELECT e.type, e.eventtime, v.name as vehicle_name, v.plate, p.address
                FROM tc_events e
                JOIN saas_vehicles v ON v.traccar_device_id = e.deviceid
                LEFT JOIN tc_positions p ON e.positionid = p.id
                WHERE v.tenant_id = ? 
                AND e.eventtime BETWEEN ? AND ?
                $restriction
                ORDER BY e.eventtime DESC
                LIMIT 1000
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $from, $to]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Tradução de tipos de evento
            $dict = [
                'deviceOnline' => 'Online', 'deviceOffline' => 'Offline',
                'deviceMoving' => 'Em Movimento', 'deviceStopped' => 'Parado',
                'deviceOverspeed' => 'Excesso de Velocidade',
                'geofenceEnter' => 'Entrou na Cerca', 'geofenceExit' => 'Saiu da Cerca',
                'ignitionOn' => 'Ignição Ligada', 'ignitionOff' => 'Ignição Desligada'
            ];

            foreach($events as &$e) {
                $e['type_translated'] = $dict[$e['type']] ?? $e['type'];
                $e['time_formatted'] = date('d/m/Y H:i:s', strtotime($e['eventtime']));
            }

            echo json_encode(['success' => true, 'data' => $events]);
            break;

        // Lista Simples de Veículos (para Selects)
        case 'get_vehicle_list':
            $sql = "SELECT id, name, plate FROM saas_vehicles v WHERE tenant_id = ? AND status = 'active' $restriction ORDER BY name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        default:
            throw new Exception("Ação desconhecida");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}