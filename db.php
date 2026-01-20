<?php
// db.php - Centralizador de Conexão com o Banco de Dados

// Configurações do Banco
$dbHost = '127.0.0.1';
$dbPort = '5432';
$dbName = 'traccar';
$dbUser = 'traccar';
$dbPass = 'traccar';

try {
    // Cria a conexão PDO
    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    
    // Configura para lançar exceções em caso de erro (importante para try/catch funcionar)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Define o modo de fetch padrão para array associativo
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Se der erro, para tudo e avisa (em JSON se for API, ou texto puro)
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Erro crítico de conexão com o banco de dados.']);
    exit;
}
?>