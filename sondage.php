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
            --border: rgba(31, 31, 31, 0.12);
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
            justify-content: center;
            padding: 24px 16px 48px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: clamp(22px, 4vw, 40px);
            box-shadow: var(--shadow);
            max-width: 1100px;
            width: 100%;
            align-self: flex-start;
        }
        h1 { font-size: clamp(22px, 4.5vw, 30px); margin-bottom: 8px; }
        .subtitle { color: var(--muted); margin-bottom: 8px; }
        .hint { color: var(--muted); font-size: 14px; margin-bottom: 24px; }

        .options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .choice {
            display: flex;
            flex-direction: column;
            border: 2px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            background: var(--surface);
            transition: border-color .15s, box-shadow .15s;
        }
        .choice:hover { border-color: var(--brand); }
        .choice:has(input:checked) {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(209, 135, 90, 0.22);
        }
        .choice-media {
            position: relative;
            display: block;
            width: 100%;
            aspect-ratio: 1 / 1;
            padding: 0;
            border: 0;
            background: #f4f1ec;
            cursor: zoom-in;
        }
        .choice-media img {
            position: absolute;
            inset: 14px;
            width: calc(100% - 28px);
            height: calc(100% - 28px);
            object-fit: contain;
        }
        .choice-media:focus-visible { outline: 3px solid var(--brand); outline-offset: -3px; }
        .zoom-hint {
            position: absolute;
            right: 10px;
            bottom: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(31, 31, 31, 0.8);
            color: #fff;
            font-size: 12.5px;
            font-weight: 600;
        }
        .choice-pick {
            display: flex;
            align-items: center;
            margin-top: auto;
            gap: 12px;
            padding: 16px 18px;
            border-top: 1px solid var(--border);
            font-weight: 700;
            cursor: pointer;
        }
        .choice.no-image .choice-pick { border-top: 0; padding: 36px 20px; font-size: 18px; }
        .choice-pick input { width: 22px; height: 22px; flex-shrink: 0; accent-color: var(--brand); }

        .form-group { margin-bottom: 20px; max-width: 460px; }
        label.field { display: block; font-weight: 600; margin-bottom: 8px; }
        input[type="text"] {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: 2px solid var(--border);
            font-family: inherit;
            font-size: 16px;
        }
        input[type="text"]:focus { outline: none; border-color: var(--brand); }
        .help { color: var(--muted); font-size: 13px; margin-top: 6px; }
        .submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 460px;
            padding: 15px 28px;
            border: none;
            border-radius: 12px;
            background: var(--brand);
            color: #fff;
            font: 700 16px 'Montserrat', sans-serif;
            cursor: pointer;
        }
        .submit:hover { background: var(--brand-dark); }
        .alert { padding: 14px 16px; border-radius: 12px; margin-bottom: 22px; font-weight: 600; }
        .alert-error { background: rgba(220, 53, 69, 0.1); color: #b02a37; }
        .privacy-note { color: var(--muted); font-size: 12px; margin-top: 18px; }
        .privacy-note a { color: var(--brand-dark); }
        .back { display: inline-block; margin-top: 18px; color: var(--brand-dark); font-weight: 600; text-decoration: none; }

        dialog.lightbox {
            width: min(96vw, 1100px);
            margin: auto;
            padding: 16px;
            border: 0;
            border-radius: 18px;
            background: var(--surface);
            color: var(--ink);
        }
        dialog.lightbox::backdrop { background: rgba(20, 20, 20, 0.78); }
        .lightbox-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .lightbox-title { font-size: 18px; font-weight: 700; }
        .lightbox-stage {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f1ec;
            border-radius: 12px;
            padding: 12px;
        }
        .lightbox-stage img {
            display: block;
            max-width: 100%;
            max-height: 68vh;
            object-fit: contain;
        }
        .lightbox-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 14px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 20px;
            border: 2px solid var(--brand);
            border-radius: 12px;
            background: var(--brand);
            color: #fff;
            font: 700 15px 'Montserrat', sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        .btn:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
        .btn-ghost { background: transparent; color: var(--ink); border-color: var(--border); }
        .btn-ghost:hover { background: #f4f1ec; border-color: var(--border); }
        .lightbox-actions .spacer { flex: 1; }

        @media (max-width: 600px) {
            .options { gap: 16px; }
            .submit, .form-group { max-width: none; }
        }
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
                <div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$poll['is_active']): ?>
                <div class="alert alert-error">Ce sondage est actuellement fermé au vote.</div>
            <?php elseif (empty($options)): ?>
                <p class="subtitle">Ce sondage ne contient pas encore d'options.</p>
            <?php else: ?>
                <?php
                $selected_option = (int)($_POST['option_id'] ?? 0);
                $has_images = count(array_filter($options, static fn($o) => !empty($o['image_path']))) > 0;
                ?>
                <?php if ($has_images): ?>
                    <p class="hint">Touchez une image pour l'agrandir, puis cochez celle que vous préférez.</p>
                <?php endif; ?>

                <form method="POST">
                    <div class="options">
                        <?php foreach ($options as $opt): ?>
                            <?php $has_image = !empty($opt['image_path']); ?>
                            <div class="choice<?php echo $has_image ? '' : ' no-image'; ?>">
                                <?php if ($has_image): ?>
                                    <button type="button" class="choice-media"
                                            data-zoom="<?php echo htmlspecialchars($opt['image_path']); ?>"
                                            data-label="<?php echo htmlspecialchars($opt['label']); ?>"
                                            data-option="<?php echo (int)$opt['id']; ?>"
                                            aria-label="Agrandir l'image : <?php echo htmlspecialchars($opt['label']); ?>">
                                        <img src="<?php echo htmlspecialchars($opt['image_path']); ?>" alt="<?php echo htmlspecialchars($opt['label']); ?>" loading="lazy" decoding="async">
                                        <span class="zoom-hint" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                                            Agrandir
                                        </span>
                                    </button>
                                <?php endif; ?>
                                <label class="choice-pick">
                                    <input type="radio" name="option_id" id="option-<?php echo (int)$opt['id']; ?>" value="<?php echo (int)$opt['id']; ?>" required <?php echo $selected_option === (int)$opt['id'] ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($opt['label']); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group">
                        <label class="field" for="licence_number">Votre numéro de licence FFT</label>
                        <input type="text" id="licence_number" name="licence_number" required autocomplete="off" placeholder="Ex: 1234567A" value="<?php echo htmlspecialchars((string)($_POST['licence_number'] ?? '')); ?>">
                        <p class="help">Utilisé uniquement pour vérifier que vous êtes bien licencié au club et limiter le vote à une seule participation par personne.</p>
                    </div>

                    <button type="submit" class="submit">Voter</button>

                    <p class="privacy-note">
                        Votre numéro de licence est conservé le temps du sondage pour éviter les votes multiples.
                        Voir notre <a href="/politique-confidentialite.html" target="_blank" rel="noopener">politique de confidentialité</a>.
                    </p>
                </form>

                <?php if ($has_images): ?>
                    <dialog class="lightbox" id="lightbox" aria-labelledby="lightbox-title">
                        <div class="lightbox-bar">
                            <p class="lightbox-title" id="lightbox-title"></p>
                            <button type="button" class="btn btn-ghost" data-close>Fermer</button>
                        </div>
                        <div class="lightbox-stage"><img id="lightbox-image" src="" alt=""></div>
                        <div class="lightbox-actions">
                            <button type="button" class="btn" id="lightbox-choose">Choisir ce logo</button>
                            <a class="btn btn-ghost" id="lightbox-full" href="#" target="_blank" rel="noopener">Ouvrir en taille réelle</a>
                        </div>
                    </dialog>

                    <script>
                        (function () {
                            var dialog = document.getElementById('lightbox');
                            var image = document.getElementById('lightbox-image');
                            var title = document.getElementById('lightbox-title');
                            var full = document.getElementById('lightbox-full');
                            var choose = document.getElementById('lightbox-choose');
                            var currentOption = null;

                            document.querySelectorAll('[data-zoom]').forEach(function (button) {
                                button.addEventListener('click', function () {
                                    if (typeof dialog.showModal !== 'function') {
                                        window.open(button.dataset.zoom, '_blank', 'noopener');
                                        return;
                                    }
                                    currentOption = button.dataset.option;
                                    image.src = button.dataset.zoom;
                                    image.alt = button.dataset.label;
                                    title.textContent = button.dataset.label;
                                    full.href = button.dataset.zoom;
                                    dialog.showModal();
                                });
                            });

                            dialog.addEventListener('click', function (event) {
                                if (event.target === dialog) { dialog.close(); }
                            });
                            dialog.querySelector('[data-close]').addEventListener('click', function () { dialog.close(); });
                            choose.addEventListener('click', function () {
                                var radio = document.getElementById('option-' + currentOption);
                                if (radio) { radio.checked = true; }
                                dialog.close();
                            });
                        })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
