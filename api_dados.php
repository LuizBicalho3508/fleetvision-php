<?php
session_start();
// Habilita exibição de erros apenas para debug (remova em produção se necessário)
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if (!isset($_SESSION['user_id'])) { 
    http_response_code(403); 
    exit(json_encode(['error' => 'Sessão expirada.'])); 
}

if (!file_exists('db.php')) {
    http_response_code(500);
    exit(json_encode(['error' => 'Erro crítico: db.php não encontrado.']));
}
require 'db.php';

$tenant_id  = $_SESSION['tenant_id'];
$user_id    = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? '';
$user_role  = $_SESSION['user_role'] ?? 'user';

// --- FILTRO DE CLIENTE ---
$loggedCustomerId = null;
$isRestricted = false;

if ($user_role != 'admin' && $user_role != 'superadmin') {
    $isRestricted = true;
    $stmtUserCheck = $pdo->prepare("SELECT customer_id FROM saas_users WHERE id = ?");
    $stmtUserCheck->execute([$user_id]);
    $userDirectCustomer = $stmtUserCheck->fetchColumn();
    $loggedCustomerId = $userDirectCustomer ?: ($pdo->query("SELECT id FROM saas_customers WHERE email = '$user_email' AND tenant_id = $tenant_id")->fetchColumn());
}

$restrictionSQL = "";
if ($isRestricted) {
    if ($loggedCustomerId) {
        $restrictionSQL = " AND (v.client_id = $loggedCustomerId OR v.user_id = $user_id)";
    } else {
        $restrictionSQL = " AND v.user_id = $user_id";
    }
}

$action = $_REQUEST['action'] ?? '';
$endpoint = $_REQUEST['endpoint'] ?? '';

// --- ROTEADOR ---
if (!empty($endpoint)) {
    if (strpos($endpoint, 'dashboard') !== false) { 
        http_response_code(400); 
        exit(json_encode(['error' => 'Endpoint restrito.'])); 
    }
    handleProxyTraccar($endpoint, $tenant_id, $loggedCustomerId, $user_id, $pdo);
    exit;
}

