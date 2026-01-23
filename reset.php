<?php
// ARQUIVO: reset.php
require 'db.php';

$token = $_GET['token'] ?? '';
$msg = '';
$validToken = false;

// Limpa tokens expirados
$pdo->query("DELETE FROM saas_password_resets WHERE expires_at < NOW()");

if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM saas_password_resets WHERE token = ? AND used = false AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();

    if ($resetRequest) {
        $validToken = true;
    } else {
        $msg = "Este link é inválido ou expirou.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $pass = $_POST['password'];
    $passConfirm = $_POST['password_confirm'];

    if ($pass === $passConfirm && strlen($pass) >= 6) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        
        // Atualiza senha do usuário
        $stmtUser = $pdo->prepare("UPDATE saas_users SET password = ? WHERE email = ?");
        $stmtUser->execute([$hash, $resetRequest['email']]);

        // Invalida token
        $pdo->prepare("UPDATE saas_password_resets SET used = true WHERE token = ?")->execute([$token]);

        $msg = "Senha alterada com sucesso! Redirecionando...";
        echo "<script>setTimeout(() => window.location.href = '/admin/login', 3000);</script>";
        $validToken = false; // Esconde o form
    } else {
        $msg = "As senhas não conferem ou são muito curtas.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Nova Senha</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-md">
        <h1 class="text-2xl font-bold text-slate-800 mb-6 text-center">Definir Nova Senha</h1>

        <?php if($msg): ?>
            <div class="p-4 mb-4 text-center text-sm bg-blue-100 text-blue-700 rounded-lg"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if($validToken): ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Nova Senha</label>
                <input type="password" name="password" required minlength="6" class="w-full px-4 py-3 rounded-lg border border-slate-300 outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Confirmar Senha</label>
                <input type="password" name="password_confirm" required minlength="6" class="w-full px-4 py-3 rounded-lg border border-slate-300 outline-none focus:border-blue-500">
            </div>
            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">Salvar Senha</button>
        </form>
        <?php elseif(!$msg): ?>
            <div class="text-center text-red-500">Token não fornecido.</div>
        <?php endif; ?>
    </div>
</body>
</html>