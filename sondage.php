<?php
require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/config/database.php';

$db = get_db();

$normalizeLicence = static function (string $value): string {
    $value = strtoupper(trim($value));
    return preg_replace('/\s+/', '', $value);
};

$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($poll_id > 0) {
    $stmt = $db->prepare('SELECT * FROM polls WHERE id = ?');
    $stmt->execute([$poll_id]);
} else {
    $stmt = $db->prepare('SELECT * FROM polls WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    $stmt->execute();
}
$poll = $stmt->fetch();

$error = null;
$success = false;

if ($poll && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $licence = $normalizeLicence($_POST['licence_number'] ?? '');
    $option_id = (int)($_POST['option_id'] ?? 0);

    if (!$poll['is_active']) {
        $error = 'Ce sondage est fermé, votre vote ne peut pas être enregistré.';
    } elseif ($licence === '') {
        $error = 'Merci de renseigner votre numéro de licence.';
    } elseif ($option_id === 0) {
        $error = 'Merci de choisir une option.';
    } else {
        $stmt = $db->prepare('SELECT id FROM poll_options WHERE id = ? AND poll_id = ?');
        $stmt->execute([$option_id, $poll['id']]);
        if (!$stmt->fetch()) {
            $error = 'Option invalide.';
        } else {
            $stmt = $db->prepare('SELECT id FROM poll_licencies WHERE licence_number = ?');
            $stmt->execute([$licence]);
            if (!$stmt->fetch()) {
                $error = 'Ce numéro de licence n\'est pas reconnu. Vérifiez votre saisie ou contactez le club.';
            } else {
                try {
                    $stmt = $db->prepare('INSERT INTO poll_votes (poll_id, option_id, licence_number, ip_address) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$poll['id'], $option_id, $licence, get_user_ip()]);
                    $success = true;
                } catch (PDOException $e) {
                    if ((int)$e->getCode() === 23000) {
                        $error = 'Un vote a déjà été enregistré pour ce numéro de licence.';
                    } else {
                        $error = 'Une erreur est survenue, merci de réessayer.';
                    }
                }
            }
        }
    }
}

$options = [];
if ($poll) {
    $stmt = $db->prepare('SELECT * FROM poll_options WHERE poll_id = ? ORDER BY display_order ASC, id ASC');
    $stmt->execute([$poll['id']]);
    $options = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $poll ? htmlspecialchars($poll['title']) : 'Sondage'; ?> - Tennis Club de Batilly</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#d1875a">
    <link rel="icon" type="image/jpeg" href="./assets/images/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #d1875a;
            --brand-dark: #b06f4a;
            --ink: #1f1f1f;
            --muted: #5f6368;
            --bg: #faf6f1;
            --surface: #ffffff;
            --border: rgba(31, 31, 31, 0.1);
            --shadow: 0 24px 60px rgba(31, 31, 31, 0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 36px;
            box-shadow: var(--shadow);
            max-width: 640px;
            width: 100%;
        }
        h1 { font-size: clamp(22px, 4.5vw, 28px); margin-bottom: 8px; }
        .subtitle { color: var(--muted); margin-bottom: 24px; }
        .options { display: grid; gap: 14px; margin-bottom: 24px; }
        .option {
            display: flex;
            align-items: center;
            gap: 14px;
            border: 2px solid var(--border);
            border-radius: 14px;
            padding: 12px 16px;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .option:hover { border-color: var(--brand); }
        .option input[type="radio"] { width: 20px; height: 20px; accent-color: var(--brand); flex-shrink: 0; }
        .option img { height: 56px; width: auto; border-radius: 8px; object-fit: contain; flex-shrink: 0; }
        .option span { font-weight: 600; }
        .option:has(input:checked) { border-color: var(--brand); background: rgba(209, 135, 90, 0.08); }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 2px solid var(--border);
            font-family: inherit;
            font-size: 15px;
        }
        input[type="text"]:focus { outline: none; border-color: var(--brand); }
        .help { color: var(--muted); font-size: 13px; margin-top: 6px; }
        button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--brand);
            color: #fff;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            width: 100%;
            justify-content: center;
            font-family: inherit;
        }
        button:hover { background: var(--brand-dark); }
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .alert-error { background: rgba(220, 53, 69, 0.1); color: #b02a37; }
        .alert-success { background: rgba(40, 167, 69, 0.1); color: #1e7e34; }
        .privacy-note { color: var(--muted); font-size: 12px; margin-top: 18px; }
        .privacy-note a { color: var(--brand); }
        .back { display: inline-block; margin-top: 18px; color: var(--brand); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <?php if (!$poll): ?>
            <h1>Aucun sondage en cours</h1>
            <p class="subtitle">Il n'y a pas de sondage ouvert au vote pour le moment. Revenez plus tard !</p>
            <a class="back" href="/index.html">&larr; Retour au site</a>
        <?php elseif ($success): ?>
            <h1>Merci pour votre vote !</h1>
            <p class="subtitle">Votre choix a bien été enregistré pour « <?php echo htmlspecialchars($poll['title']); ?> ».</p>
            <a class="back" href="/index.html">&larr; Retour au site</a>
        <?php else: ?>
            <h1><?php echo htmlspecialchars($poll['title']); ?></h1>
            <?php if (!empty($poll['description'])): ?>
                <p class="subtitle"><?php echo nl2br(htmlspecialchars($poll['description'])); ?></p>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$poll['is_active']): ?>
                <div class="alert alert-error">Ce sondage est actuellement fermé au vote.</div>
            <?php elseif (empty($options)): ?>
                <p class="subtitle">Ce sondage ne contient pas encore d'options.</p>
            <?php else: ?>
                <form method="POST">
                    <div class="options">
                        <?php foreach ($options as $opt): ?>
                            <label class="option">
                                <input type="radio" name="option_id" value="<?php echo $opt['id']; ?>" required>
                                <?php if (!empty($opt['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($opt['image_path']); ?>" alt="<?php echo htmlspecialchars($opt['label']); ?>">
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($opt['label']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group">
                        <label for="licence_number">Votre numéro de licence FFT</label>
                        <input type="text" id="licence_number" name="licence_number" required autocomplete="off" placeholder="Ex: 1234567A">
                        <p class="help">Utilisé uniquement pour vérifier que vous êtes bien licencié au club et limiter le vote à une seule participation par personne.</p>
                    </div>

                    <button type="submit">Voter</button>

                    <p class="privacy-note">
                        Votre numéro de licence est conservé le temps du sondage pour éviter les votes multiples.
                        Voir notre <a href="/politique-confidentialite.html" target="_blank" rel="noopener">politique de confidentialité</a>.
                    </p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
