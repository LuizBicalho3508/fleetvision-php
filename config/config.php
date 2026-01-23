<?php
// ARQUIVO: config/config.php
// Configurações centralizadas do sistema

// Define o fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Configurações de Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'traccar'); // ALTERE AQUI
define('DB_USER', 'traccar');  // ALTERE AQUI
define('DB_PASS', 'traccar');    // ALTERE AQUI
define('DB_CHARSET', 'utf8mb4');

// Configurações da API Traccar
define('TRACCAR_URL', 'http://seuservidor:8082/api'); // Sem a barra no final
define('TRACCAR_USER', 'admin'); // Recomendo usar um usuário de serviço, não o admin principal se possível
define('TRACCAR_PASS', 'admin');

// Configurações de Segurança
define('APP_KEY', 'gerar_uma_hash_aleatoria_segura_aqui_32_chars'); // Usado para criptografia se necessário
define('DEBUG_MODE', true); // Mude para false em produção

// Definições de Caminhos (Paths)
define('ROOT_PATH', dirname(__DIR__));