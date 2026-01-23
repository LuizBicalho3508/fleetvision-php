<?php
// ARQUIVO: api_comandos.php
require_once 'db.php';
require_once 'utils/Security.php';
require_once 'TraccarApi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); exit(json_encode(['error' => 'Sessão expirada']));
}

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$action    = $_GET['action'] ?? '';
$input     = json_decode(file_get_contents('php://input'), true) ?? [];

$traccar = new TraccarApi();

try {
    switch ($action) {
        case 'send':
            // 1. Validação de Dados
            $vehicleId = $input['vehicle_id'] ?? null;
            $type = $input['type'] ?? null; // engineStop, engineResume
            $password = $input['password'] ?? '';

            if (!$vehicleId || !$type || !$password) throw new Exception("Dados incompletos.");

            // 2. Validação de Segurança (Senha do Usuário)
            $stmtUser = $pdo->prepare("SELECT password FROM saas_users WHERE id = ?");
            $stmtUser->execute([$user_id]);
            $hash = $stmtUser->fetchColumn();

            if (!password_verify($password, $hash)) {
                throw new Exception("Senha de confirmação incorreta.");
            }

            // 3. Busca Dados do Veículo
            $stmtV = $pdo->prepare("SELECT traccar_device_id, name, plate FROM saas_vehicles WHERE id = ? AND tenant_id = ?");
            $stmtV->execute([$vehicleId, $tenant_id]);
            $vehicle = $stmtV->fetch();

            if (!$vehicle || !$vehicle['traccar_device_id']) throw new Exception("Veículo não encontrado ou sem rastreador.");

            // 4. Envia para o Traccar
            $response = $traccar->sendCommand($vehicle['traccar_device_id'], $type);

            if (isset($response['error']) && $response['error']) {
                throw new Exception("Erro Traccar: " . ($response['message'] ?? 'Falha desconhecida'));
            }

            // 5. Auditoria (Log)
            $stmtLog = $pdo->prepare("INSERT INTO saas_command_logs (tenant_id, user_id, vehicle_id, command_type, status, ip_address, created_at) VALUES (?, ?, ?, ?, 'sent', ?, NOW())");
            $stmtLog->execute([$tenant_id, $user_id, $vehicleId, $type, $_SERVER['REMOTE_ADDR']]);

            echo json_encode(['success' => true, 'message' => 'Comando enviado com sucesso!']);
            break;

        case 'history':
            // Histórico de comandos
            $limit = 20;
            $sql = "
                SELECT l.*, u.name as user_name, v.name as vehicle_name, v.plate 
                FROM saas_command_logs l
                JOIN saas_users u ON l.user_id = u.id
                JOIN saas_vehicles v ON l.vehicle_id = v.id
                WHERE l.tenant_id = ?
                ORDER BY l.created_at DESC LIMIT $limit
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            
            // Traduz tipos
            $data = $stmt->fetchAll();
            foreach($data as &$row) {
                if($row['command_type'] == 'engineStop') $row['type_label'] = 'Bloqueio';
                elseif($row['command_type'] == 'engineResume') $row['type_label'] = 'Desbloqueio';
                else $row['type_label'] = $row['command_type'];
                
                $row['date_fmt'] = date('d/m H:i', strtotime($row['created_at']));
            }

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default: throw new Exception("Ação inválida");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}