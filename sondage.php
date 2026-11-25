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
    <meta name="theme-color" content="#14148c">
    <link rel="icon" type="image/jpeg" href="./assets/images/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #14148c;
            --navy-dark: #0b0b63;
            --yellow: #e8e55a;
            --ink: #0d0e24;
            --muted: #565b78;
            --line: #d6d9e6;
            --danger: #b3261e;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Archivo', system-ui, -apple-system, 'Segoe UI', sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: var(--ink);
            background: #fff;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--navy); font-weight: 700; }
        :focus-visible { outline: 3px solid var(--navy); outline-offset: 2px; }

        .topbar { background: var(--navy); color: #fff; }
        .topbar-inner {
            display: flex;
            align-items: center;
            gap: 14px;
            max-width: 1100px;
            margin: 0 auto;
            padding: 12px 20px;
            font-weight: 800;
            font-stretch: 115%;
        }
        .topbar img { display: block; width: 44px; height: 44px; background: #fff; }
        .topbar-line { height: 8px; background: var(--yellow); }

        main { max-width: 1100px; margin: 0 auto; padding: 36px 20px 64px; }
        h1 {
            font-size: clamp(28px, 5vw, 44px);
            font-weight: 900;
            font-stretch: 125%;
            letter-spacing: -0.02em;
            line-height: 1.08;
            margin-bottom: 12px;
        }
        .intro { max-width: 60ch; margin-bottom: 8px; }
        .hint { color: var(--muted); margin-bottom: 26px; }

        .options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
            gap: 22px;
            margin-bottom: 36px;
        }
        .choice {
            display: flex;
            flex-direction: column;
            border: 3px solid var(--navy);
            background: #fff;
        }
        .choice:has(input:checked) { background: var(--yellow); }
        .choice-media {
            position: relative;
            display: block;
            width: 100%;
            aspect-ratio: 1 / 1;
            padding: 0;
            border: 0;
            border-bottom: 3px solid var(--navy);
            background: #fff;
            cursor: zoom-in;
        }
        .choice-media img {
            position: absolute;
            inset: 14px;
            width: calc(100% - 28px);
            height: calc(100% - 28px);
            object-fit: contain;
        }
        .choice-media:focus-visible { outline-offset: -6px; }
        .zoom-hint {
            position: absolute;
            right: 0;
            bottom: 0;
            padding: 5px 12px;
            background: var(--navy);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
        }
        .choice-pick {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: auto;
            padding: 16px 18px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
        }
        .choice.no-image .choice-pick { padding: 44px 20px; font-size: 22px; }
        .choice-pick input { width: 24px; height: 24px; flex-shrink: 0; accent-color: var(--navy); }

        .form-group { max-width: 460px; margin-bottom: 20px; }
        label.field { display: block; margin-bottom: 6px; font-weight: 700; }
        input[type="text"] {
            width: 100%;
            height: 50px;
            padding: 0 14px;
            border: 3px solid var(--navy);
            border-radius: 0;
            font: 500 18px 'Archivo', sans-serif;
        }
        input[type="text"]:focus { outline: none; box-shadow: 0 0 0 4px var(--yellow); }
        .help { margin-top: 6px; font-size: 14px; color: var(--muted); }
        .submit {
            display: block;
            width: 100%;
            max-width: 460px;
            height: 54px;
            border: 3px solid var(--navy);
            border-radius: 0;
            background: var(--navy);
            color: #fff;
            font: 800 18px 'Archivo', sans-serif;
            cursor: pointer;
        }
        .submit:hover { background: var(--yellow); color: var(--navy-dark); }
        .alert {
            margin-bottom: 24px;
            padding: 12px 16px;
            background: var(--danger);
            color: #fff;
            font-weight: 700;
        }
        .privacy-note { max-width: 60ch; margin-top: 22px; font-size: 14px; color: var(--muted); }
        .back { display: inline-block; margin-top: 20px; }

        dialog.lightbox {
            width: min(96vw, 1100px);
            margin: auto;
            padding: 16px;
            border: 4px solid var(--navy);
            border-radius: 0;
            background: #fff;
            color: var(--ink);
        }
        dialog.lightbox::backdrop { background: rgba(13, 14, 36, 0.8); }
        .lightbox-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .lightbox-title { font-size: 20px; font-weight: 800; font-stretch: 112%; }
        .lightbox-stage {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border: 2px solid var(--line);
            background: #fff;
        }
        .lightbox-stage img { display: block; max-width: 100%; max-height: 66vh; object-fit: contain; }
        .lightbox-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 14px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 44px;
            padding: 0 20px;
            border: 3px solid var(--navy);
            border-radius: 0;
            background: var(--navy);
            color: #fff;
            font: 800 16px 'Archivo', sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        .btn:hover { background: var(--yellow); color: var(--navy-dark); }
        .btn-ghost { background: #fff; color: var(--navy); }

        @media (max-width: 600px) {
            .options { gap: 18px; }
            .form-group, .submit { max-width: none; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-inner">
            <img src="./assets/images/logo.jpg" alt="" width="44" height="44">
            <span>Tennis Club de Batilly</span>
        </div>
        <div class="topbar-line"></div>
    </div>

    <main>
        <?php if (!$poll): ?>
            <h1>Pas de sondage en cours</h1>
            <p class="intro">Aucun vote n'est ouvert pour le moment.</p>
            <a class="back" href="/">Retour au site</a>
        <?php elseif ($success): ?>
            <h1>Vote enregistré</h1>
            <p class="intro">Merci. Votre choix pour « <?php echo htmlspecialchars($poll['title']); ?> » est pris en compte.</p>
            <a class="back" href="/">Retour au site</a>
        <?php else: ?>
            <h1><?php echo htmlspecialchars($poll['title']); ?></h1>
            <?php if (!empty($poll['description'])): ?>
                <p class="intro"><?php echo nl2br(htmlspecialchars($poll['description'])); ?></p>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$poll['is_active']): ?>
                <div class="alert">Ce vote est terminé.</div>
            <?php elseif (empty($options)): ?>
                <p class="intro">Ce sondage n'a pas encore de propositions.</p>
            <?php else: ?>
                <?php
                $selected_option = (int)($_POST['option_id'] ?? 0);
                $has_images = count(array_filter($options, static fn($o) => !empty($o['image_path']))) > 0;
                ?>
                <?php if ($has_images): ?>
                    <p class="hint">Touchez une image pour la voir en grand.</p>
                <?php else: ?>
                    <div style="height: 20px"></div>
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
                                        <span class="zoom-hint" aria-hidden="true">Agrandir</span>
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
                        <label class="field" for="licence_number">Numéro de licence</label>
                        <input type="text" id="licence_number" name="licence_number" required autocomplete="off" placeholder="1234567A" value="<?php echo htmlspecialchars((string)($_POST['licence_number'] ?? '')); ?>">
                        <p class="help">Une seule voix par licence.</p>
                    </div>

                    <button type="submit" class="submit">Voter</button>

                    <p class="privacy-note">
                        Le numéro de licence sert à vérifier votre adhésion et à éviter les doubles votes. Il est conservé le temps du sondage.
                        <a href="/politique-confidentialite" target="_blank" rel="noopener">Politique de confidentialité</a>
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
                            <button type="button" class="btn" id="lightbox-choose">Choisir celui-ci</button>
                            <a class="btn btn-ghost" id="lightbox-full" href="#" target="_blank" rel="noopener">Taille réelle</a>
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
    </main>
</body>
</html>
