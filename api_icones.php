<?php
// ARQUIVO: api_icones.php
require_once 'db.php';
require_once 'utils/Security.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) exit;

$tenant_id = $_SESSION['tenant_id'];
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM saas_custom_icons WHERE tenant_id = ? OR tenant_id IS NULL");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    elseif ($action === 'upload') {
        if (!isset($_FILES['icon'])) throw new Exception("Arquivo não enviado");
        
        $file = $_FILES['icon'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if(!in_array($ext, ['png', 'jpg', 'svg'])) throw new Exception("Apenas PNG, JPG ou SVG");

        $name = $_POST['name'] ?? 'Icone';
        $filename = "icon_" . uniqid() . ".$ext";
        $path = "uploads/icons/$filename";
        
        if (!is_dir('uploads/icons')) mkdir('uploads/icons', 0755, true);
        
        if (move_uploaded_file($file['tmp_name'], $path)) {
            $pdo->prepare("INSERT INTO saas_custom_icons (tenant_id, name, url, category) VALUES (?, ?, ?, 'custom')")
                ->execute([$tenant_id, $name, $path]);
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Erro ao salvar arquivo");
        }
    }
    elseif ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'];
        
        $stmt = $pdo->prepare("SELECT url FROM saas_custom_icons WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $icon = $stmt->fetch();
        
        if ($icon) {
            if(file_exists($icon['url'])) unlink($icon['url']);
            $pdo->prepare("DELETE FROM saas_custom_icons WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Ícone não encontrado ou padrão do sistema.");
        }
    }
} catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }