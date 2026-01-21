<?php
// ARQUIVO: index.php
// --- CONFIGURAÇÃO E SESSÃO ---
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 1. CONEXÃO CENTRALIZADA
require 'db.php';

// 2. ROTEAMENTO
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); 
$parts = explode('/', trim($path, '/'));
$slug = (isset($parts[0]) && !empty($parts[0]) && $parts[0] != 'api') ? $parts[0] : 'admin';
$page = (isset($parts[1]) && !empty($parts[1])) ? $parts[1] : 'dashboard';

// 3. BUSCA TENANT
$stmt = $pdo->prepare("SELECT * FROM saas_tenants WHERE slug = :slug LIMIT 1"); 
$stmt->execute(['slug' => $slug]); 
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) { 
    $stmt->execute(['slug' => 'admin']); 
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC); 
    $slug = 'admin'; 
}
$_SESSION['tenant_id'] = $tenant['id'];

// 4. AUTH CHECK
if ($page == 'logout') { session_destroy(); header("Location: /$slug/login"); exit; }
if (!isset($_SESSION['user_id']) && $page != 'login') { header("Location: /$slug/login"); exit; }
if ($page == 'login') { require 'login.php'; exit; }

// --- LÓGICA DE PERFIL E PERMISSÕES ---

// Busca usuário e SUAS PERMISSÕES (Join com saas_roles)
$stmtUser = $pdo->prepare("
    SELECT u.*, r.permissions 
    FROM saas_users u 
    LEFT JOIN saas_roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmtUser->execute([$_SESSION['user_id']]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) { session_destroy(); header("Location: /$slug/login"); exit; }

$userAvatar = $currentUser['avatar_url'] ?? null;
$userInitials = strtoupper(substr($currentUser['name'], 0, 2));
$role = $_SESSION['user_role'] ?? 'user';

// Decodifica permissões do JSON
$userPermissions = [];
if (!empty($currentUser['permissions'])) {
    $decoded = json_decode($currentUser['permissions'], true);
    if (is_array($decoded)) $userPermissions = $decoded;
}

// FUNÇÃO GLOBAL PARA VERIFICAR PERMISSÃO
function hasPermission($perm) {
    global $role, $userPermissions;
    // Superadmin e Admin geralmente têm acesso total (ou remova 'admin' se quiser que ele também siga o perfil)
    if ($role === 'superadmin') return true; 
    
    // Se o usuário não tem perfil definido, bloqueia tudo (exceto dashboard se quiser liberar)
    if (empty($userPermissions)) return false;

    return in_array($perm, $userPermissions);
}

// --- FIM LÓGICA DE PERMISSÕES ---

$stmtB = $pdo->prepare("SELECT * FROM saas_branches WHERE tenant_id = ? ORDER BY id ASC"); 
$stmtB->execute([$tenant['id']]); 
$dbBranches = $stmtB->fetchAll(PDO::FETCH_ASSOC);
if(empty($dbBranches)) $dbBranches = [['id' => 0, 'name' => 'Matriz']];

// Lógica de update de avatar (mantida do seu código original)
$msgSuccess = ''; $msgError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $uid = $_SESSION['user_id']; $newName = $_POST['name']; $newPass = $_POST['password']; $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                if (!is_dir('uploads/avatars')) mkdir('uploads/avatars', 0777, true);
                $fileName = "avatar_{$uid}_" . time() . ".{$ext}";
                $target = "uploads/avatars/$fileName";
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) $avatarPath = $target;
            }
        }
        $sql = "UPDATE saas_users SET name = ?"; $params = [$newName];
        if (!empty($newPass)) { $sql .= ", password = ?"; $params[] = password_hash($newPass, PASSWORD_DEFAULT); }
        if ($avatarPath) { $sql .= ", avatar_url = ?"; $params[] = $avatarPath; }
        $sql .= " WHERE id = ?"; $params[] = $uid;
        $pdo->prepare($sql)->execute($params);
        $_SESSION['user_name'] = $newName;
        $msgSuccess = "Perfil atualizado com sucesso!";
        // Atualiza objeto local
        $currentUser['name'] = $newName; if($avatarPath) $userAvatar = $avatarPath;
    } catch (Exception $e) { $msgError = "Erro ao atualizar: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tenant['name'] ?? 'FleetVision'); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="/style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php if(!empty($tenant['logo_url'])): ?><link rel="icon" href="<?php echo $tenant['logo_url']; ?>" type="image/x-icon"><?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        :root { 
            --primary: <?php echo $tenant['primary_color'] ?? '#3b82f6'; ?>; 
            --secondary: <?php echo $tenant['secondary_color'] ?? '#1e293b'; ?>;
            --bg-page: #f8fafc;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans flex h-screen overflow-hidden">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-screen overflow-hidden relative transition-all duration-300">
        
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-30 border-b border-gray-200 flex-shrink-0">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="w-10 h-10 flex items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition focus:outline-none">
                    <i class="fas fa-bars text-lg"></i>
                </button>

                <h2 class="text-lg font-bold text-slate-700 capitalize flex items-center gap-2">
                    <?php echo ucfirst(str_replace('_', ' ', $page)); ?> 
                </h2>
                
                <div class="hidden md:flex items-center text-xs font-medium text-slate-500 bg-slate-50 px-3 py-1.5 rounded-full border border-slate-200">
                    <i class="fas fa-building mr-1.5 text-slate-400"></i> <?php echo htmlspecialchars($dbBranches[0]['name']); ?>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button onclick="toggleFullScreen()" class="w-9 h-9 flex items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition" title="Tela Cheia">
                    <i class="fas fa-expand"></i>
                </button>

                <div class="relative group" id="user-menu-container">
                    <button onclick="toggleUserMenu()" class="flex items-center gap-3 pl-1 pr-2 py-1 rounded-full hover:bg-slate-50 transition border border-transparent hover:border-slate-100">
                        <?php if($userAvatar): ?>
                            <img src="/<?php echo $userAvatar; ?>" class="w-9 h-9 rounded-full object-cover border border-slate-200 shadow-sm">
                        <?php else: ?>
                            <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-bold border border-indigo-200">
                                <?php echo $userInitials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="hidden md:block text-left">
                            <div class="text-sm font-bold text-slate-700 leading-tight"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                            <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider leading-tight"><?php echo $role; ?></div>
                        </div>
                        <i class="fas fa-chevron-down text-slate-300 text-xs ml-1"></i>
                    </button>

                    <div id="user-dropdown" class="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden hidden transform origin-top-right transition-all z-50">
                        <div class="p-4 border-b border-gray-50 bg-gray-50/50">
                            <p class="text-xs text-gray-500">Logado como</p>
                            <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                        </div>
                        <div class="p-2">
                            <button onclick="openProfileModal()" class="w-full text-left px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 flex items-center gap-2 transition">
                                <i class="far fa-user-circle"></i> Meu Perfil
                            </button>
                            <a href="/<?php echo $slug; ?>/logout" class="w-full text-left px-3 py-2 rounded-lg text-sm text-red-500 hover:bg-red-50 flex items-center gap-2 transition">
                                <i class="fas fa-sign-out-alt"></i> Sair do Sistema
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 relative p-0 transition-all duration-300">
            <?php 
                $file = $page . '.php'; 
                
                if ($page == 'dashboard') {
                    if ($role == 'superadmin') $file = 'dashboard_super.php';
                    elseif ($role == 'admin') $file = 'dashboard_tenant.php';
                    else $file = 'dashboard_client.php';
                }
                
                // Mapeamento
                $routes = [
                    'estoque' => 'admin_estoque.php',
                    'teste' => 'admin_teste.php',
                    'icones' => 'admin_icones.php',
                    'crm' => 'admin_crm.php',
                    'gestao' => 'admin_gestao.php',
                    'tenant_users' => 'admin_usuarios_tenant.php',
                    'admin_server' => 'admin_server.php',
                    'api_docs' => 'api_docs.php',
                    'historico' => 'historico.php',
                    'perfis' => 'perfis.php', 
                    'frota' => 'frota.php',
                    'clientes' => 'clientes.php',
                    'cercas' => 'cercas.php',
                    'alertas' => 'alertas.php',
                    'motoristas' => 'motoristas.php',
                    'jornada' => 'jornada.php',
                    'ranking_motoristas' => 'ranking_motoristas.php',
                    'financeiro' => 'financeiro.php',
                    'relatorios' => 'relatorios.php',
                    'usuarios' => 'usuarios.php',
                    'filiais' => 'filiais.php',
                    'mapa' => 'mapa.php'
                ];

                if (array_key_exists($page, $routes)) {
                    // Verifica permissão da página antes de carregar!
                    $allowed = true;
                    // Mapeamento Página -> Permissão Obrigatória
                    $pagePerms = [
                        'mapa' => 'map_view',
                        'historico' => 'map_history',
                        'alertas' => 'alerts_view',
                        'frota' => 'vehicles_view',
                        'motoristas' => 'drivers_view',
                        'cercas' => 'geofences_view',
                        'jornada' => 'journal_view',
                        'ranking_motoristas' => 'ranking_view',
                        'clientes' => 'customers_view',
                        'financeiro' => 'financial_view',
                        'relatorios' => 'reports_view',
                        'usuarios' => 'users_view',
                        'perfis' => 'roles_manage',
                        'filiais' => 'branches_manage',
                        'estoque' => 'tech_stock',
                        'teste' => 'tech_test',
                        'icones' => 'tech_config'
                    ];

                    if (isset($pagePerms[$page]) && !hasPermission($pagePerms[$page])) {
                        $allowed = false;
                        echo "<div class='h-full flex flex-col items-center justify-center text-slate-400'>
                                <i class='fas fa-lock text-5xl mb-4 text-red-300'></i>
                                <h2 class='text-2xl font-bold text-slate-600'>Acesso Negado</h2>
                                <p>Você não tem permissão para acessar esta página.</p>
                              </div>";
                    } else {
                        $file = $routes[$page];
                    }
                }

                if (isset($allowed) && $allowed === false) {
                    // Já exibiu mensagem de erro
                } elseif (file_exists($file)) {
                    try { require $file; } 
                    catch (Throwable $e) { echo "<div class='p-8 text-red-500'>Erro: ".$e->getMessage()."</div>"; }
                } else {
                    echo "<div class='p-10 text-center text-gray-400'>Página não encontrada: $file</div>";
                }
            ?>
        </main>
    </div>
    
    <div id="modal-profile" class="fixed inset-0 bg-black/60 hidden z-[9999] flex items-center justify-center backdrop-blur-sm p-4">
       <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="bg-white px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="font-bold text-lg text-slate-800">Editar Perfil</h3>
                <button onclick="document.getElementById('modal-profile').classList.add('hidden')" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="action" value="update_profile">
                <div class="flex flex-col items-center mb-6">
                    <div class="relative group cursor-pointer" onclick="document.getElementById('profile-upload').click()">
                        <?php if($userAvatar): ?>
                            <img src="/<?php echo $userAvatar; ?>" id="preview-avatar" class="w-24 h-24 rounded-full object-cover border-4 border-slate-100 shadow-md">
                        <?php else: ?>
                            <div id="preview-avatar-div" class="w-24 h-24 rounded-full bg-slate-100 flex items-center justify-center text-slate-300 text-3xl font-bold border-4 border-white shadow-md">
                                <?php echo $userInitials; ?>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition bg-black/20 rounded-full">
                            <i class="fas fa-camera text-white text-2xl drop-shadow-md"></i>
                        </div>
                    </div>
                    <input type="file" name="avatar" id="profile-upload" class="hidden" accept="image/*" onchange="previewImage(this)">
                </div>
                <div class="space-y-4">
                    <div><label class="block text-xs font-bold text-slate-500 mb-1">Nome Completo</label><input type="text" name="name" class="input-std" value="<?php echo htmlspecialchars($currentUser['name']); ?>" required></div>
                    <div><label class="block text-xs font-bold text-slate-500 mb-1">Nova Senha</label><input type="password" name="password" class="input-std" placeholder="Deixe em branco para manter"></div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modal-profile').classList.add('hidden')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-6">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-container"></div>

    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('main-sidebar');
        function toggleSidebar() {
            const isClosed = sidebar.classList.contains('w-0');
            if (isClosed) {
                sidebar.classList.remove('w-0'); sidebar.classList.add('w-64');
                localStorage.setItem('sidebar_state', 'open');
            } else {
                sidebar.classList.remove('w-64'); sidebar.classList.add('w-0');
                localStorage.setItem('sidebar_state', 'closed');
            }
            setTimeout(() => { if (typeof map !== 'undefined') map.invalidateSize(); }, 300);
        }
        document.addEventListener('DOMContentLoaded', () => {
            const state = localStorage.getItem('sidebar_state');
            if (state === 'closed') { sidebar.classList.remove('w-64'); sidebar.classList.add('w-0'); }
        });

        // Utils
        function toggleUserMenu() { document.getElementById('user-dropdown').classList.toggle('hidden'); }
        window.onclick = function(event) { if (!event.target.closest('#user-menu-container')) { document.getElementById('user-dropdown').classList.add('hidden'); } }
        function openProfileModal() { document.getElementById('modal-profile').classList.remove('hidden'); document.getElementById('user-dropdown').classList.add('hidden'); }
        function previewImage(input) { if (input.files && input.files[0]) { const reader = new FileReader(); reader.onload = function(e) { const img = document.getElementById('preview-avatar'); if(img) img.src = e.target.result; else location.reload(); }; reader.readAsDataURL(input.files[0]); } }
        function toggleFullScreen() { if (!document.fullscreenElement) document.documentElement.requestFullscreen(); else if (document.exitFullscreen) document.exitFullscreen(); }
        function showToast(msg, type='blue') { const c = document.getElementById('toast-container'); const d = document.createElement('div'); let colorClass = type === 'success' ? 'border-green-500' : (type === 'error' ? 'border-red-500' : 'border-blue-500'); d.className = `toast ${colorClass}`; d.innerHTML = msg; c.appendChild(d); setTimeout(()=>d.remove(), 5000); }
        
        <?php if($msgSuccess): ?>showToast("<?php echo $msgSuccess; ?>", "success");<?php endif; ?>
        <?php if($msgError): ?>showToast("<?php echo $msgError; ?>", "error");<?php endif; ?>
    </script>
</body>
</html>