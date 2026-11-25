<?php
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

if (is_logged_in()) {
    redirect(ADMIN_URL . '/index.php');
}

$db = get_db();
$token = $_GET['token'] ?? '';
$token = trim($token);
$error = '';
$success = '';
$user = null;

if ($token !== '') {
    $stmt = $db->prepare('SELECT id, username, email FROM users WHERE password_reset_token_hash IS NOT NULL AND password_reset_expires_at > NOW()');
    $stmt->execute();
    foreach ($stmt->fetchAll() as $candidate) {
        if (password_verify($token, $candidate['password_reset_token_hash'])) {
            $user = $candidate;
            break;
        }
    }

    if (!$user) {
        $error = 'Le lien de réinitialisation est invalide ou a expiré.';
    }
} else {
    $error = 'Lien invalide. Merci de refaire une demande.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Veuillez renseigner les deux champs.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } else {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare('UPDATE users SET password = ?, force_password_change = 0, password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ?');
        $stmt->execute([$hashed, $user['id']]);
        log_activity($db, $user['id'], 'update', 'user', $user['id'], 'Réinitialisation mot de passe');
        redirect(ADMIN_URL . '/login.php?reset=success');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - <?php echo SITE_NAME; ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth">
        <?php include __DIR__ . '/includes/auth_aside.php'; ?>

        <main class="auth-main">
            <div class="auth-card">
                <h1>Nouveau mot de passe</h1>
                <p class="auth-lead">Choisissez un mot de passe robuste d'au moins 8 caractères.</p>

                <?php if (!empty($error)): ?>
                    <div class="notice notice-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($user && empty($error)): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" autofocus required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn-primary">Enregistrer</button>
                    </form>
                <?php elseif (!$success): ?>
                    <div class="auth-links">
                        <a href="forgot_password.php">Faire une nouvelle demande</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
