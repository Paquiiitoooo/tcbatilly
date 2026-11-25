<?php
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';
require_login();

$db = get_db();
$page_title = 'Sondages';
$page_description = 'Créez un sondage (ex: vote du nouveau logo), gérez les options et la liste des licenciés autorisés à voter.';

$upload_base_dir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'polls' . DIRECTORY_SEPARATOR;
$upload_base_url = '/assets/uploads/polls/';

$ensureUploadDir = static function () use ($upload_base_dir) {
    if (!is_dir($upload_base_dir)) {
        mkdir($upload_base_dir, 0755, true);
    }
};

$sanitizeFileName = static function (string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return strtolower($name);
};

$handleImageUpload = function (array $file, ?string $existing_path = null) use ($ensureUploadDir, $upload_base_dir, $upload_base_url, $sanitizeFileName) {
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing_path;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur lors de l\'upload de l\'image.');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Le fichier est trop volumineux.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException('Extension de fichier non autorisée. Formats acceptés: ' . implode(', ', ALLOWED_EXTENSIONS));
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('Le fichier doit être une image valide.');
    }

    $ensureUploadDir();

    $basename = $sanitizeFileName(pathinfo($file['name'], PATHINFO_FILENAME));
    $targetName = uniqid('poll_', true) . '_' . $basename . '.' . $extension;
    $targetPath = $upload_base_dir . $targetName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Impossible d\'enregistrer le fichier uploadé.');
    }

    if ($existing_path && strpos($existing_path, $upload_base_url) === 0) {
        $oldPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), DIRECTORY_SEPARATOR) . $existing_path;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    return $upload_base_url . $targetName;
};

$normalizeLicence = static function (string $value): string {
    $value = strtoupper(trim($value));
    return preg_replace('/\s+/', '', $value);
};

