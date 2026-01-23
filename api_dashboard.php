<?php
// ARQUIVO: api_dashboard.php
require_once 'db.php';
require_once 'utils/Security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); exit(json_encode(['error' => 'Sessão expirada']));
}

$tenant_id = $_SESSION['tenant_id'];
$user_role = $_SESSION['user_role'] ?? 'user';
$user_id   = $_SESSION['user_id'];

// Restrição para cliente final (se necessário)
$restriction = "";
if ($user_role !== 'admin' && $user_role !== 'superadmin') {
    $stmtC = $pdo->prepare("SELECT customer_id FROM saas_users WHERE id = ?");
    $stmtC->execute([$user_id]);
    $custId = $stmtC->fetchColumn();
    if ($custId) {
        $restriction = " AND v.client_id = $custId "; // Assumindo coluna client_id no saas_vehicles
    } else {
        $restriction = " AND v.user_id = $user_id ";
    }
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_stats':
            // 1. Contagens Gerais
            $sqlTotal = "SELECT COUNT(*) FROM saas_vehicles v WHERE v.tenant_id = ? AND v.status = 'active' $restriction";
            $stmt = $pdo->prepare($sqlTotal); $stmt->execute([$tenant_id]);
            $totalVehicles = $stmt->fetchColumn();

            $totalClients = 0;
            if ($user_role === 'admin' || $user_role === 'superadmin') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM saas_customers WHERE tenant_id = ?");
                $stmt->execute([$tenant_id]);
                $totalClients = $stmt->fetchColumn();
            }

            // 2. Online vs Offline (Baseado no Traccar)
            // Query Otimizada com JOIN
            $sqlOnline = "
                SELECT COUNT(v.id) 
                FROM saas_vehicles v 
                JOIN tc_devices d ON v.traccar_device_id = d.id 
                WHERE v.tenant_id = ? AND v.status = 'active' 
                AND d.lastupdate > NOW() - INTERVAL '24 hours' 
                $restriction
            ";
            $stmt = $pdo->prepare($sqlOnline); $stmt->execute([$tenant_id]);
            $online = $stmt->fetchColumn();
            $offline = $totalVehicles - $online;
            if ($offline < 0) $offline = 0;

            // 3. Faturamento (Apenas Admin)
            $revenue = 0;
            if ($user_role === 'admin' || $user_role === 'superadmin') {
                $stmt = $pdo->prepare("SELECT SUM(monthly_fee) FROM saas_customers WHERE tenant_id = ? AND financial_status != 'overdue'");
                $stmt->execute([$tenant_id]);
                $revenue = $stmt->fetchColumn() ?: 0;
            }

            echo json_encode([
                'success' => true,
                'total_vehicles' => $totalVehicles,
                'total_clients' => $totalClients,
                'online_24h' => $online,
                'offline_24h' => $offline,
                'revenue' => (float)$revenue
            ]);
            break;

        case 'get_growth':
            // Gráfico de crescimento (Últimos 6 meses)
            $sql = "
                SELECT TO_CHAR(created_at, 'YYYY-MM') as mes, COUNT(*) as qtd
                FROM saas_vehicles v
                WHERE v.tenant_id = ? AND v.created_at >= NOW() - INTERVAL '6 months' $restriction
                GROUP BY 1 ORDER BY 1 ASC
            ";
            $stmt = $pdo->prepare($sql); $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_KEY_PAIR)]);
            break;

        case 'get_top_clients':
            // Apenas Admin
            if ($user_role !== 'admin' && $user_role !== 'superadmin') {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            $sql = "
                SELECT c.name, COUNT(v.id) as total
                FROM saas_customers c
                JOIN saas_vehicles v ON c.id = v.client_id
                WHERE c.tenant_id = ? AND v.status = 'active'
                GROUP BY c.id, c.name
                ORDER BY total DESC
                LIMIT 5
            ";
            $stmt = $pdo->prepare($sql); $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        default:
            throw new Exception("Ação desconhecida");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}