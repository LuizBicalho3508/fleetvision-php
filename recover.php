<?php
// ARQUIVO: recover.php
require 'db.php';

$msg = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    if ($email) {
        // Verifica se usuário existe
        $stmt = $pdo->prepare("SELECT id, name FROM saas_users WHERE email = ? AND active = true");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Grava token
            $stmtToken = $pdo->prepare("INSERT INTO saas_password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmtToken->execute([$email, $token, $expires]);

            // Link de recuperação
            // IMPORTANTE: Ajuste APP_URL no config/app.php ou defina aqui
            $domain = defined('APP_URL') ? APP_URL : 'https://seusite.com';
            $link = "$domain/reset.php?token=$token";

            // Simulação de Envio de E-mail (Para produção, use PHPMailer)
            // Aqui usamos mail() nativo do PHP com headers HTML
            $subject = "Recuperar Senha - FleetVision";
            $message = "
            <html>
            <head><title>Recuperar Senha</title></head>
            <body>
                <h3>Olá, {$user['name']}</h3>
                <p>Recebemos uma solicitação para redefinir sua senha.</p>
                <p>Clique no botão abaixo para prosseguir:</p>
                <a href='$link' style='padding:10px 20px; background:#3b82f6; color:white; text-decoration:none; border-radius:5px;'>Redefinir Senha</a>
                <p>Se não foi você, ignore este e-mail.</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: No-Reply <noreply@seusite.com>' . "\r\n";

            if(mail($email, $subject, $message, $headers)) {
                $msg = "Enviamos um link de recuperação para seu e-mail.";
                $type = "success";
            } else {
                $msg = "Erro ao enviar e-mail. Contate o suporte.";
                $type = "error";
            }
        } else {
            // Por segurança, não avisamos se o e-mail não existe
            $msg = "Se o e-mail estiver cadastrado, você receberá o link.";
            $type = "success";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha | FleetVision</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Recuperação de Acesso</h1>
            <p class="text-sm text-slate-500">Informe seu e-mail para receber o link.</p>
        </div>

        <?php if($msg): ?>
            <div class="p-4 mb-4 text-sm rounded-lg <?php echo $type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">E-mail Cadastrado</label>
                <input type="email" name="email" required class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 outline-none transition">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">Enviar Link</button>
        </form>
        <div class="mt-6 text-center">
            <a href="/admin/login" class="text-sm text-slate-400 hover:text-slate-600">Voltar para Login</a>
        </div>
    </div>
</body>
</html>