$active_tab = in_array($_GET['tab'] ?? '', ['polls', 'options', 'licencies', 'results'], true) ? $_GET['tab'] : 'polls';
$current_poll_id = isset($_GET['poll']) ? (int)$_GET['poll'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_tab = $_POST['redirect_tab'] ?? $active_tab;
    $redirect_poll = (int)($_POST['redirect_poll'] ?? $_POST['poll_id'] ?? $current_poll_id);
    $back = 'polls.php?tab=' . urlencode($redirect_tab) . ($redirect_poll ? '&poll=' . $redirect_poll : '');

    try {
        if ($action === 'poll_create' || $action === 'poll_update') {
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($title === '') {
                throw new RuntimeException('Le titre du sondage est requis.');
            }

            if ($action === 'poll_update') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare('UPDATE polls SET title = ?, description = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$title, $description, $is_active, $id]);
                log_activity($db, $_SESSION['user_id'], 'update', 'poll', $id, $title);
                redirect('polls.php?tab=polls&success=' . urlencode('Sondage mis à jour.'));
            } else {
                $stmt = $db->prepare('INSERT INTO polls (title, description, is_active) VALUES (?, ?, ?)');
                $stmt->execute([$title, $description, $is_active]);
                $newId = $db->lastInsertId();
                log_activity($db, $_SESSION['user_id'], 'create', 'poll', $newId, $title);
                redirect('polls.php?tab=options&poll=' . $newId . '&success=' . urlencode('Sondage créé. Ajoutez maintenant les options.'));
            }
        }

        if ($action === 'poll_delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT image_path FROM poll_options WHERE poll_id = ?');
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $opt) {
                if (!empty($opt['image_path']) && strpos($opt['image_path'], $upload_base_url) === 0) {
                    $filePath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), DIRECTORY_SEPARATOR) . $opt['image_path'];
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
            $stmt = $db->prepare('DELETE FROM polls WHERE id = ?');
            $stmt->execute([$id]);
            log_activity($db, $_SESSION['user_id'], 'delete', 'poll', $id);
            redirect('polls.php?tab=polls&success=' . urlencode('Sondage supprimé.'));
        }

        if ($action === 'option_create' || $action === 'option_update') {
            $poll_id = (int)($_POST['poll_id'] ?? 0);
            $label = sanitize($_POST['label'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);

            if ($poll_id === 0 || $label === '') {
                throw new RuntimeException('Le libellé de l\'option est requis.');
            }

            $image_path = null;
            if ($action === 'option_update') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare('SELECT image_path FROM poll_options WHERE id = ?');
                $stmt->execute([$id]);
                $current = $stmt->fetch();
                $image_path = $current ? $current['image_path'] : null;
                $image_path = $handleImageUpload($_FILES['image'] ?? [], $image_path);

                $stmt = $db->prepare('UPDATE poll_options SET label = ?, image_path = ?, display_order = ? WHERE id = ?');
                $stmt->execute([$label, $image_path, $display_order, $id]);
                log_activity($db, $_SESSION['user_id'], 'update', 'poll_option', $id, $label);
            } else {
                $image_path = $handleImageUpload($_FILES['image'] ?? []);
                $stmt = $db->prepare('INSERT INTO poll_options (poll_id, label, image_path, display_order) VALUES (?, ?, ?, ?)');
                $stmt->execute([$poll_id, $label, $image_path, $display_order]);
                log_activity($db, $_SESSION['user_id'], 'create', 'poll_option', $db->lastInsertId(), $label);
            }
            redirect('polls.php?tab=options&poll=' . $poll_id . '&success=' . urlencode('Option enregistrée.'));
        }

        if ($action === 'option_delete') {
            $id = (int)($_POST['id'] ?? 0);
            $poll_id = (int)($_POST['poll_id'] ?? 0);
            $stmt = $db->prepare('SELECT image_path FROM poll_options WHERE id = ?');
            $stmt->execute([$id]);
            $opt = $stmt->fetch();
            if ($opt) {
                if (!empty($opt['image_path']) && strpos($opt['image_path'], $upload_base_url) === 0) {
                    $filePath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), DIRECTORY_SEPARATOR) . $opt['image_path'];
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
                $stmt = $db->prepare('DELETE FROM poll_options WHERE id = ?');
                $stmt->execute([$id]);
                log_activity($db, $_SESSION['user_id'], 'delete', 'poll_option', $id);
            }
            redirect('polls.php?tab=options&poll=' . $poll_id . '&success=' . urlencode('Option supprimée.'));
        }

        if ($action === 'licence_add') {
            $licence = $normalizeLicence($_POST['licence_number'] ?? '');
            $full_name = sanitize($_POST['full_name'] ?? '');
            if ($licence === '') {
                throw new RuntimeException('Le numéro de licence est requis.');
            }
            $stmt = $db->prepare('SELECT id FROM poll_licencies WHERE licence_number = ?');
            $stmt->execute([$licence]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Ce numéro de licence est déjà dans la liste.');
            }
            $stmt = $db->prepare('INSERT INTO poll_licencies (licence_number, full_name) VALUES (?, ?)');
            $stmt->execute([$licence, $full_name !== '' ? $full_name : null]);
            log_activity($db, $_SESSION['user_id'], 'create', 'poll_licencie', $db->lastInsertId(), $licence);
            redirect('polls.php?tab=licencies&success=' . urlencode('Licencié ajouté.'));
        }

        if ($action === 'licence_import') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Merci de sélectionner un fichier CSV.');
            }
            if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Erreur lors de l\'upload du fichier.');
            }

            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($handle === false) {
                throw new RuntimeException('Impossible de lire le fichier.');
            }

            $imported = 0;
            $duplicates = 0;
            $invalid = 0;
            $isFirstLine = true;

            $stmt = $db->prepare('INSERT IGNORE INTO poll_licencies (licence_number, full_name) VALUES (?, ?)');

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $delimiter = (strpos($line, ';') !== false) ? ';' : ',';
                $fields = array_map('trim', explode($delimiter, $line));

                if ($isFirstLine) {
                    $isFirstLine = false;
                    $lower = strtolower($fields[0]);
                    if (strpos($lower, 'licence') !== false || strpos($lower, 'licenc') !== false || strpos($lower, 'numero') !== false || strpos($lower, 'numéro') !== false) {
                        continue;
                    }
                }

                $licence = $normalizeLicence($fields[0] ?? '');
                if ($licence === '') {
                    $invalid++;
                    continue;
                }

                $full_name = isset($fields[1]) ? sanitize($fields[1]) : null;
                $stmt->execute([$licence, $full_name !== '' ? $full_name : null]);
                if ($stmt->rowCount() > 0) {
                    $imported++;
                } else {
                    $duplicates++;
                }
            }
            fclose($handle);

            log_activity($db, $_SESSION['user_id'], 'import', 'poll_licencie', null, "$imported importés, $duplicates déjà présents, $invalid invalides");
            redirect('polls.php?tab=licencies&success=' . urlencode("Import terminé : $imported ajoutés, $duplicates déjà présents, $invalid lignes invalides."));
        }

        if ($action === 'licence_delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM poll_licencies WHERE id = ?');
            $stmt->execute([$id]);
            log_activity($db, $_SESSION['user_id'], 'delete', 'poll_licencie', $id);
            redirect('polls.php?tab=licencies&success=' . urlencode('Licencié retiré de la liste.'));
        }

        if ($action === 'licence_clear') {
            $db->query('DELETE FROM poll_licencies');
            log_activity($db, $_SESSION['user_id'], 'delete', 'poll_licencie', null, 'Liste entière vidée');
            redirect('polls.php?tab=licencies&success=' . urlencode('Liste des licenciés vidée.'));
        }
    } catch (RuntimeException $e) {
        redirect($back . '&error=' . urlencode($e->getMessage()));
    }
}

