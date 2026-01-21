<?php
// Processamento Login (Mantido)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT u.*, t.slug as tenant_slug FROM saas_users u JOIN saas_tenants t ON u.tenant_id = t.id WHERE u.email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        header("Location: /" . $tenant['slug'] . "/dashboard");
        exit;
    } else { $error = 'Credenciais inválidas.'; }
}

// === CONFIGURAÇÃO VISUAL ===
$bgImage = !empty($tenant['login_bg_url']) ? $tenant['login_bg_url'] : 'https://images.unsplash.com/photo-1494548162494-384bba4ab999?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80';
$btnColor = !empty($tenant['login_btn_color']) ? $tenant['login_btn_color'] : $tenant['primary_color'];
$cardBgHex = !empty($tenant['login_card_bg']) ? $tenant['login_card_bg'] : '#ffffff';
$textColor = !empty($tenant['login_text_color']) ? $tenant['login_text_color'] : '#374151';
$opacity = !empty($tenant['login_card_opacity']) ? ($tenant['login_card_opacity'] / 100) : 0.95;

// Converter Hex do Fundo para RGB (para aplicar opacidade)
list($r, $g, $b) = sscanf($cardBgHex, "#%02x%02x%02x");
$rgbaCard = "rgba($r, $g, $b, $opacity)";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo htmlspecialchars($tenant['name']); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="/style.css">
    
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
                    <?php echo htmlspecialchars($tenant['logo_text']); ?>
                </h1>
            <?php endif; ?>
            
            <p class="text-sm font-medium tracking-wide uppercase opacity-80" style="color: <?php echo $textColor; ?>">
                <?php echo htmlspecialchars($tenant['login_message'] ?: 'Bem-vindo'); ?>
            </p>
        </div>

        <?php if(isset($error) && $error): ?>
            <div class="bg-red-50/90 border-l-4 border-red-500 text-red-600 p-3 mb-6 rounded text-sm shadow flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="text-xs font-bold uppercase ml-1 opacity-70" style="color: <?php echo $textColor; ?>">E-mail</label>
                <input type="email" name="email" required 
                       class="w-full mt-1 px-4 py-3 rounded-xl input-custom focus:ring-2 focus:ring-[<?php echo $btnColor; ?>] outline-none transition"
                       placeholder="usuario@empresa.com">
            </div>
            <div>
                <label class="text-xs font-bold uppercase ml-1 opacity-70" style="color: <?php echo $textColor; ?>">Senha</label>
                <input type="password" name="password" required 
                       class="w-full mt-1 px-4 py-3 rounded-xl input-custom focus:ring-2 focus:ring-[<?php echo $btnColor; ?>] outline-none transition"
                       placeholder="••••••••">
            </div>

            <button type="submit" class="btn-custom w-full text-white font-bold py-3.5 rounded-xl shadow-lg uppercase tracking-wider text-sm mt-4">
                Entrar
            </button>
        </form>

        <div class="mt-8 text-center border-t border-black/10 pt-4 opacity-60">
            <p class="text-xs" style="color: <?php echo $textColor; ?>">Sistema Seguro FleetVision</p>
        </div>
    </div>

</body>
</html>