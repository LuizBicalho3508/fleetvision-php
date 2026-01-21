<?php
// ARQUIVO: api_financeiro.php
session_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// 1. Segurança Básica
if (!isset($_SESSION['user_id'])) { 
    http_response_code(403); 
    exit(json_encode(['error' => 'Sessão expirada.'])); 
}

if (!file_exists('db.php')) {
    http_response_code(500);
    exit(json_encode(['error' => 'Erro crítico: db.php não encontrado.']));
}
require 'db.php';

// 2. Contexto
$tenant_id  = $_SESSION['tenant_id'];
$user_role  = $_SESSION['user_role'] ?? 'user';
$action     = $_REQUEST['action'] ?? '';

// 3. Roteador
switch ($action) {

    // Salvar Chave de API
    case 'save_config':
        if ($user_role != 'admin' && $user_role != 'superadmin') {
            http_response_code(403); 
            exit(json_encode(['error' => 'Permissão negada.']));
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['apiKey'] ?? '';
        
        // Testa a chave antes de salvar
        $test = callAsaas('/finance/balance', 'GET', [], $token);
        if (isset($test['errors'])) {
            http_response_code(400); 
            exit(json_encode(['error' => 'Chave API Inválida.']));
        }

        try {
            $stmt = $pdo->prepare("UPDATE saas_tenants SET asaas_token = ? WHERE id = ?");
            $stmt->execute([$token, $tenant_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    // Verificar se existe configuração
    case 'get_config':
        try {
            $stmt = $pdo->prepare("SELECT asaas_token FROM saas_tenants WHERE id = ?");
            $stmt->execute([$tenant_id]);
            $token = $stmt->fetchColumn();
            echo json_encode(['has_token' => !empty($token)]);
        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    // Proxy para requisições Asaas
    case 'proxy':
        try {
            $stmt = $pdo->prepare("SELECT asaas_token FROM saas_tenants WHERE id = ?");
            $stmt->execute([$tenant_id]);
            $apiToken = $stmt->fetchColumn();

            if (empty($apiToken)) {
                http_response_code(400); 
                exit(json_encode(['error' => 'Configure a Chave API do Asaas.']));
            }

            $asaas_endpoint = $_REQUEST['asaas_endpoint'] ?? '';
            $method = $_SERVER['REQUEST_METHOD'];
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            // Repassa parâmetros GET
            if ($method === 'GET' && !empty($_GET)) {
                $query = $_GET; 
                unset($query['action'], $query['asaas_endpoint']); 
                
                $separator = strpos($asaas_endpoint, '?') === false ? '?' : '&';
                $asaas_endpoint .= $separator . http_build_query($query);
            }

            $response = callAsaas($asaas_endpoint, $method, $data, $apiToken);
            echo json_encode($response);

        } catch (Exception $e) { 
            http_response_code(500); 
            echo json_encode(['error' => $e->getMessage()]); 
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action inválida em api_financeiro.php']);
        break;
}

// Helper Function
function callAsaas($endpoint, $method, $data, $apiKey) {
    // URL Base do Asaas (Mude para sandbox.asaas.com se for teste)
    $baseUrl = 'https://api.asaas.com/v3'; 
    $url = $baseUrl . ($endpoint[0] != '/' ? '/' : '') . $endpoint;

    $ch = curl_init($url);
    
    $headers = [
        "Content-Type: application/json",
        "access_token: " . trim($apiKey)
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    // Tratamento de erros do Asaas
    if ($httpCode >= 400) {
        $errorMsg = $json['errors'][0]['description'] ?? 'Erro desconhecido no Asaas';
        return ['error' => $errorMsg, 'code' => $httpCode];
    }

    return $json;
}
?>