$polls = $db->query('
    SELECT p.*,
        (SELECT COUNT(*) FROM poll_options o WHERE o.poll_id = p.id) AS options_count,
        (SELECT COUNT(*) FROM poll_votes v WHERE v.poll_id = p.id) AS votes_count
    FROM polls p
    ORDER BY p.id DESC
')->fetchAll();

if ($current_poll_id === 0 && !empty($polls)) {
    $current_poll_id = (int)$polls[0]['id'];
}

$current_poll = null;
foreach ($polls as $p) {
    if ((int)$p['id'] === $current_poll_id) {
        $current_poll = $p;
        break;
    }
}

$options = [];
if ($current_poll) {
    $stmt = $db->prepare('
        SELECT o.*, (SELECT COUNT(*) FROM poll_votes v WHERE v.option_id = o.id) AS votes
        FROM poll_options o
        WHERE o.poll_id = ?
        ORDER BY o.display_order ASC, o.id ASC
    ');
    $stmt->execute([$current_poll_id]);
    $options = $stmt->fetchAll();
}

$licence_count = (int)$db->query('SELECT COUNT(*) c FROM poll_licencies')->fetch()['c'];
$licencies = $db->query('SELECT * FROM poll_licencies ORDER BY imported_at DESC, id DESC LIMIT 500')->fetchAll();

$editing_poll = isset($_GET['edit_poll']);
$poll_being_edited = null;
if ($editing_poll) {
    $stmt = $db->prepare('SELECT * FROM polls WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_poll']]);
    $poll_being_edited = $stmt->fetch();
}

$editing_option = isset($_GET['edit_option']);
$option_being_edited = null;
if ($editing_option) {
    $stmt = $db->prepare('SELECT * FROM poll_options WHERE id = ?');
    $stmt->execute([(int)$_GET['edit_option']]);
    $option_being_edited = $stmt->fetch();
}

$vote_url = $current_poll ? rtrim(SITE_URL, '/') . '/sondage.php?id=' . $current_poll['id'] : '';

include 'includes/header.php';
?>

