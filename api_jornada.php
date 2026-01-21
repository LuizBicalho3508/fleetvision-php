<?php
// ARQUIVO: api_jornada.php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Segurança
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) { 
    http_response_code(403); 
    exit(json_encode(['error' => 'Sessão expirada.'])); 
}

if (!file_exists('db.php')) {
    http_response_code(500);
    exit(json_encode(['error' => 'Erro crítico: db.php não encontrado.']));
}
require 'db.php';

$tenant_id  = $_SESSION['tenant_id'];
$user_email = $_SESSION['user_email'] ?? '';
$user_role  = $_SESSION['user_role'] ?? 'user';

// Parâmetros da Lei 13.103
$MAX_CONTINUOUS = 5.5 * 3600; // 5h30
$MAX_DAILY      = 10 * 3600;  // 10h
$MIN_REST       = 30 * 60;    // 30min

try {
    // 2. Filtros de Permissão
    $customerFilter = "";
    $params = ['tid' => $tenant_id];

    if ($user_role != 'admin' && $user_role != 'superadmin') {
        $stmtMe = $pdo->prepare("SELECT customer_id FROM saas_users WHERE id = ?");
        $stmtMe->execute([$_SESSION['user_id']]);
        $userCustomerId = $stmtMe->fetchColumn();

        if (!$userCustomerId && !empty($user_email)) {
            $stmtEmail = $pdo->prepare("SELECT id FROM saas_customers WHERE email = ? AND tenant_id = ?");
            $stmtEmail->execute([$user_email, $tenant_id]);
            $userCustomerId = $stmtEmail->fetchColumn();
        }

        if ($userCustomerId) {
            $customerFilter = " AND d.customer_id = :cid";
            $params['cid'] = $userCustomerId;
        } else {
            echo json_encode([]); exit;
        }
    }

    // 3. Query Inteligente (Jornada + Telemetria em Tempo Real)
    // Fazemos JOIN com tc_devices/positions para saber se a ignição está REALMENTE ligada
    $sql = "
        SELECT 
            j.driver_id, d.name as driver_name, d.cnh_number, d.rfid_tag,
            j.vehicle_id, v.name as vehicle_name, v.plate, v.current_driver_id,
            j.start_time, 
            j.end_time,
            -- Duração bruta
            EXTRACT(EPOCH FROM (COALESCE(j.end_time, NOW()) - j.start_time)) as duration,
            CASE WHEN j.end_time IS NULL THEN 1 ELSE 0 END as db_is_open,
            -- Telemetria para validação
            pos.attributes as pos_attributes,
            pos.speed as current_speed,
            pos.fixtime as last_pos_time
        FROM saas_driver_journeys j
        JOIN saas_drivers d ON j.driver_id = d.id
        JOIN saas_vehicles v ON j.vehicle_id = v.id
        -- Joins para checagem de status real
        LEFT JOIN tc_devices dev ON v.traccar_device_id = dev.id
        LEFT JOIN tc_positions pos ON dev.positionid = pos.id
        WHERE j.tenant_id = :tid
          $customerFilter
          AND (
              DATE(j.start_time) = CURRENT_DATE 
              OR DATE(j.end_time) = CURRENT_DATE 
              OR j.end_time IS NULL
          )
        ORDER BY j.driver_id, j.start_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawJourneys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Processamento Lógico
    $drivers = [];

    foreach ($rawJourneys as $row) {
        $did = $row['driver_id'];
        
        if (!isset($drivers[$did])) {
            $drivers[$did] = [
                'id' => $did,
                'name' => $row['driver_name'],
                'cnh' => $row['cnh_number'] ?? 'N/A',
                'status' => 'descanso',
                'current_vehicle' => '-',
                'total_driving' => 0,
                'continuous_driving' => 0,
                'violations' => [],
                'journeys_count' => 0,
                'last_end_time' => 0,
                'last_start_time' => 0
            ];
        }

        // Parse da Telemetria
        $telemetry = json_decode($row['pos_attributes'] ?? '{}', true);
        $ignitionOn = isset($telemetry['ignition']) ? (bool)$telemetry['ignition'] : false;
        // Se não tem ignição, usa velocidade > 5km/h como fallback
        if (!isset($telemetry['ignition']) && isset($row['current_speed'])) {
            $ignitionOn = ($row['current_speed'] > 2); 
        }

        // Validação de "Motorista Atual"
        // Se o veículo agora está com OUTRO motorista, esta jornada aberta é "zumbi"
        $isDriverMatch = true;
        if (!empty($row['current_driver_id'])) {
            if ($row['current_driver_id'] != $did) {
                $isDriverMatch = false; // Outro motorista assumiu o veículo
            }
        }

        $duration = floatval($row['duration']);
        $startTime = strtotime($row['start_time']);
        $endTime = $row['end_time'] ? strtotime($row['end_time']) : time(); 
        
        // CORREÇÃO CRÍTICA:
        // Se a jornada está "Aberta" no banco, mas a ignição está OFF ou outro motorista assumiu,
        // paramos de contar o tempo visualmente no momento da última posição ou agora.
        $isActive = ($row['db_is_open'] == 1);
        
        if ($isActive) {
            if (!$ignitionOn || !$isDriverMatch) {
                // Veículo parado ou motorista trocado: considera jornada pausada/encerrada para cálculo
                // Ajusta a duração para não inflar infinitamente
                $isActive = false; 
                
                // Se tiver last_pos_time, usa ele como fim estimado, senão usa start_time (duração 0)
                $realEnd = $row['last_pos_time'] ? strtotime($row['last_pos_time']) : time();
                $duration = max(0, $realEnd - $startTime);
                $endTime = $realEnd;
            }
        }

        // Soma tempos
        $drivers[$did]['total_driving'] += $duration;
        $drivers[$did]['journeys_count']++;

        // Regra do Descanso (Zera contínuo se parou > 30min)
        if ($drivers[$did]['last_end_time'] > 0) {
            $restTime = $startTime - $drivers[$did]['last_end_time'];
            if ($restTime >= $MIN_REST) {
                $drivers[$did]['continuous_driving'] = 0;
            }
        }
        
        $drivers[$did]['continuous_driving'] += $duration;
        $drivers[$did]['last_end_time'] = $endTime;

        // Define Status Visual Final
        if ($isActive) {
            $drivers[$did]['status'] = 'dirigindo';
            $drivers[$did]['current_vehicle'] = $row['vehicle_name'] . ' (' . ($row['plate'] ?: 'S/ PLACA') . ')';
            $drivers[$did]['last_start_time'] = $startTime;
        }
    }

    // 5. Validação de Infrações
    foreach ($drivers as &$d) {
        if ($d['total_driving'] > $MAX_DAILY) {
            $d['violations'][] = 'Excedeu 10h diárias';
        }
        if ($d['continuous_driving'] > $MAX_CONTINUOUS) {
            $d['violations'][] = 'Excedeu 5h30m ininterruptas';
        }
        
        if (empty($d['violations'])) {
            $d['health'] = 'ok';
        } elseif (count($d['violations']) == 1 && $d['total_driving'] < $MAX_DAILY) {
            $d['health'] = 'warning';
        } else {
            $d['health'] = 'critical';
        }
    }

    echo json_encode(array_values($drivers));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>