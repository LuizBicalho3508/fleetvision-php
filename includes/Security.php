<?php
// ARQUIVO: includes/Security.php

class Security {
    
    /**
     * Verifica se o usuário (ou seu cliente pai) tem pendências financeiras.
     * Retorna TRUE se estiver bloqueado.
     */
    public static function isFinanciallyBlocked(PDO $pdo, int $userId, int $tenantId): bool {
        // Busca se o usuário está atrelado a um cliente e o status desse cliente
        $sql = "
            SELECT c.financial_status 
            FROM saas_users u
            JOIN saas_customers c ON u.customer_id = c.id
            WHERE u.id = ? AND u.tenant_id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $tenantId]);
        $status = $stmt->fetchColumn();

        // Se status for 'overdue', retorna true (bloqueado)
        return $status === 'overdue';
    }

    /**
     * Verifica permissão baseada no JSON de roles
     */
    public static function hasPermission(PDO $pdo, int $userId, string $permission): bool {
        // Superadmin bypass
        if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 1 || $_SESSION['user_role'] === 'superadmin')) {
            return true;
        }

        $stmt = $pdo->prepare("
            SELECT r.permissions 
            FROM saas_users u 
            JOIN saas_roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $json = $stmt->fetchColumn();

        if (!$json) return false;

        $perms = json_decode($json, true);
        return is_array($perms) && in_array($permission, $perms);
    }

    /**
     * Middleware para rodar no topo de páginas protegidas
     */
    public static function protect(PDO $pdo) {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // 1. Verifica Sessão
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
            self::redirectLogin();
        }

        // 2. Verifica Bloqueio Financeiro em Tempo Real (A cada page load)
        // Isso garante que se o webhook do Asaas bater agora, o usuário cai na próxima troca de página
        if (self::isFinanciallyBlocked($pdo, $_SESSION['user_id'], $_SESSION['tenant_id'])) {
            // Opcional: Destruir sessão
            session_destroy();
            header("Location: /bloqueio_financeiro.php"); // Crie esta página simples
            exit;
        }
    }

    private static function redirectLogin() {
        $slug = $_SESSION['tenant_slug'] ?? 'admin';
        header("Location: /$slug/login");
        exit;
    }
}
?>