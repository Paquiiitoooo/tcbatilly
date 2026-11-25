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
                $subject = 'Nouveau mot de passe - TC Batilly';
                $message = "Bonjour " . $user['username'] . ",\n\n" .
                    "Quelqu'un a demandé à changer le mot de passe de ce compte. Pour en choisir un nouveau :\n" . $resetUrl . "\n\n" .
                    "Ce lien est valable 1 heure. Vous n'êtes pas à l'origine de la demande ? Ne faites rien, votre mot de passe reste le même.";
                $html = email_layout([
                    'greeting' => 'Bonjour ' . $user['username'] . ',',
                    'lead' => 'Quelqu\'un a demandé à changer le mot de passe de ce compte.',
                    'button' => ['label' => 'Choisir un nouveau mot de passe', 'url' => $resetUrl],
                    'notes' => [
                        'Ce lien est valable 1 heure.',
                        'Vous n\'êtes pas à l\'origine de la demande ? Ne faites rien, votre mot de passe reste le même.',
                    ],
                ]);
                $sent = send_email($user['email'], $subject, $message, $html);

                // Hors production uniquement : on expose le lien si l'envoi échoue,
                // pour ne pas bloquer les tests. Jamais en production.
                if (!$sent && ENVIRONMENT !== 'production') {
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
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth">
        <?php include __DIR__ . '/includes/auth_aside.php'; ?>

        <main class="auth-main">
            <div class="auth-card">
                <img class="auth-logo" src="../assets/images/logo.jpg" alt="Tennis Club de Batilly" width="72" height="72">
                <h1>Mot de passe oublié</h1>
                <p class="auth-lead">Un lien pour choisir un nouveau mot de passe sera envoyé à cette adresse.</p>

                <?php if (!empty($error)): ?>
                    <div class="notice notice-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="notice notice-success" role="status"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($debug_token): ?>
                    <div class="notice notice-debug">Mode test : utilisez ce lien pour réinitialiser le mot de passe :<br><a href="<?php echo htmlspecialchars($debug_token); ?>"><?php echo htmlspecialchars($debug_token); ?></a></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Adresse e-mail</label>
                        <input type="email" id="email" name="email" placeholder="vous@exemple.fr" required autofocus autocomplete="email">
                    </div>
                    <button type="submit" class="btn-primary">Envoyer le lien</button>
                </form>

                <div class="auth-links">
                    <a href="login.php">Retour à la connexion</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
