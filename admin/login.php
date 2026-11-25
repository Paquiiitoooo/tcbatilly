<?php
/**
 * TC BATILLY - Page de connexion admin
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

if (is_logged_in()) {
    redirect(ADMIN_URL . '/index.php');
}

$db = get_db();

function dispatch_two_factor_code(PDO $db, array $user): void {
    $code = (string)random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);

    // Expiration calculée côté PHP (même horloge/fuseau que la vérification),
    // pour éviter tout décalage avec le NOW() de la base (souvent en UTC sur Plesk).
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // +10 minutes

    $stmt = $db->prepare("UPDATE users SET two_factor_code_hash = ?, two_factor_expires_at = ? WHERE id = ?");
    $stmt->execute([$hash, $expiresAt, $user['id']]);

    $_SESSION['pending_user_id'] = $user['id'];
    $_SESSION['pending_username'] = $user['username'];
    $_SESSION['pending_user_email'] = $user['email'];
    $_SESSION['pending_user_role'] = $user['role'];
    $_SESSION['auth_step'] = 'two_factor';

    $subject = 'Code de connexion - TC Batilly';
    $message = "Bonjour " . $user['username'] . ",\n\n" .
        "Votre code de connexion : " . $code . "\n" .
        "Il est valable 10 minutes.\n\n" .
        "Si vous n'avez pas demandé ce code, ne faites rien : personne ne peut se connecter sans lui.";
    $html = email_layout([
        'greeting' => 'Bonjour ' . $user['username'] . ',',
        'lead' => 'Voici le code pour entrer dans l\'administration du site.',
        'code' => $code,
        'notes' => [
            'Il est valable 10 minutes.',
            'Vous n\'avez rien demandé ? Ne faites rien : personne ne peut se connecter sans ce code.',
        ],
    ]);

    $sent = send_email($user['email'], $subject, $message, $html);

    if ($sent) {
        unset($_SESSION['two_factor_debug_code']);
        $_SESSION['two_factor_delivery'] = 'email';
    } elseif (ENVIRONMENT !== 'production') {
        // Hors production uniquement : on affiche le code pour ne pas bloquer les tests.
        $_SESSION['two_factor_debug_code'] = $code;
        $_SESSION['two_factor_delivery'] = 'debug';
    } else {
        // En production, on n'affiche jamais le code : on signale l'échec d'envoi.
        unset($_SESSION['two_factor_debug_code']);
        $_SESSION['two_factor_delivery'] = 'failed';
    }
}

function two_factor_delivery_message(string $email): string {
    switch ($_SESSION['two_factor_delivery'] ?? 'email') {
        case 'email':
            return 'Un code de vérification vient d\'être envoyé à ' . htmlspecialchars($email)
                . '. Vérifiez votre boîte de réception (et vos spams).';
        case 'debug':
            return 'Environnement de test : utilisez le code affiché ci-dessous pour valider la connexion.';
        default: // failed
            return 'L\'envoi du code par e-mail a échoué. Réessayez (« Renvoyer un code ») ou contactez l\'administrateur.';
    }
}

function clear_pending_auth(): void {
    unset(
        $_SESSION['pending_user_id'],
        $_SESSION['pending_username'],
        $_SESSION['pending_user_email'],
        $_SESSION['pending_user_role'],
        $_SESSION['two_factor_debug_code'],
        $_SESSION['two_factor_delivery']
    );
    $_SESSION['auth_step'] = 'login';
}

function finalize_login(PDO $db, array $user): void {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    $stmt = $db->prepare('UPDATE users SET password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ?');
    $stmt->execute([$user['id']]);

    clear_pending_auth();
    $_SESSION['auth_step'] = 'login';

    log_activity($db, $user['id'], 'login');
    redirect(ADMIN_URL . '/index.php');
}

$error = '';
$info_message = '';
$step = $_SESSION['auth_step'] ?? 'login';

// Accès direct (GET) : on repart toujours du formulaire de connexion.
// Évite de rester bloqué sur l'étape « code 2FA » à cause d'une session précédente.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clear_pending_auth();
    $step = 'login';
}

$success_query = $_GET['reset'] ?? '';
if ($success_query === 'success') {
    $info_message = 'Votre mot de passe a été mis à jour. Vous pouvez vous connecter avec vos nouvelles identifiants.';
    $step = 'login';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_step = $_POST['auth_step'] ?? 'login';

    switch ($submitted_step) {
        case 'login':
            $username = sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $error = 'Veuillez indiquer vos identifiants.';
                clear_pending_auth();
                $step = 'login';
                break;
            }

            $stmt = $db->prepare("SELECT id, username, email, password, role, force_password_change, two_factor_enabled FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Identifiants incorrects.';
                clear_pending_auth();
                $step = 'login';
                break;
            }

            // Si la 2FA est désactivée pour ce compte, connexion directe (sans code e-mail).
            if ((int) ($user['two_factor_enabled'] ?? 1) !== 1) {
                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_username'] = $user['username'];
                $_SESSION['pending_user_email'] = $user['email'];
                $_SESSION['pending_user_role'] = $user['role'];

                if ((int) $user['force_password_change'] === 1) {
                    $_SESSION['auth_step'] = 'password_change';
                    $info_message = 'Veuillez définir un nouveau mot de passe avant d\'accéder au tableau de bord.';
                    $step = 'password_change';
                } else {
                    finalize_login($db, $user);
                }
                break;
            }

            dispatch_two_factor_code($db, $user);
            $info_message = two_factor_delivery_message($user['email']);
            $step = 'two_factor';
            break;

        case 'two_factor':
            $pendingId = $_SESSION['pending_user_id'] ?? 0;
            if (!$pendingId) {
                $error = 'Session d\'authentification expirée. Veuillez vous reconnecter.';
                clear_pending_auth();
                $step = 'login';
                break;
            }

            $stmt = $db->prepare("SELECT id, username, email, role, force_password_change, two_factor_code_hash, two_factor_expires_at FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$pendingId]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Utilisateur introuvable.';
                clear_pending_auth();
                $step = 'login';
                break;
            }

            if (isset($_POST['resend_code'])) {
                dispatch_two_factor_code($db, $user);
                $info_message = two_factor_delivery_message($user['email']);
                $step = 'two_factor';
                break;
            }

            $inputCode = preg_replace('/\D/', '', $_POST['two_factor_code'] ?? '');
            if ($inputCode === '') {
                $error = 'Veuillez saisir le code reçu.';
                $step = 'two_factor';
                break;
            }
            if (strlen($inputCode) !== 6) {
                $error = 'Le code doit comporter 6 chiffres.';
                $step = 'two_factor';
                break;
            }

            if (empty($user['two_factor_code_hash']) || empty($user['two_factor_expires_at']) || strtotime($user['two_factor_expires_at']) < time()) {
                dispatch_two_factor_code($db, $user);
                $info_message = 'Le code avait expiré. Un nouveau code vient de vous être envoyé.';
                $step = 'two_factor';
                break;
            }

            if (!password_verify($inputCode, $user['two_factor_code_hash'])) {
                $error = 'Code de vérification invalide.';
                $step = 'two_factor';
                break;
            }

            $stmt = $db->prepare("UPDATE users SET two_factor_code_hash = NULL, two_factor_expires_at = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);

            if ((int)$user['force_password_change'] === 1) {
                $_SESSION['auth_step'] = 'password_change';
                $info_message = 'Veuillez définir un nouveau mot de passe avant d\'accéder au tableau de bord.';
                $step = 'password_change';
            } else {
                finalize_login($db, $user);
            }
            break;

        case 'password_change':
            $pendingId = $_SESSION['pending_user_id'] ?? 0;
            if (!$pendingId) {
                $error = 'Session expirée. Veuillez vous reconnecter.';
                clear_pending_auth();
                $step = 'login';
                break;
            }

            $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$pendingId]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Utilisateur introuvable.';
                clear_pending_auth();
                $step = 'login';
                break;
            }

            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($newPassword === '' || $confirmPassword === '') {
                $error = 'Veuillez renseigner les deux champs de mot de passe.';
                $step = 'password_change';
                break;
            }

            if ($newPassword !== $confirmPassword) {
                $error = 'Les mots de passe ne correspondent pas.';
                $step = 'password_change';
                break;
            }

            if (strlen($newPassword) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
                $step = 'password_change';
                break;
            }

            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
            $stmt->execute([$hashed, $user['id']]);

            log_activity($db, $user['id'], 'update', 'user', $user['id'], 'Changement de mot de passe');
            finalize_login($db, $user);
            break;

        default:
            clear_pending_auth();
            $step = 'login';
            break;
    }
}

if (!in_array($step, ['login', 'two_factor', 'password_change'], true)) {
    $step = 'login';
}

$_SESSION['auth_step'] = $step;
$debug_code = $_SESSION['two_factor_debug_code'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo SITE_NAME; ?></title>
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
                <?php
                $titles = [
                    'login' => ['Connexion', ''],
                    'two_factor' => ['Code de vérification', 'Un code à 6 chiffres vient d\'être envoyé par e-mail.'],
                    'password_change' => ['Nouveau mot de passe', '8 caractères minimum.'],
                ];
                ?>
                <h1><?php echo $titles[$step][0]; ?></h1>
                <?php if ($titles[$step][1] !== ''): ?>
                    <p class="auth-lead"><?php echo $titles[$step][1]; ?></p>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="notice notice-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($info_message)): ?>
                    <div class="notice notice-info" role="status"><?php echo htmlspecialchars($info_message); ?></div>
                <?php endif; ?>
                <?php if ($step === 'two_factor' && !empty($debug_code)): ?>
                    <div class="notice notice-debug">Code de test : <?php echo htmlspecialchars($debug_code); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php if ($step === 'login'): ?>
                        <div class="form-group">
                            <label for="username">Identifiant ou e-mail</label>
                            <input type="text" id="username" name="username" required autofocus autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" required autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn-primary">Entrer</button>
                    <?php elseif ($step === 'two_factor'): ?>
                        <div class="form-group">
                            <label for="two_factor_code">Code de vérification</label>
                            <input type="text" id="two_factor_code" name="two_factor_code" inputmode="numeric" maxlength="6" class="code-input" placeholder="000000" autocomplete="one-time-code" autofocus required>
                        </div>
                        <div class="two-factor-actions">
                            <button type="submit" class="btn-primary">Vérifier le code</button>
                            <button type="submit" name="resend_code" value="1" class="btn-secondary" formnovalidate>Renvoyer un code</button>
                        </div>
                    <?php elseif ($step === 'password_change'): ?>
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" autofocus required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn-primary">Changer le mot de passe</button>
                    <?php endif; ?>
                    <input type="hidden" name="auth_step" value="<?php echo $step; ?>">
                </form>

                <div class="auth-links">
                    <?php if ($step === 'login'): ?>
                        <a href="forgot_password.php">Mot de passe oublié ?</a>
                    <?php endif; ?>
                    <a href="../">Retour au site</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
