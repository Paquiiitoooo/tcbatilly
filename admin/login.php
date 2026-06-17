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

    $stmt = $db->prepare("UPDATE users SET two_factor_code_hash = ?, two_factor_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?");
    $stmt->execute([$hash, $user['id']]);

    $_SESSION['pending_user_id'] = $user['id'];
    $_SESSION['pending_username'] = $user['username'];
    $_SESSION['pending_user_email'] = $user['email'];
    $_SESSION['pending_user_role'] = $user['role'];
    $_SESSION['auth_step'] = 'two_factor';

    $subject = SITE_NAME . ' - Code de vérification';
    $message = "Bonjour " . $user['username'] . "\n\n" .
        "Voici votre code de vérification à 6 chiffres : " . $code . "\n" .
        "Il expirera dans 10 minutes.\n\n" .
        "Si vous n'êtes pas à l'origine de cette connexion, ignorez ce message.";
    $fromDomain = parse_url(SITE_URL, PHP_URL_HOST) ?: 'tcbatilly.fr';
    $headers = 'From: ' . SITE_NAME . " <no-reply@" . $fromDomain . ">";

    $sent = @mail($user['email'], $subject, $message, $headers);

    if (!$sent || DEBUG_MODE) {
        $_SESSION['two_factor_debug_code'] = $code;
        $_SESSION['two_factor_delivery'] = 'debug';
    } else {
        unset($_SESSION['two_factor_debug_code']);
        $_SESSION['two_factor_delivery'] = 'email';
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

            dispatch_two_factor_code($db, $user);
            $delivery = $_SESSION['two_factor_delivery'] ?? 'email';
            $info_message = ($delivery === 'email')
                ? 'Un code de vérification vient d\'être envoyé à ' . htmlspecialchars($user['email']) . '. '
                  . 'Vérifiez votre boîte de réception (et vos spams).'
                : 'En environnement local, utilisez le code affiché ci-dessous pour valider la connexion.';
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
                $delivery = $_SESSION['two_factor_delivery'] ?? 'email';
                $info_message = ($delivery === 'email')
                    ? 'Nouveau code envoyé. Vérifiez votre messagerie.'
                    : 'En environnement local, utilisez le nouveau code affiché ci-dessous.';
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
            width: min(960px, 96vw);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 0;
            border-radius: 26px;
            overflow: hidden;
            background: var(--surface);
            box-shadow: var(--shadow);
            border: 1px solid rgba(27, 25, 23, 0.08);
        }
        .login-aside {
            padding: 48px 40px;
            background: linear-gradient(160deg, rgba(209, 135, 90, 0.95) 0%, rgba(57, 90, 100, 0.85) 100%);
            color: #fffaf3;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .brand-block h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }
        .brand-block p {
            margin-top: 12px;
            font-size: 15px;
            line-height: 1.6;
            opacity: 0.92;
        }
        .brand-stats {
            margin-top: 40px;
            display: grid;
            gap: 20px;
        }
        .brand-stat {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand-stat span {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.16);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .brand-stat strong {
            font-size: 16px;
            font-weight: 600;
        }
        .login-main {
            padding: 48px 40px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 32px;
        }
        .login-header h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .login-header p {
            color: rgba(31, 31, 31, 0.65);
        }
        .form-group { margin-bottom: 24px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid rgba(27, 25, 23, 0.12);
            font-size: 15px;
            transition: border 0.25s ease, box-shadow 0.25s ease;
            background: rgba(255, 255, 255, 0.9);
        }
        .form-group input:focus {
            outline: none;
            border-color: rgba(209, 135, 90, 0.55);
            box-shadow: 0 0 0 4px rgba(209, 135, 90, 0.15);
        }
        .error-message {
            background: rgba(224, 97, 76, 0.16);
            color: #9b2c19;
            padding: 16px 18px;
            border-radius: 14px;
            border-left: 4px solid #e0614c;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .btn-login {
            width: 100%;
            padding: 15px 18px;
            border-radius: 14px;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            color: #fffdf9;
            box-shadow: 0 14px 28px rgba(209, 135, 90, 0.25);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 18px 32px rgba(209, 135, 90, 0.3); }
        .btn-login:active { transform: translateY(0); }
        .btn-login-secondary {
            width: 100%;
            padding: 15px 18px;
            border-radius: 14px;
            border: 1.5px solid rgba(27, 25, 23, 0.12);
            background: rgba(27, 25, 23, 0.05);
            color: var(--muted);
            font-weight: 600;
            cursor: pointer;
            transition: background 0.25s ease, transform 0.25s ease;
        }
        .btn-login-secondary:hover {
            background: rgba(27, 25, 23, 0.12);
        }
        .btn-login-secondary:active {
            transform: translateY(1px);
        }
        .two-factor-actions {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }
        .login-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: rgba(31, 31, 31, 0.55);
        }
        .login-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .login-footer a:hover { text-decoration: underline; }
        .info-message {
            background: rgba(57, 90, 100, 0.12);
            color: #294d57;
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid rgba(57, 90, 100, 0.4);
        }
        .info-message.debug {
            background: rgba(224, 97, 76, 0.14);
            color: #8f3d2a;
            border-left-color: rgba(224, 97, 76, 0.45);
        }
        .code-input {
            letter-spacing: 8px;
            text-align: center;
            font-weight: 600;
        }
        .code-input::placeholder {
            color: rgba(27, 25, 23, 0.3);
            letter-spacing: 8px;
        }
        .forgot-link {
            display: inline-block;
            margin-top: 16px;
            font-size: 13px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .forgot-link:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            body { padding: 20px; }
            .login-wrapper { grid-template-columns: 1fr; }
            .login-aside { display: none; }
            .login-main { padding: 36px 28px 32px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <aside class="login-aside">
            <div class="brand-block">
                <h1>Tennis Club de Batilly</h1>
                <p>Accédez à l'espace d'administration pour gérer le contenu du site, les membres de l'équipe et l'actualité du club.</p>

                <div class="brand-stats">
                    <div class="brand-stat">
                        <span>🎾</span>
                        <div>
                            <strong>Gestion complète</strong>
                            <p style="font-size:13px;opacity:0.85;">Événements, actualités, tarifs, services</p>
                        </div>
                    </div>
                    <div class="brand-stat">
                        <span>🔐</span>
                        <div>
                            <strong>Espace sécurisé</strong>
                            <p style="font-size:13px;opacity:0.85;">Accès réservé aux membres autorisés</p>
                        </div>
                    </div>
                </div>
            </div>
            <p style="font-size:13px;opacity:0.7;text-align:right;">Développé pour le TC Batilly – Back-office 2025</p>
        </aside>

        <main class="login-main">
            <div class="login-header">
                <h2>Connexion à l'administration</h2>
                <p>Renseignez vos identifiants pour accéder au tableau de bord.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($info_message)): ?>
                <div class="info-message"><?php echo htmlspecialchars($info_message); ?></div>
            <?php endif; ?>
            <?php if ($step === 'two_factor' && !empty($debug_code)): ?>
                <div class="info-message debug">Code de test : <?php echo htmlspecialchars($debug_code); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php if ($step === 'login'): ?>
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur ou email</label>
                        <input type="text" id="username" name="username" required autofocus value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn-login">Se connecter</button>
                    <a class="forgot-link" href="forgot_password.php">Mot de passe oublié ?</a>
                <?php elseif ($step === 'two_factor'): ?>
                    <div class="form-group">
                        <label for="two_factor_code">Code de vérification</label>
                        <input type="text" id="two_factor_code" name="two_factor_code" inputmode="numeric" maxlength="6" class="code-input" placeholder="- - - - - -" autocomplete="one-time-code" required>
                    </div>
                    <div class="two-factor-actions">
                        <button type="submit" name="resend_code" value="1" class="btn-login-secondary">Renvoyer un code</button>
                        <button type="submit" class="btn-login">Vérifier le code</button>
                    </div>
                <?php elseif ($step === 'password_change'): ?>
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn-login">Changer le mot de passe</button>
                <?php endif; ?>
                <input type="hidden" name="auth_step" value="<?php echo $step; ?>">
            </form>

            <div class="login-footer">
                <a href="../index.html">← Retour au site</a>
                <span>Besoin d'aide ? Contactez l'administrateur.</span>
            </div>
        </main>
    </div>
</body>
</html>

