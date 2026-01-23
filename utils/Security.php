<?php
// ARQUIVO: utils/Security.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Security {
    
    // Gera um token CSRF e o armazena na sessão
    public static function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Verifica se o token enviado é válido
    public static function checkCsrfToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die(json_encode(['error' => 'Ação não autorizada (Token CSRF Inválido). Recarregue a página.']));
        }
        return true;
    }

    // Input do HTML para formulários
    public static function csrfInput() {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}