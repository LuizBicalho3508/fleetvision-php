<?php
// ARQUIVO: api_core.php
// Centraliza Segurança, Conexão e Proxy Traccar

session_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// 1. Verificação de Sessão
if (!isset($_SESSION['user_id'])) { 
    http_response_code(403); 
    exit(json_encode(['error' => 'Sessão expirada. Faça login novamente.'])); 
}

if (!file_exists('db.php')) {
    http_response_code(500); 
    exit(json_encode(['error' => 'Erro crítico: db.php não encontrado.']));
}
require 'db.php';

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];

// 2. NORMALIZAÇÃO DE PERMISSÃO (CORREÇÃO DE "ADMIN WHITELABEL")
// Esta lógica garante que o sistema entenda quem é admin
$user_role = $_SESSION['user_role'] ?? 'user';

if (is_numeric($user_role)) {
    // Se vier ID numérico, busca o nome real no banco
    $stmtRole = $pdo->prepare("SELECT name FROM saas_roles WHERE id = ?");
    $stmtRole->execute([$user_role]);
    $roleName = $stmtRole->fetchColumn();
    if ($roleName) $user_role = $roleName;
}

$user_role = strtolower(trim($user_role));
$adminAliases = ['admin', 'admin whitelabel', 'administrador', 'gerente geral', 'superadmin'];

// Define se é restrito ou não
global $isRestricted, $linkedCustomerId;
$isRestricted = !in_array($user_role, $adminAliases);
$linkedCustomerId = null;

// Se for restrito, busca o cliente vinculado
if ($isRestricted) {
    $stmtMe = $pdo->prepare("SELECT customer_id FROM saas_users WHERE id = ?");
    $stmtMe->execute([$user_id]);
    $linkedCustomerId = $stmtMe->fetchColumn();
}

// 3. Função Proxy Traccar (Para Histórico, Cercas, etc.)
function fetchTraccarProxy($endpoint) {
    global $TRACCAR_HOST;
    $TRACCAR_HOST = 'http://127.0.0.1:8082/api';
    $cookie = sys_get_temp_dir() . '/traccar_cookie_global.txt';
    
    // Autenticação no Traccar
    if (!file_exists($cookie) || (time() - filemtime($cookie) > 1800)) {
        $ch = curl_init("$TRACCAR_HOST/session"); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, "email=admin&password=admin"); // Configure suas credenciais aqui
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_exec($ch); curl_close($ch);
    }

    $url = $TRACCAR_HOST . $endpoint;
    
    // Repassa Query Params (GET)
    if (!empty($_GET)) {
        $query = $_GET; 
        // Limpa params internos para não quebrar a URL do Traccar
        unset($query['endpoint'], $query['action'], $query['type']);
        if ($query) $url .= (strpos($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $ch = curl_init($url); 
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    // Repassa Método e Body (POST/PUT/DELETE)
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $input = file_get_contents('php://input');
        if ($input) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $res = curl_exec($ch); 
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    curl_close($ch);

    // Retorna array ou erro
    if ($code >= 200 && $code < 300) {
        return json_decode($res, true);
    } else {
        return ['error' => 'Traccar Error', 'code' => $code, 'details' => $res];
    }
}
?>