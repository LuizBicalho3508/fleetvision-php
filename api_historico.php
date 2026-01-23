<?php
// ARQUIVO: api_historico.php
require 'api_core.php';

$endpoint = $_REQUEST['endpoint'] ?? '';

// Só aceita endpoints de relatório de rotas
if ($endpoint === '/reports/route' || $endpoint === '/reports/summary' || $endpoint === '/reports/trips') {
    // O fetchTraccarProxy já cuida de pegar os parametros GET da URL
    $data = fetchTraccarProxy($endpoint);
    echo json_encode($data);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Endpoint inválido para histórico']);
}
?>