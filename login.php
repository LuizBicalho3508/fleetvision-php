<?php
// ARQUIVO: login.php
// Responsável pelo login web com interface visual e bloqueio financeiro

// NOTA: session_start() removido pois index.php já iniciou a sessão.

// Garante conexão com banco apenas se não vier do index
if (!isset($pdo)) {
    require 'db.php';
}

// Se já estiver logado, redireciona
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['tenant_slug'])) {
        header("Location: /" . $_SESSION['tenant_slug'] . "/dashboard");
    } else {
        header("Location: /dashboard");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        try {
            // Busca usuário e tenant
            $stmt = $pdo->prepare("
                SELECT u.*, t.slug as tenant_slug 
                FROM saas_users u
                JOIN saas_tenants t ON u.tenant_id = t.id
                WHERE u.email = ? 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                
                // Verifica inatividade manual do usuário
                $isActive = $user['active'] == 1 || $user['active'] === true || $user['active'] === 't';
                
                if (!$isActive) {
                    $error = 'Sua conta foi desativada pelo administrador.';
                } 
                else {
                    // =========================================================
                    // 🛑 PORTEIRO FINANCEIRO (LÓGICA DE BLOQUEIO)
                    // =========================================================
                    $bloqueadoFinanceiro = false;

                    // Verifica se não é admin e se pertence a um cliente
                    if (!empty($user['customer_id'])) {
                        $stmtFin = $pdo->prepare("SELECT financial_status FROM saas_customers WHERE id = ?");
                        $stmtFin->execute([$user['customer_id']]);
                        $status = $stmtFin->fetchColumn();

                        if ($status === 'overdue') {
                            $bloqueadoFinanceiro = true;
                        }
                    }

                    if ($bloqueadoFinanceiro) {
                        $error = 'ACESSO SUSPENSO. Consta uma pendência financeira em seu contrato.';
                    } 
                    // =========================================================
                    // ✅ LOGIN PERMITIDO
                    // =========================================================
                    else {
                        // Regenera ID da sessão para segurança
                        session_regenerate_id(true);

                        $_SESSION['user_id']    = $user['id'];
                        $_SESSION['user_name']  = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['tenant_id']  = $user['tenant_id'];
                        $_SESSION['tenant_slug']= $user['tenant_slug'];
                        $_SESSION['user_role']  = $user['role_id']; 
                        
                        // Redirecionamento
                        header("Location: /" . $user['tenant_slug'] . "/dashboard");
                        exit;
                    }
                }
            } else {
                $error = 'E-mail ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $error = 'Erro no sistema. Tente novamente mais tarde.';
        }
    }
}

// === CONFIGURAÇÃO VISUAL (Trazida do tenant) ===
// Se $tenant não estiver definido (acesso direto sem index), define padrão
if (!isset($tenant)) $tenant = [];

$bgImage = !empty($tenant['login_bg_url']) ? $tenant['login_bg_url'] : 'https://images.unsplash.com/photo-1494548162494-384bba4ab999?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80';
$btnColor = !empty($tenant['login_btn_color']) ? $tenant['login_btn_color'] : ($tenant['primary_color'] ?? '#3b82f6');
$cardBgHex = !empty($tenant['login_card_bg']) ? $tenant['login_card_bg'] : '#ffffff';
$textColor = !empty($tenant['login_text_color']) ? $tenant['login_text_color'] : '#374151';
$opacity = !empty($tenant['login_card_opacity']) ? ($tenant['login_card_opacity'] / 100) : 0.95;

// Converter Hex do Fundo para RGB
$r = 255; $g = 255; $b = 255;
if(sscanf($cardBgHex, "#%02x%02x%02x", $r, $g, $b) !== 3) { $r=255; $g=255; $b=255; }
$rgbaCard = "rgba($r, $g, $b, $opacity)";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo htmlspecialchars($tenant['name'] ?? 'FleetVision'); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php if(!empty($tenant['logo_url'])): ?><link rel="icon" href="<?php echo $tenant['logo_url']; ?>" type="image/x-icon"><?php endif; ?>
    
    <style>
        .login-bg {
            background-image: url('<?php echo $bgImage; ?>');
            background-size: cover;
            background-position: center;
        }
        .glass-card {
            background-color: <?php echo $rgbaCard; ?>;
            backdrop-filter: blur(12px);
            color: <?php echo $textColor; ?>;
        }
        .text-custom { color: <?php echo $textColor; ?>; }
        .input-custom {
            background-color: rgba(255,255,255,0.8);
            border-color: rgba(0,0,0,0.1);
            color: #000;
        }
        .btn-custom {
            background-color: <?php echo $btnColor; ?>;
            transition: transform 0.2s, filter 0.2s;
        }
        .btn-custom:hover { filter: brightness(110%); transform: translateY(-2px); }
    </style>
</head>
<body class="login-bg h-screen w-full flex items-center justify-center font-sans antialiased">

    <div class="absolute inset-0 bg-black/30"></div>

    <div class="glass-card relative z-10 w-full max-w-md p-10 rounded-2xl shadow-2xl mx-4 border border-white/10 transition-all duration-300">
        
        <div class="text-center mb-8">
            <?php if (!empty($tenant['logo_url'])): ?>
                <img src="<?php echo $tenant['logo_url']; ?>" alt="Logo" class="h-20 mx-auto mb-4 object-contain drop-shadow-md">
            <?php else: ?>
                <h1 class="text-3xl font-extrabold uppercase tracking-widest mb-2" style="color: <?php echo $textColor; ?>">
                    <?php echo htmlspecialchars($tenant['logo_text'] ?? 'LOGIN'); ?>
                </h1>
            <?php endif; ?>
            
            <p class="text-sm font-medium tracking-wide uppercase opacity-80" style="color: <?php echo $textColor; ?>">
                <?php echo htmlspecialchars($tenant['login_message'] ?? 'Bem-vindo'); ?>
            </p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50/95 border-l-4 border-red-500 text-red-600 p-4 mb-6 rounded text-sm shadow flex items-start animate-pulse">
                <i class="fas fa-exclamation-triangle mr-2 mt-0.5"></i> 
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="text-xs font-bold uppercase ml-1 opacity-70" style="color: <?php echo $textColor; ?>">E-mail</label>
                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <input type="email" name="email" required 
                           class="w-full pl-10 pr-4 py-3 rounded-xl input-custom focus:ring-2 focus:ring-[<?php echo $btnColor; ?>] outline-none transition"
                           placeholder="usuario@empresa.com">
                </div>
            </div>
            <div>
                <label class="text-xs font-bold uppercase ml-1 opacity-70" style="color: <?php echo $textColor; ?>">Senha</label>
                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">
                        <i class="fas fa-lock"></i>
                    </div>
                    <input type="password" name="password" required 
                           class="w-full pl-10 pr-4 py-3 rounded-xl input-custom focus:ring-2 focus:ring-[<?php echo $btnColor; ?>] outline-none transition"
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="btn-custom w-full text-white font-bold py-3.5 rounded-xl shadow-lg uppercase tracking-wider text-sm mt-4 flex items-center justify-center gap-2">
                <span>Entrar</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="mt-8 text-center border-t border-black/10 pt-4 opacity-60">
            <p class="text-xs" style="color: <?php echo $textColor; ?>">Sistema Seguro FleetVision &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>

</body>
</html>