<div class="content-section" aria-labelledby="polls-tabs">
    <div class="view-tabs" role="tablist" aria-label="Gestion des sondages" id="polls-tabs">
        <button type="button" class="view-tab" id="tab-polls-list" role="tab" aria-controls="panel-polls-list" aria-selected="<?php echo $active_tab === 'polls' ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('poll', 18); ?></span>
            <span>Sondages</span>
        </button>
        <button type="button" class="view-tab" id="tab-polls-options" role="tab" aria-controls="panel-polls-options" aria-selected="<?php echo $active_tab === 'options' ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('edit', 18); ?></span>
            <span>Options & logos</span>
        </button>
        <button type="button" class="view-tab" id="tab-polls-licencies" role="tab" aria-controls="panel-polls-licencies" aria-selected="<?php echo $active_tab === 'licencies' ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('users', 18); ?></span>
            <span>Licenciés autorisés <span class="badge badge-info"><?php echo $licence_count; ?></span></span>
        </button>
        <button type="button" class="view-tab" id="tab-polls-results" role="tab" aria-controls="panel-polls-results" aria-selected="<?php echo $active_tab === 'results' ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('qrcode', 18); ?></span>
            <span>Résultats & lien de vote</span>
        </button>
    </div>

    <div class="tab-panels">
        <!-- ============ TAB: SONDAGES ============ -->
        <section class="tab-panel" id="panel-polls-list" role="tabpanel" aria-labelledby="tab-polls-list" aria-hidden="<?php echo $active_tab === 'polls' ? 'false' : 'true'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo admin_icon('poll', 22); ?>
                    Sondages <span class="badge badge-info"><?php echo count($polls); ?></span>
                </h2>
            </header>

            <?php if (empty($polls)): ?>
                <div class="empty-state" style="text-align:center;padding:48px 20px;color:var(--ink-muted);">
                    <p>Aucun sondage n'a encore été créé.</p>
                </div>
            <?php else: ?>
                <div class="table-container" role="application" aria-label="Tableau des sondages">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Titre</th>
                                <th scope="col">Options</th>
                                <th scope="col">Votes</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($polls as $poll): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($poll['title']); ?></strong></td>
                                    <td><?php echo (int)$poll['options_count']; ?></td>
                                    <td><?php echo (int)$poll['votes_count']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $poll['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $poll['is_active'] ? 'Ouvert au vote' : 'Fermé'; ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <a href="polls.php?tab=options&poll=<?php echo $poll['id']; ?>" class="btn-secondary"><?php echo admin_icon('edit', 15); ?><span>Options</span></a>
                                            <a href="polls.php?tab=results&poll=<?php echo $poll['id']; ?>" class="btn-secondary"><?php echo admin_icon('qrcode', 15); ?><span>Résultats</span></a>
                                            <a href="polls.php?edit_poll=<?php echo $poll['id']; ?>#poll-form" class="btn-ghost"><?php echo admin_icon('edit', 15); ?><span>Modifier</span></a>
                                            <form method="POST" onsubmit="return confirm('Supprimer ce sondage, ses options et tous ses votes ?');">
                                                <input type="hidden" name="action" value="poll_delete">
                                                <input type="hidden" name="id" value="<?php echo $poll['id']; ?>">
                                                <button type="submit" class="btn-danger"><?php echo admin_icon('trash', 15); ?><span>Supprimer</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div id="poll-form" style="margin-top:28px;border-top:1px solid var(--border, #eee);padding-top:24px;">
                <h3 style="margin:0 0 14px;font-size:17px;"><?php echo $poll_being_edited ? 'Modifier le sondage' : 'Créer un sondage'; ?></h3>
                <form method="POST" class="panel-grid">
                    <input type="hidden" name="action" value="<?php echo $poll_being_edited ? 'poll_update' : 'poll_create'; ?>">
                    <?php if ($poll_being_edited): ?><input type="hidden" name="id" value="<?php echo $poll_being_edited['id']; ?>"><?php endif; ?>

                    <div class="form-group">
                        <label for="title">Titre du sondage *</label>
                        <input type="text" id="title" name="title" required placeholder="Vote du nouveau logo du club" value="<?php echo $poll_being_edited ? htmlspecialchars($poll_being_edited['title']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">Description / consignes</label>
                        <textarea id="description" name="description" rows="3" placeholder="Choisissez votre logo préféré parmi les propositions ci-dessous."><?php echo $poll_being_edited ? htmlspecialchars($poll_being_edited['description']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo (!$poll_being_edited || $poll_being_edited['is_active']) ? 'checked' : ''; ?>>
                            Ouvert au vote (visible sur la page publique)
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><?php echo $poll_being_edited ? 'Mettre à jour' : 'Créer le sondage'; ?></button>
                        <?php if ($poll_being_edited): ?><a href="polls.php?tab=polls" class="btn-secondary">Annuler</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <!-- ============ TAB: OPTIONS ============ -->
        <section class="tab-panel" id="panel-polls-options" role="tabpanel" aria-labelledby="tab-polls-options" aria-hidden="<?php echo $active_tab === 'options' ? 'false' : 'true'; ?>">
            <?php if (empty($polls)): ?>
                <p style="color:var(--ink-muted);">Créez d'abord un sondage dans l'onglet « Sondages ».</p>
            <?php else: ?>
                <form method="GET" class="select-bar">
                    <input type="hidden" name="tab" value="options">
                    <label for="poll-select-options">Sondage</label>
                    <select id="poll-select-options" name="poll" onchange="this.form.submit()">
                        <?php foreach ($polls as $poll): ?>
                            <option value="<?php echo $poll['id']; ?>" <?php echo $poll['id'] === $current_poll_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($poll['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($current_poll): ?>
                    <div class="table-container" style="margin-bottom:24px;">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Image</th>
                                    <th scope="col">Libellé</th>
                                    <th scope="col">Ordre</th>
                                    <th scope="col">Votes</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($options)): ?>
                                    <tr><td colspan="5" style="text-align:center;color:var(--ink-muted);">Aucune option pour ce sondage.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($options as $opt): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($opt['image_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($opt['image_path']); ?>" alt="<?php echo htmlspecialchars($opt['label']); ?>" style="height:48px;width:auto;border-radius:8px;">
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Sans image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($opt['label']); ?></strong></td>
                                        <td><?php echo (int)$opt['display_order']; ?></td>
                                        <td><?php echo (int)$opt['votes']; ?></td>
                                        <td>
                                            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                                <a href="polls.php?tab=options&poll=<?php echo $current_poll_id; ?>&edit_option=<?php echo $opt['id']; ?>#option-form" class="btn-ghost"><?php echo admin_icon('edit', 15); ?><span>Modifier</span></a>
                                                <form method="POST" onsubmit="return confirm('Supprimer cette option ?');">
                                                    <input type="hidden" name="action" value="option_delete">
                                                    <input type="hidden" name="id" value="<?php echo $opt['id']; ?>">
                                                    <input type="hidden" name="poll_id" value="<?php echo $current_poll_id; ?>">
                                                    <button type="submit" class="btn-danger"><?php echo admin_icon('trash', 15); ?><span>Supprimer</span></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="option-form">
                        <h3 style="margin:0 0 14px;font-size:17px;"><?php echo $option_being_edited ? 'Modifier l\'option' : 'Ajouter une option'; ?></h3>
                        <form method="POST" enctype="multipart/form-data" class="panel-grid">
                            <input type="hidden" name="action" value="<?php echo $option_being_edited ? 'option_update' : 'option_create'; ?>">
                            <input type="hidden" name="poll_id" value="<?php echo $current_poll_id; ?>">
                            <?php if ($option_being_edited): ?><input type="hidden" name="id" value="<?php echo $option_being_edited['id']; ?>"><?php endif; ?>

                            <div class="panel-grid two-columns">
                                <div class="form-group">
                                    <label for="label">Libellé *</label>
                                    <input type="text" id="label" name="label" required placeholder="Logo A" value="<?php echo $option_being_edited ? htmlspecialchars($option_being_edited['label']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="display_order">Ordre d'affichage</label>
                                    <input type="number" id="display_order" name="display_order" value="<?php echo $option_being_edited ? (int)$option_being_edited['display_order'] : 0; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="image">Image (logo candidat)</label>
                                <input type="file" id="image" name="image" accept="image/*">
                                <small>Formats acceptés : <?php echo implode(', ', ALLOWED_EXTENSIONS); ?>. Taille max : <?php echo round(MAX_UPLOAD_SIZE / 1024 / 1024, 1); ?> MB.</small>
                                <?php if ($option_being_edited && !empty($option_being_edited['image_path'])): ?>
                                    <div style="margin-top:10px;">
                                        <img src="<?php echo htmlspecialchars($option_being_edited['image_path']); ?>" alt="Image actuelle" style="height:60px;border-radius:8px;">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary"><?php echo $option_being_edited ? 'Mettre à jour' : 'Ajouter l\'option'; ?></button>
                                <?php if ($option_being_edited): ?><a href="polls.php?tab=options&poll=<?php echo $current_poll_id; ?>" class="btn-secondary">Annuler</a><?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- ============ TAB: LICENCIÉS ============ -->
        <section class="tab-panel" id="panel-polls-licencies" role="tabpanel" aria-labelledby="tab-polls-licencies" aria-hidden="<?php echo $active_tab === 'licencies' ? 'false' : 'true'; ?>">
            <p style="color:var(--ink-muted);margin-bottom:18px;">
                Cette liste sert à vérifier qu'un numéro de licence saisi au moment du vote correspond bien à un licencié du club.
                Elle est partagée par tous les sondages.
            </p>

            <div class="panel-grid two-columns" style="margin-bottom:24px;">
                <div>
                    <h3 style="margin:0 0 10px;font-size:16px;">Importer un fichier CSV</h3>
                    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                        <input type="hidden" name="action" value="licence_import">
                        <input type="hidden" name="redirect_tab" value="licencies">
                        <input type="file" name="csv_file" accept=".csv,text/csv" required>
                        <small>Une ligne par licencié : <code>numero_licence</code> ou <code>numero_licence,nom</code>. Séparateur virgule ou point-virgule.</small>
                        <button type="submit" class="btn-primary" style="align-self:flex-start;"><?php echo admin_icon('upload', 16); ?><span>Importer</span></button>
                    </form>
                </div>
                <div>
                    <h3 style="margin:0 0 10px;font-size:16px;">Ajouter un licencié</h3>
                    <form method="POST" style="display:flex;flex-direction:column;gap:10px;">
                        <input type="hidden" name="action" value="licence_add">
                        <input type="hidden" name="redirect_tab" value="licencies">
                        <input type="text" name="licence_number" placeholder="Numéro de licence *" required>
                        <input type="text" name="full_name" placeholder="Nom (optionnel)">
                        <button type="submit" class="btn-secondary" style="align-self:flex-start;"><?php echo admin_icon('plus', 16); ?><span>Ajouter</span></button>
                    </form>
                </div>
            </div>

            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:18px;">
                    <?php echo admin_icon('users', 20); ?>
                    Licenciés autorisés <span class="badge badge-info"><?php echo $licence_count; ?></span>
                </h2>
                <?php if ($licence_count > 0): ?>
                    <form method="POST" onsubmit="return confirm('Vider toute la liste des licenciés autorisés ? Les votes déjà enregistrés ne seront pas supprimés.');">
                        <input type="hidden" name="action" value="licence_clear">
                        <input type="hidden" name="redirect_tab" value="licencies">
                        <button type="submit" class="btn-danger"><?php echo admin_icon('trash', 15); ?><span>Vider la liste</span></button>
                    </form>
                <?php endif; ?>
            </header>

            <?php if (empty($licencies)): ?>
                <div class="empty-state" style="text-align:center;padding:32px 20px;color:var(--ink-muted);">
                    <p>Aucun licencié importé pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="table-container" style="max-height:480px;overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Numéro de licence</th>
                                <th scope="col">Nom</th>
                                <th scope="col">Importé le</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($licencies as $lic): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($lic['licence_number']); ?></code></td>
                                    <td><?php echo $lic['full_name'] ? htmlspecialchars($lic['full_name']) : '<span style="color:var(--ink-muted);">—</span>'; ?></td>
                                    <td><?php echo format_date($lic['imported_at'], DATETIME_FORMAT); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Retirer ce licencié de la liste ?');">
                                            <input type="hidden" name="action" value="licence_delete">
                                            <input type="hidden" name="id" value="<?php echo $lic['id']; ?>">
                                            <input type="hidden" name="redirect_tab" value="licencies">
                                            <button type="submit" class="btn-danger"><?php echo admin_icon('trash', 15); ?><span>Retirer</span></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($licence_count > count($licencies)): ?>
                    <p style="color:var(--ink-muted);font-size:13px;margin-top:10px;">Affichage des 500 entrées les plus récentes sur <?php echo $licence_count; ?>.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- ============ TAB: RESULTATS & LIEN ============ -->
        <section class="tab-panel" id="panel-polls-results" role="tabpanel" aria-labelledby="tab-polls-results" aria-hidden="<?php echo $active_tab === 'results' ? 'false' : 'true'; ?>">
            <?php if (empty($polls)): ?>
                <p style="color:var(--ink-muted);">Créez d'abord un sondage dans l'onglet « Sondages ».</p>
            <?php else: ?>
                <form method="GET" class="select-bar">
                    <input type="hidden" name="tab" value="results">
                    <label for="poll-select-results">Sondage</label>
                    <select id="poll-select-results" name="poll" onchange="this.form.submit()">
                        <?php foreach ($polls as $poll): ?>
                            <option value="<?php echo $poll['id']; ?>" <?php echo $poll['id'] === $current_poll_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($poll['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($current_poll): ?>
                    <div class="stat-card" style="margin-bottom:24px;">
                        <h3 style="margin:0 0 6px;font-size:16px;">Lien de vote (un seul lien pour tout le monde)</h3>
                        <p style="color:var(--ink-muted);font-size:13px;margin-bottom:10px;">
                            Partagez ce lien, ou générez un QR code à partir de celui-ci (n'importe quel générateur de QR code gratuit fonctionne), et imprimez/affichez-le au club. Chaque licencié ne peut voter qu'une seule fois, grâce à son numéro de licence.
                        </p>
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <input type="text" readonly value="<?php echo htmlspecialchars($vote_url); ?>" id="vote-url-input" style="flex:1;min-width:260px;padding:10px 12px;border-radius:8px;border:1px solid var(--border, #ddd);font-family:monospace;">
                            <button type="button" class="btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('vote-url-input').value); this.querySelector('span').textContent='Copié !';">
                                <?php echo admin_icon('check', 16); ?><span>Copier le lien</span>
                            </button>
                        </div>
                        <?php if (!$current_poll['is_active']): ?>
                            <p style="margin-top:10px;"><span class="badge badge-danger">Ce sondage est fermé</span> — le lien affichera un message de clôture tant qu'il n'est pas réactivé dans l'onglet « Sondages ».</p>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin:0 0 14px;font-size:17px;">Résultats — <?php echo (int)$current_poll['votes_count']; ?> vote(s)</h3>
                    <?php if (empty($options)): ?>
                        <p style="color:var(--ink-muted);">Ajoutez des options dans l'onglet « Options & logos » pour voir des résultats.</p>
                    <?php else: ?>
                        <?php $total = max(1, (int)$current_poll['votes_count']); ?>
                        <div style="display:flex;flex-direction:column;gap:14px;">
                            <?php foreach ($options as $opt): ?>
                                <?php $pct = round(((int)$opt['votes'] / $total) * 100); ?>
                                <div>
                                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                        <strong><?php echo htmlspecialchars($opt['label']); ?></strong>
                                        <span><?php echo (int)$opt['votes']; ?> vote(s) — <?php echo $pct; ?>%</span>
                                    </div>
                                    <div style="background:rgba(0,0,0,0.06);border-radius:8px;height:16px;overflow:hidden;">
                                        <div style="background:var(--brand, #d1875a);height:100%;width:<?php echo $pct; ?>%;transition:width .3s;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
