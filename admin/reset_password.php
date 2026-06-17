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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #d1875a;
            --brand-dark: #b06f4a;
            --accent: #395a64;
            --canvas: #fdfcf9;
            --surface: rgba(255, 255, 255, 0.92);
            --shadow: 0 40px 80px rgba(27, 25, 23, 0.18);
            --muted: #5f6368;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #faf3ec 0%, #f5e3d1 45%, #e9d1bb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
            color: #1f1f1f;
        }
        .login-wrapper {
            width: min(520px, 96vw);
            border-radius: 26px;
            overflow: hidden;
            background: var(--surface);
            box-shadow: var(--shadow);
            border: 1px solid rgba(27, 25, 23, 0.1);
            padding: 44px 38px;
            display: grid;
            gap: 24px;
        }
        h1 {
            font-size: clamp(24px, 4vw, 30px);
            margin-bottom: 6px;
        }
        p { color: rgba(31, 31, 31, 0.7); }
        .message {
            border-radius: 14px;
            padding: 16px 18px;
            font-size: 14px;
            line-height: 1.5;
        }
        .error { background: rgba(224,97,76,0.16); color: #8f3d2a; border-left: 4px solid rgba(224,97,76,0.5); }
        .success { background: rgba(53,182,134,0.16); color: #1d7e58; border-left: 4px solid rgba(53,182,134,0.45); }
        .form-group { margin-top: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 10px; }
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid rgba(27, 25, 23, 0.12);
            font-size: 15px;
        }
        .btn-primary {
            width: 100%;
            margin-top: 24px;
            padding: 15px 18px;
            border-radius: 14px;
            border: none;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            color: #fffdf9;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(209,135,90,0.25);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 18px 36px rgba(209,135,90,0.32); }
        .back-link {
            text-align: center;
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div>
            <h1>Définir un nouveau mot de passe</h1>
            <p>Choisissez un mot de passe robuste d'au moins 8 caractères.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($user && empty($error)): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn-primary">Enregistrer</button>
            </form>
        <?php elseif (!$success): ?>
            <a class="back-link" href="forgot_password.php">← Faire une nouvelle demande</a>
        <?php endif; ?>
    </div>
</body>
</html>

