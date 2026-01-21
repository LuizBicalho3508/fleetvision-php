<?php
// ARQUIVO: api_mapa.php
session_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['user_id'])) { 
    http_response_code(403); exit(json_encode(['error' => 'Sessão expirada.'])); 
}
if (!file_exists('db.php')) {
    http_response_code(500); exit(json_encode(['error' => 'Erro crítico: db.php.']));
}
require 'db.php';

$tenant_id  = $_SESSION['tenant_id'];
$user_id    = $_SESSION['user_id'];
$user_role  = $_SESSION['user_role'] ?? 'user';
$action     = $_REQUEST['action'] ?? 'get_initial_data';

switch ($action) {
    case 'get_initial_data': getInitialMapData($pdo, $tenant_id, $user_id, $user_role); break;
    case 'geocode': handleGeocode($_GET['lat']??0, $_GET['lon']??0, $pdo); break;
    case 'secure_command': handleSecureCommand($pdo, $user_id, $tenant_id, $user_role); break;
    default: http_response_code(404); echo json_encode(['error' => 'Action inválida']); break;
}

function getInitialMapData($pdo, $tenant_id, $user_id, $user_role) {
    // 1. Carrega todos os Motoristas do Tenant para Memória (Lookup Table)
    // Isso permite associar o ID do cartão ao Nome rapidamente
    $driversMap = [];
    try {
        $stmtD = $pdo->prepare("SELECT rfid_tag, name FROM saas_drivers WHERE tenant_id = ? AND rfid_tag IS NOT NULL");
        $stmtD->execute([$tenant_id]);
        while ($row = $stmtD->fetch(PDO::FETCH_ASSOC)) {
            // Limpa o RFID para garantir match (remove espaços, etc)
            $cleanTag = trim($row['rfid_tag']);
            if($cleanTag) $driversMap[$cleanTag] = $row['name'];
        }
    } catch (Exception $e) {}

    // 2. Busca Veículos
    $sql = "SELECT v.id, v.name, v.plate, v.traccar_device_id, v.category, v.last_telemetry,
            i.url as icon_url, c.name as client_name, d.name as db_driver_name
            FROM saas_vehicles v 
            LEFT JOIN saas_custom_icons i ON CAST(v.category AS VARCHAR) = CAST(i.id AS VARCHAR) 
            LEFT JOIN saas_customers c ON v.client_id = c.id
            LEFT JOIN saas_drivers d ON v.current_driver_id = d.id
            WHERE v.tenant_id = ?";
    
    $params = [$tenant_id];
    // (Filtros de permissão omitidos para brevidade, mas devem ser mantidos como no anterior)
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dbVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $vehiclesData = []; $customerData = []; $staticInfo = []; $allowedIds = []; $savedStates = [];

    foreach ($dbVehicles as $v) {
        if ($v['traccar_device_id']) {
            $tid = (int)$v['traccar_device_id'];
            $allowedIds[] = $tid;
            
            if ($v['icon_url']) $vehiclesData[$tid] = (strpos($v['icon_url'], '/')===0)?$v['icon_url']:'/'.$v['icon_url'];
            if ($v['client_name']) $customerData[$tid] = $v['client_name'];
            
            $staticInfo[$tid] = [
                'plate' => $v['plate'] ?: 'S/ PLACA',
                'name' => $v['name'],
                'driver' => $v['db_driver_name'] // Motorista fixo do banco (fallback)
            ];

            if (!empty($v['last_telemetry'])) $savedStates[$tid] = json_decode($v['last_telemetry'], true);
        }
    }

    // 3. Dados Traccar
    $traccarPositions = fetchTraccarData("/positions");
    $traccarDevices = fetchTraccarData("/devices");
    
    $posMap = []; foreach ($traccarPositions as $p) $posMap[$p['deviceId']] = $p;
    $devMap = []; foreach ($traccarDevices as $d) $devMap[$d['id']] = $d;

    $finalDevices = []; $initialPositions = [];

    foreach ($dbVehicles as $v) {
        $tid = (int)$v['traccar_device_id'];
        if (!$tid) continue;

        $tData = $devMap[$tid] ?? ['id'=>$tid, 'status'=>'offline', 'lastUpdate'=>null, 'disabled'=>false];
        $tData['name'] = $v['name'];
        $finalDevices[] = $tData;

        $livePos = $posMap[$tid] ?? null;
        $savedState = $savedStates[$tid] ?? [];

        if ($livePos) {
            // NORMALIZAÇÃO COM LÓGICA SUNTECH + DRIVERS
            $normalized = normalizeTelemetry($livePos, $savedState, $driversMap, $staticInfo[$tid]['driver']);
            $livePos['saas_state'] = $normalized;
            $initialPositions[] = $livePos;

            // Atualiza Banco se mudou
            try {
                $jsonState = json_encode($normalized);
                if ($jsonState !== $v['last_telemetry']) {
                    $pdo->prepare("UPDATE saas_vehicles SET last_telemetry = ? WHERE id = ?")->execute([$jsonState, $v['id']]);
                }
            } catch (Exception $e) {}
        }
    }

    echo json_encode([
        'success' => true,
        'config' => [
            'icons' => $vehiclesData, 'customers' => $customerData, 'staticInfo' => $staticInfo,
            'allowedIds' => $allowedIds, 'wsToken' => getTraccarSessionToken()
        ],
        'data' => ['devices' => $finalDevices, 'positions' => $initialPositions]
    ]);
}