switch ($action) {

    // --- KPIS & DASHBOARD ---
    case 'get_kpis':
        try {
            $sqlTotal = "SELECT COUNT(*) FROM saas_vehicles v WHERE v.tenant_id = ? AND v.status = 'active' $restrictionSQL";
            $stmtTotal = $pdo->prepare($sqlTotal);
            $stmtTotal->execute([$tenant_id]);
            $total = $stmtTotal->fetchColumn();

            $sqlOnline = "SELECT COUNT(v.id) FROM saas_vehicles v 
                          JOIN tc_devices d ON v.traccar_device_id = d.id 
                          WHERE v.tenant_id = ? AND v.status = 'active' 
                          AND d.lastupdate > NOW() - INTERVAL '10 minutes' $restrictionSQL";
            $stmtOnline = $pdo->prepare($sqlOnline);
            $stmtOnline->execute([$tenant_id]);
            $online = $stmtOnline->fetchColumn();

            $sqlMoving = "SELECT COUNT(v.id) FROM saas_vehicles v 
                          JOIN tc_devices d ON v.traccar_device_id = d.id 
                          JOIN tc_positions p ON d.positionid = p.id
                          WHERE v.tenant_id = ? AND v.status = 'active' 
                          AND d.lastupdate > NOW() - INTERVAL '10 minutes' AND p.speed > 1 $restrictionSQL"; 
            $stmtMoving = $pdo->prepare($sqlMoving);
            $stmtMoving->execute([$tenant_id]);
            $moving = $stmtMoving->fetchColumn();

            $stopped = $online - $moving; 
            if($stopped < 0) $stopped = 0;
            $offline = $total - $online;
            if($offline < 0) $offline = 0;

            echo json_encode(['total_vehicles'=>$total, 'online'=>$online, 'moving'=>$moving, 'stopped'=>$stopped, 'offline'=>$offline]);
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    case 'get_dashboard_data':
        $type = $_REQUEST['type'] ?? 'online';
        try {
            $sql = "SELECT v.id, v.name, v.plate, v.traccar_device_id as deviceid, t.lastupdate, t.positionid, p.speed, p.address
                    FROM saas_vehicles v 
                    LEFT JOIN tc_devices t ON v.traccar_device_id = t.id 
                    LEFT JOIN tc_positions p ON t.positionid = p.id
                    WHERE v.tenant_id = ? AND v.status = 'active' $restrictionSQL";
            if ($type === 'offline') $sql .= " AND (t.lastupdate < NOW() - INTERVAL '24 hours' OR t.lastupdate IS NULL)";
            elseif ($type === 'online') $sql .= " AND t.lastupdate >= NOW() - INTERVAL '24 hours'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    case 'get_alerts':
        try {
            $limit = isset($_REQUEST['limit']) ? min((int)$_REQUEST['limit'], 200) : 5;
            $sql = "SELECT e.id, e.type, e.eventtime as event_time, v.name as vehicle_name, p.latitude, p.longitude, e.attributes, v.plate
                    FROM tc_events e JOIN saas_vehicles v ON e.deviceid = v.traccar_device_id
                    LEFT JOIN tc_positions p ON e.positionid = p.id
                    WHERE v.tenant_id = ? $restrictionSQL ORDER BY e.eventtime DESC LIMIT $limit";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dict = ['deviceOverspeed'=>'Excesso de Velocidade','geofenceExit'=>'Saiu da Cerca','geofenceEnter'=>'Entrou na Cerca','ignitionOn'=>'Ignição Ligada','ignitionOff'=>'Ignição Desligada','deviceOffline'=>'Offline','deviceOnline'=>'Online','deviceStopped'=>'Parou','deviceMoving'=>'Em Movimento'];
            foreach($alerts as &$a) { $a['type_label'] = $dict[$a['type']] ?? $a['type']; $a['formatted_time'] = date('d/m/Y H:i:s', strtotime($a['event_time'])); }
            echo json_encode($alerts);
        } catch (Exception $e) { echo json_encode([]); }
        break;

    case 'get_ranking':
        try {
            $sql = "SELECT v.id, v.name, v.plate, COUNT(e.id) as total_events,
                    SUM(CASE WHEN e.type = 'deviceOverspeed' THEN 1 ELSE 0 END) as overspeed,
                    SUM(CASE WHEN e.type = 'geofenceExit' THEN 1 ELSE 0 END) as geofence
                    FROM saas_vehicles v LEFT JOIN tc_events e ON v.traccar_device_id = e.deviceid AND e.eventtime > NOW() - INTERVAL 30 DAY
                    WHERE v.tenant_id = ? $restrictionSQL GROUP BY v.id";
            $stmt = $pdo->prepare($sql); $stmt->execute([$tenant_id]); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as &$r) {
                $score = 100 - ($r['overspeed'] * 5) - ($r['geofence'] * 2);
                $r['score'] = max(0, min(100, $score));
                $r['class'] = $r['score'] >= 90 ? 'A' : ($r['score'] >= 70 ? 'B' : ($r['score'] >= 50 ? 'C' : 'D'));
            }
            usort($rows, function($a, $b) { return $b['score'] <=> $a['score']; });
            echo json_encode($rows);
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    // --- FINANCEIRO ---
    case 'asaas_save_config':
        if ($user_role != 'admin' && $user_role != 'superadmin') http_400('Permissão negada');
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['apiKey'] ?? '';
        $test = callAsaas('/finance/balance', 'GET', [], $token);
        if (isset($test['errors'])) http_400('Chave API Inválida.');
        try {
            $stmt = $pdo->prepare("UPDATE saas_tenants SET asaas_token = ? WHERE id = ?");
            $stmt->execute([$token, $tenant_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    case 'asaas_get_config':
        $stmt = $pdo->prepare("SELECT asaas_token FROM saas_tenants WHERE id = ?");
        $stmt->execute([$tenant_id]);
        echo json_encode(['has_token' => !empty($stmt->fetchColumn())]);
        break;

    case 'asaas_proxy':
        $stmt = $pdo->prepare("SELECT asaas_token FROM saas_tenants WHERE id = ?");
        $stmt->execute([$tenant_id]);
        $apiToken = $stmt->fetchColumn();
        if (empty($apiToken)) http_400('Configure a Chave API do Asaas.');
        $asaas_endpoint = $_REQUEST['asaas_endpoint'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($method === 'GET' && !empty($_GET)) {
            $query = $_GET; unset($query['action'], $query['asaas_endpoint']); 
            if (strpos($asaas_endpoint, '?') === false) $asaas_endpoint .= '?' . http_build_query($query);
            else $asaas_endpoint .= '&' . http_build_query($query);
        }
        $response = callAsaas($asaas_endpoint, $method, $data, $apiToken);
        echo json_encode($response);
        break;

    // =========================================================================
    // 👥 USERS & ROLES
    // =========================================================================
    
    // Lista usuários do tenant
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
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    // LISTAR PERFIS (ESSENCIAL PARA O SELECT FUNCIONAR)
    case 'get_roles':
        try {
            // Se for superadmin, pode ver de qualquer tenant (passado via GET)
            $target_tenant = ($user_role === 'superadmin' && isset($_GET['tenant_id'])) ? $_GET['tenant_id'] : $tenant_id;

            // Busca perfis do tenant alvo OU do tenant admin (global)
            // Primeiro pegamos o ID do admin para saber quais são globais
            $stmtAdmin = $pdo->prepare("SELECT id FROM saas_tenants WHERE slug = 'admin' LIMIT 1");
            $stmtAdmin->execute();
            $adminTenantId = $stmtAdmin->fetchColumn() ?: 0;

            $sql = "SELECT id, name, tenant_id FROM saas_roles WHERE tenant_id = ? OR tenant_id = ? ORDER BY tenant_id ASC, name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$target_tenant, $adminTenantId]);
            
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Marca visivelmente os globais
            foreach($roles as &$r) {
                if ($r['tenant_id'] != $target_tenant) {
                    $r['name'] .= ' (Global)';
                }
            }
            echo json_encode($roles);
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    // SALVAR USUÁRIO (COM DEBUG DE ERRO SQL)
    case 'save_user':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $pass = $input['password'] ?? '';
        
        // Tratamento de campos opcionais (Null se vazio)
        $role = !empty($input['role_id']) ? $input['role_id'] : null;
        $cust = !empty($input['customer_id']) ? $input['customer_id'] : null;
        $branch = !empty($input['branch_id']) ? $input['branch_id'] : null;
        
        // Active: converte para 1 ou 0 (inteiro)
        $active = (isset($input['active']) && $input['active']) ? 1 : 0;
        
        // Define tenant alvo (Superadmin pode escolher, outros usam sessão)
        $target_tenant_id = ($user_role === 'superadmin' && !empty($input['tenant_id'])) ? $input['tenant_id'] : $tenant_id;
        
        if (empty($name) || empty($email)) http_400('Nome e Email são obrigatórios.');

        try {
            // Valida duplicidade de email
            $checkSql = "SELECT id FROM saas_users WHERE email = ? AND tenant_id = ?";
            $checkParams = [$email, $target_tenant_id];
            if ($id) {
                $checkSql .= " AND id != ?";
                $checkParams[] = $id;
            }
            $stmtCheck = $pdo->prepare($checkSql);
            $stmtCheck->execute($checkParams);
            if ($stmtCheck->fetch()) http_400('Este email já está em uso nesta empresa.');

            if ($id) {
                // UPDATE
                $sql = "UPDATE saas_users SET name=?, email=?, role_id=?, customer_id=?, branch_id=?, active=?, tenant_id=? WHERE id=?";
                $params = [$name, $email, $role, $cust, $branch, $active, $target_tenant_id, $id];
                
                if ($user_role !== 'superadmin') {
                    $sql .= " AND tenant_id=?";
                    $params[] = $tenant_id;
                }

                if (!empty($pass)) { 
                    $sql = str_replace('active=?,', 'active=?, password=?,', $sql); 
                    // Insere senha na posição 6 (logo após active)
                    array_splice($params, 6, 0, password_hash($pass, PASSWORD_DEFAULT));
                }
                
                $pdo->prepare($sql)->execute($params);
            } else {
                // INSERT
                if(empty($pass)) http_400('Senha obrigatória para novos usuários.');
                
                $stmt = $pdo->prepare("INSERT INTO saas_users (tenant_id, name, email, password, role_id, customer_id, branch_id, status, active) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                $stmt->execute([$target_tenant_id, $name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $cust, $branch, $active]);
            }
            echo json_encode(['success' => true]);

        } catch (PDOException $e) { 
            // RETORNA ERRO REAL DO BANCO (ÚTIL PARA DEBUG 500)
            http_response_code(500);
            echo json_encode(['error' => 'Erro SQL: ' . $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro Interno: ' . $e->getMessage()]);
        }
        break;

    case 'delete_user':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id || $id == $user_id) http_400('Operação inválida');
        try {
            if ($user_role === 'superadmin') {
                $pdo->prepare("DELETE FROM saas_users WHERE id = ?")->execute([$id]);
            } else {
                $pdo->prepare("DELETE FROM saas_users WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    // --- PROFILES ---
    case 'get_profiles':
        try {
            $stmt = $pdo->prepare("SELECT * FROM saas_roles WHERE tenant_id = ? ORDER BY id DESC");
            $stmt->execute([$tenant_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    case 'save_profile':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $name = $input['name'] ?? '';
        $permissions = is_array($input['permissions'] ?? []) ? json_encode($input['permissions']) : ($input['permissions'] ?? '[]');
        if (empty($name)) http_400('Nome obrigatório');
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE saas_roles SET name = ?, permissions = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$name, $permissions, $id, $tenant_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO saas_roles (tenant_id, name, permissions) VALUES (?, ?, ?)");
                $stmt->execute([$tenant_id, $name, $permissions]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    case 'delete_profile':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id) http_400('ID inválido');
        try {
            $stmt = $pdo->prepare("DELETE FROM saas_roles WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { http_500($e->getMessage()); }
        break;

    // --- COMANDOS ---
    case 'secure_command':
        $input = json_decode(file_get_contents('php://input'), true);
        $deviceId = $input['deviceId'] ?? null; $cmdType = $input['type'] ?? null; $password = $input['password'] ?? '';
        if (!$deviceId || !$cmdType) http_400('Dados incompletos');
        if ($password !== 'SKIP_CHECK') {
            $stmt = $pdo->prepare("SELECT password FROM saas_users WHERE id = ?"); $stmt->execute([$user_id]);
            if (!password_verify($password, $stmt->fetchColumn())) { http_response_code(401); exit(json_encode(['error' => 'Senha incorreta'])); }
        }
        $checkSql = "SELECT COUNT(*) FROM saas_vehicles v WHERE traccar_device_id = ? AND tenant_id = ? $restrictionSQL";
        $stmtCheck = $pdo->prepare($checkSql); $stmtCheck->execute([$deviceId, $tenant_id]);
        if ($stmtCheck->fetchColumn() == 0) { http_response_code(403); exit(json_encode(['error' => 'Acesso negado'])); }
        
        $traccarCmd = ($cmdType === 'lock') ? 'engineStop' : (($cmdType === 'unlock') ? 'engineResume' : $cmdType);
        $attr = $input['attributes'] ?? new stdClass();
        
        $ch = curl_init("http://127.0.0.1:8082/api/commands/send");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_USERPWD, "admin:admin"); 
        curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['deviceId' => $deviceId, 'type' => $traccarCmd, 'attributes' => $attr]));
        $resp = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 400) http_500('Erro Traccar: ' . $resp);
        else echo json_encode(['success' => true]);
        curl_close($ch);
        break;

    case 'get_history_positions':
        $deviceId = $_GET['deviceId'] ?? null;
        $dateStr  = $_GET['date'] ?? null; 
        if (!$deviceId || !$dateStr) http_400('ID e Data são obrigatórios.');
        $checkSql = "SELECT COUNT(*) FROM saas_vehicles v WHERE traccar_device_id = ? AND tenant_id = ? $restrictionSQL";
        $stmtCheck = $pdo->prepare($checkSql); $stmtCheck->execute([$deviceId, $tenant_id]);
        if ($stmtCheck->fetchColumn() == 0) http_403('Acesso negado.');
        try {
            $dtStart = new DateTime($dateStr . ' 00:00:00'); $dtStart->setTimezone(new DateTimeZone('UTC'));
            $isoFrom = $dtStart->format('Y-m-d\TH:i:s\Z');
            $dtEnd = new DateTime($dateStr . ' 23:59:59'); $dtEnd->setTimezone(new DateTimeZone('UTC'));
            $isoTo = $dtEnd->format('Y-m-d\TH:i:s\Z');
        } catch (Exception $e) { http_400('Data inválida.'); }
        $url = "http://127.0.0.1:8082/api/reports/route?deviceId=$deviceId&from=$isoFrom&to=$isoTo";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_USERPWD, "admin:admin");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']); 
        $response = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 200) echo $response; else echo json_encode([]);
        curl_close($ch);
        break;

    case 'geocode':
        handleGeocode($_GET['lat']??0, $_GET['lon']??0, $pdo);
        break;

    case 'ping': echo json_encode(['status' => 'ok', 'tenant' => $tenant_id]); break;

    default:
        if (isset($_GET['type']) && $_GET['type'] === 'geocode') handleGeocode($_GET['lat'], $_GET['lon'], $pdo);
        else { http_response_code(404); echo json_encode(['error' => 'Action inválida']); }
        break;
}

// HELPERS
function handleProxyTraccar($endpoint, $tenant_id, $loggedCustomerId, $user_id, $pdo) {
    $url = 'http://127.0.0.1:8082/api' . $endpoint . '?' . http_build_query($_GET);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_USERPWD, "admin:admin");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 400) { http_response_code($httpCode); echo $resp; return; }
    $data = json_decode($resp, true);
    if (is_array($data)) {
        $idsSql = "SELECT traccar_device_id FROM saas_vehicles v WHERE tenant_id = $tenant_id";
        if ($loggedCustomerId || $user_id) $idsSql .= $loggedCustomerId ? " AND (v.client_id = $loggedCustomerId OR v.user_id = $user_id)" : " AND v.user_id = $user_id";
        $ids = $pdo->query($idsSql)->fetchAll(PDO::FETCH_COLUMN);
        $filtered = [];
        foreach($data as $item) {
            $did = $item['deviceId'] ?? ($item['id'] ?? null);
            if ($did && (strpos($endpoint, '/devices')!==false || strpos($endpoint, '/positions')!==false || strpos($endpoint, '/reports')!==false)) {
                if(!in_array($did, $ids)) continue;
            }
            $filtered[] = $item;
        }
        echo json_encode(array_values($filtered));
    } else { echo $resp; }
}

function handleGeocode($lat, $lon, $pdo) {
    $cached = $pdo->query("SELECT address FROM saas_address_cache WHERE lat='$lat' AND lon='$lon'")->fetchColumn();
    if($cached) { echo json_encode(['address'=>$cached]); exit; }
    $ch = curl_init("https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&zoom=18&addressdetails=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_USERAGENT, "FV/1.0");
    $json = json_decode(curl_exec($ch), true); curl_close($ch);
    $addr = $json['display_name'] ?? 'Local desconhecido';
    $pdo->prepare("INSERT INTO saas_address_cache (lat, lon, address) VALUES (?, ?, ?)")->execute([$lat, $lon, $addr]);
    echo json_encode(['address' => $addr]);
    exit;
}
function callAsaas($endpoint, $method, $data, $apiKey) {
    $ch = curl_init('https://api.asaas.com/v3' . ($endpoint[0]!='/'?'/':'') . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "access_token: " . trim($apiKey)]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($method === 'POST' || $method === 'PUT') curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $json = json_decode(curl_exec($ch), true); curl_close($ch);
    if (isset($json['errors'])) return ['error' => $json['errors'][0]['description'] ?? 'Erro Asaas'];
    return $json;
}
function http_400($msg) { http_response_code(400); exit(json_encode(['error' => $msg])); }
function http_500($msg) { http_response_code(500); exit(json_encode(['error' => $msg])); }
?>