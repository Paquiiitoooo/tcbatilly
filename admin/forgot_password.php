<?php
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

if (is_logged_in()) {
    redirect(ADMIN_URL . '/index.php');
}

$db = get_db();
$error = '';
$success = '';
$debug_token = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez saisir une adresse email valide.';
    } else {
        $stmt = $db->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // On retourne toujours un message de succès pour ne pas divulguer l'existence de l'email
        $success = 'Si un compte existe avec cette adresse, un email de réinitialisation vient d\'être envoyé.';

        if ($user) {
            try {
                $token = bin2hex(random_bytes(32));
                $hash = password_hash($token, PASSWORD_DEFAULT);

                $update = $db->prepare('UPDATE users SET password_reset_token_hash = ?, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
                $update->execute([$hash, $user['id']]);

                $resetUrl = SITE_URL . '/admin/reset_password.php?token=' . urlencode($token);
                $subject = SITE_NAME . ' - Réinitialisation du mot de passe';
                $message = "Bonjour " . $user['username'] . "\n\n" .
                    "Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.\n" .
                    "Cliquez sur le lien suivant pour définir un nouveau mot de passe :\n" . $resetUrl . "\n\n" .
                    "Ce lien expirera dans 1 heure. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.";
                $fromDomain = parse_url(SITE_URL, PHP_URL_HOST) ?: 'tcbatilly.fr';
                $headers = 'From: ' . SITE_NAME . " <no-reply@" . $fromDomain . ">";

                $sent = @mail($user['email'], $subject, $message, $headers);

                if (!$sent || DEBUG_MODE) {
                    $debug_token = $resetUrl;
                }
            } catch (Throwable $e) {
                if (DEBUG_MODE) {
                    $error = 'Erreur interne : ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - <?php echo SITE_NAME; ?></title>
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
        .form-group { margin-top: 24px; }
        label { display: block; font-weight: 600; margin-bottom: 10px; }
        input[type="email"], input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid rgba(27, 25, 23, 0.12);
            font-size: 15px;
        }
        .btn-primary {
            width: 100%;
            margin-top: 22px;
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
        .message {
            border-radius: 14px;
            padding: 16px 18px;
            font-size: 14px;
            line-height: 1.5;
        }
        .error { background: rgba(224,97,76,0.16); color: #8f3d2a; border-left: 4px solid rgba(224,97,76,0.5); }
        .success { background: rgba(53,182,134,0.16); color: #1d7e58; border-left: 4px solid rgba(53,182,134,0.45); }
        .debug-token {
            background: rgba(224,97,76,0.14);
            color: #8f3d2a;
            border-left: 4px solid rgba(224,97,76,0.45);
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 13px;
        }
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
            <h1>Mot de passe oublié</h1>
            <p>Indiquez votre adresse email afin de recevoir un lien sécurisé pour définir un nouveau mot de passe.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($debug_token): ?>
            <div class="debug-token">Mode test : utilisez ce lien pour réinitialiser le mot de passe :<br><a href="<?php echo htmlspecialchars($debug_token); ?>"><?php echo htmlspecialchars($debug_token); ?></a></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" placeholder="vous@example.com" required autofocus>
            </div>
            <button type="submit" class="btn-primary">Envoyer le lien</button>
        </form>

        <a class="back-link" href="login.php">← Retour à la connexion</a>
    </div>
</body>
</html>