// --- NORMALIZAÇÃO PODEROSA ---
function normalizeTelemetry($pos, $oldState, $driversMap, $defaultDriverName) {
    $attr = $pos['attributes'] ?? [];
    
    // 1. IGNIÇÃO
    $ign = $attr['ignition'] ?? ($attr['motion'] ?? ($oldState['ignition'] ?? false));

    // 2. VOLTAGEM (Externa)
    $pwr = $attr['power'] ?? ($attr['adc1'] ?? ($attr['extBatt'] ?? null));
    if ($pwr > 1000) $pwr = $pwr / 1000;
    if ($pwr === null && !empty($attr['charge'])) $pwr = 12.0;
    if ($pwr === null) $pwr = $oldState['power'] ?? 0;

    // 3. BATERIA INTERNA
    $bat = $attr['batteryLevel'] ?? null;
    if ($bat === null && isset($attr['battery'])) {
        $raw = $attr['battery'];
        if ($raw > 100) $raw /= 1000;
        if ($raw > 0) $bat = min(100, max(0, round(($raw - 3.6) / (4.2 - 3.6) * 100)));
    }
    if ($bat === null) $bat = $oldState['battery'] ?? 0;

    // 4. BLOQUEIO (Mapeamento de Output)
    // Se 'blocked' vier explícito, usa. Senão, olha 'out1' (comum em Suntech/Teltonika para relé)
    $blk = $attr['blocked'] ?? ($attr['out1'] ?? ($oldState['blocked'] ?? false));

    // 5. MOTORISTA (Lógica Suntech Serial)
    $driverName = $defaultDriverName; // Começa com o do banco
    $driverIdFound = $attr['driverUniqueId'] ?? null;

    // Parser Suntech Serial: "SGBT|6|1|0|14179284|1|"
    if (empty($driverIdFound) && !empty($attr['serial'])) {
        $parts = explode('|', $attr['serial']);
        // Geralmente o ID está na posição 4 (índice 4)
        if (isset($parts[4]) && is_numeric($parts[4]) && $parts[4] > 0) {
            $driverIdFound = $parts[4];
        }
    }

    // Se achou um ID no pacote, tenta achar o nome no mapa de drivers
    if ($driverIdFound && isset($driversMap[$driverIdFound])) {
        $driverName = $driversMap[$driverIdFound];
    } elseif ($driverIdFound) {
        $driverName = "ID: $driverIdFound"; // Mostra o ID se não achar o nome
    }

    return [
        'ignition' => (bool)$ign,
        'battery'  => (int)$bat,
        'power'    => round((float)$pwr, 1),
        'sat'      => $attr['sat'] ?? ($oldState['sat'] ?? 0),
        'blocked'  => (bool)$blk,
        'driver_name' => $driverName // Retorna o nome resolvido para o Frontend
    ];
}

// --- HELPERS (Iguais aos anteriores) ---
function fetchTraccarData($endpoint) {
    global $TRACCAR_HOST;
    $TRACCAR_HOST = 'http://127.0.0.1:8082/api';
    $cookie = sys_get_temp_dir() . '/traccar_cookie_global.txt';
    if (!file_exists($cookie) || (time()-filemtime($cookie)>1800)) {
        $ch = curl_init("$TRACCAR_HOST/session"); curl_setopt($ch, CURLOPT_POSTFIELDS, "email=admin&password=admin");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie); curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); curl_exec($ch); curl_close($ch);
    }
    $ch = curl_init($TRACCAR_HOST . $endpoint); curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $d = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($c>=200 && $c<300) ? json_decode($d, true) : [];
}
function getTraccarSessionToken() {
    $c = @file_get_contents(sys_get_temp_dir() . '/traccar_cookie_global.txt');
    if ($c && preg_match('/JSESSIONID\s+([^\s]+)/', $c, $m)) return $m[1]; return '';
}
function handleGeocode($lat, $lon, $pdo) {
    $lat = round($lat, 5); $lon = round($lon, 5);
    try {
        $stmt=$pdo->prepare("SELECT address FROM saas_address_cache WHERE lat=? AND lon=? LIMIT 1"); $stmt->execute([$lat, $lon]);
        if($r=$stmt->fetchColumn()) { echo json_encode(['address'=>$r]); return; }
    } catch(e){}
    $ch = curl_init("https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&zoom=18&addressdetails=1");
    curl_setopt($ch, CURLOPT_USERAGENT, "FV/1.0"); curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $j = json_decode(curl_exec($ch), true); curl_close($ch);
    $a = $j['display_name'] ?? 'Local desconhecido';
    if($a!=='Local desconhecido') try{$pdo->prepare("INSERT INTO saas_address_cache (lat,lon,address) VALUES (?,?,?)")->execute([$lat,$lon,$a]);}catch(e){}
    echo json_encode(['address'=>$a]);
}
function handleSecureCommand($pdo, $uid, $tid, $role) {
    $in = json_decode(file_get_contents('php://input'), true);
    if(empty($in['password'])) { http_response_code(400); exit; }
    $stmt=$pdo->prepare("SELECT password FROM saas_users WHERE id=?"); $stmt->execute([$uid]);
    if(!password_verify($in['password'], $stmt->fetchColumn())) { http_response_code(401); exit(json_encode(['error'=>'Senha incorreta'])); }
    $ch=curl_init("http://127.0.0.1:8082/api/commands/send"); curl_setopt($ch, CURLOPT_USERPWD, "admin:admin");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['deviceId'=>(int)$in['deviceId'], 'type'=>$in['type']]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $res=curl_exec($ch); $code=curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if($code>=400) { http_response_code(500); echo $res; } else echo json_encode(['success'=>true]);
}
?>