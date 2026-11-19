<?php
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';
require_login();

$db = get_db();
$page_title = 'Sponsors & partenaires';
$page_description = 'Ajoutez et gérez les logos de vos partenaires.';

$upload_base_dir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sponsors' . DIRECTORY_SEPARATOR;
$upload_base_url = '/assets/uploads/sponsors/';

$ensureUploadDir = static function () use ($upload_base_dir) {
    if (!is_dir($upload_base_dir)) {
        mkdir($upload_base_dir, 0755, true);
    }
};

$sanitizeFileName = static function (string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return strtolower($name);
};

$handleLogoUpload = function (array $file, ?string $existing_path = null) use ($ensureUploadDir, $upload_base_dir, $upload_base_url, $sanitizeFileName) {
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing_path;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur lors de l\'upload du logo.');
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
    $targetName = uniqid('sponsor_', true) . '_' . $basename . '.' . $extension;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' || $action === 'update') {
            $name = sanitize($_POST['name']);
            $website = filter_var(trim($_POST['website']), FILTER_SANITIZE_URL);
            $display_order = intval($_POST['display_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '' || $website === '') {
                throw new RuntimeException('Le nom et le site web du sponsor sont requis.');
            }
            if (!filter_var($website, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Le site web du sponsor n\'est pas une URL valide.');
            }

            $logo_path = null;
            if ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare('SELECT * FROM sponsors WHERE id = ?');
                $stmt->execute([$id]);
                $current = $stmt->fetch();
                if (!$current) {
                    throw new RuntimeException('Sponsor introuvable.');
                }
                $logo_path = $current['logo_path'];
                $logo_path = $handleLogoUpload($_FILES['logo'] ?? [], $logo_path);

                $stmt = $db->prepare('UPDATE sponsors SET name = ?, website = ?, logo_path = ?, display_order = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$name, $website, $logo_path, $display_order, $is_active, $id]);
                log_activity($db, $_SESSION['user_id'], 'update', 'sponsor', $id, $name);
                redirect('sponsors.php?success=' . urlencode('Sponsor mis à jour.'));
            } else {
                $logo_path = $handleLogoUpload($_FILES['logo'] ?? []);
                if (!$logo_path) {
                    throw new RuntimeException('Merci de sélectionner un logo.');
                }

                $stmt = $db->prepare('INSERT INTO sponsors (name, website, logo_path, display_order, is_active) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $website, $logo_path, $display_order, $is_active]);
                log_activity($db, $_SESSION['user_id'], 'create', 'sponsor', $db->lastInsertId(), $name);
                redirect('sponsors.php?success=' . urlencode('Sponsor ajouté avec succès.'));
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('SELECT logo_path FROM sponsors WHERE id = ?');
            $stmt->execute([$id]);
            $sponsor = $stmt->fetch();
            if ($sponsor) {
                if ($sponsor['logo_path'] && strpos($sponsor['logo_path'], $upload_base_url) === 0) {
                    $filePath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), DIRECTORY_SEPARATOR) . $sponsor['logo_path'];
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
                $stmt = $db->prepare('DELETE FROM sponsors WHERE id = ?');
                $stmt->execute([$id]);
                log_activity($db, $_SESSION['user_id'], 'delete', 'sponsor', $id);
            }
            redirect('sponsors.php?success=' . urlencode('Sponsor supprimé.'));
        }
    } catch (RuntimeException $e) {
        redirect('sponsors.php?error=' . urlencode($e->getMessage()));
    }
}

$stmt = $db->query('SELECT * FROM sponsors ORDER BY display_order ASC, id ASC');
$sponsors = $stmt->fetchAll();

$editing = false;
$current = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $stmt = $db->prepare('SELECT * FROM sponsors WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $current = $stmt->fetch();
}

$form_active = $editing || isset($_GET['action']);

include 'includes/header.php';
?>

<div class="content-section" aria-labelledby="sponsors-tabs">
    <div class="view-tabs" role="tablist" aria-label="Gestion des sponsors" id="sponsors-tabs">
        <button type="button" class="view-tab" id="tab-sponsors-list" role="tab" aria-controls="panel-sponsors-list" aria-selected="<?php echo $form_active ? 'false' : 'true'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('sponsors', 18); ?></span>
            <span>Liste des sponsors</span>
        </button>
        <button type="button" class="view-tab" id="tab-sponsors-form" role="tab" aria-controls="panel-sponsors-form" aria-selected="<?php echo $form_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo $editing ? admin_icon('edit', 18) : admin_icon('plus', 18); ?></span>
            <span><?php echo $editing ? 'Modifier' : 'Ajouter'; ?> un sponsor</span>
        </button>
    </div>

    <div class="tab-panels">
        <section class="tab-panel" id="panel-sponsors-list" role="tabpanel" aria-labelledby="tab-sponsors-list" aria-hidden="<?php echo $form_active ? 'true' : 'false'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo admin_icon('sponsors', 22); ?>
                    Sponsors actifs <span class="badge badge-info"><?php echo count($sponsors); ?></span>
                </h2>
                <a href="sponsors.php?action=create" class="btn-secondary" data-switch-tab="tab-sponsors-form"><?php echo admin_icon('plus', 16); ?><span>Nouveau sponsor</span></a>
            </header>

            <?php if (empty($sponsors)): ?>
                <div class="empty-state" style="text-align:center;padding:48px 20px;color:var(--ink-muted);">
                    <p>Aucun sponsor n'a encore été ajouté.</p>
                    <p><a class="btn-primary" href="sponsors.php?action=create" data-switch-tab="tab-sponsors-form">Ajouter un partenaire</a></p>
                </div>
            <?php else: ?>
                <div class="table-container" role="application" aria-label="Tableau des sponsors">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Logo</th>
                                <th scope="col">Nom</th>
                                <th scope="col">Site web</th>
                                <th scope="col">Ordre</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sponsors as $sponsor): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($sponsor['logo_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($sponsor['logo_path']); ?>" alt="Logo <?php echo htmlspecialchars($sponsor['name']); ?>" class="sponsor-logo-thumb">
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Sans logo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($sponsor['name']); ?></strong></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($sponsor['website']); ?>" target="_blank" rel="noopener" class="stat-link">
                                            <span><?php echo htmlspecialchars(parse_url($sponsor['website'], PHP_URL_HOST) ?? $sponsor['website']); ?></span><?php echo admin_icon('external', 14); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $sponsor['display_order']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $sponsor['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $sponsor['is_active'] ? 'Actif' : 'Inactif'; ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <a href="sponsors.php?edit=<?php echo $sponsor['id']; ?>" class="btn-secondary" data-switch-tab="tab-sponsors-form"><?php echo admin_icon('edit', 15); ?><span>Modifier</span></a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $sponsor['id']; ?>">
                                                <button type="submit" class="btn-danger" data-confirm-delete="Supprimer ce sponsor ?"><?php echo admin_icon('trash', 15); ?><span>Supprimer</span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="tab-panel" id="panel-sponsors-form" role="tabpanel" aria-labelledby="tab-sponsors-form" aria-hidden="<?php echo $form_active ? 'false' : 'true'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo $editing ? admin_icon('edit', 22) : admin_icon('plus', 22); ?>
                    <?php echo $editing ? 'Modifier un sponsor' : 'Ajouter un sponsor'; ?>
                </h2>
                <?php if ($editing): ?>
                    <a href="sponsors.php" class="btn-ghost" data-switch-tab="tab-sponsors-list"><?php echo admin_icon('arrow-left', 16); ?><span>Retour à la liste</span></a>
                <?php endif; ?>
            </header>

            <?php if (!empty($sponsors)): ?>
                <div class="sponsor-ribbon" style="margin-bottom:18px;">
                    <?php foreach ($sponsors as $sponsor): ?>
                        <?php if (!$sponsor['is_active'] || empty($sponsor['logo_path'])) continue; ?>
                        <img src="<?php echo htmlspecialchars($sponsor['logo_path']); ?>" alt="<?php echo htmlspecialchars($sponsor['name']); ?>" class="sponsor-logo-thumb" title="<?php echo htmlspecialchars($sponsor['name']); ?>">
                    <?php endforeach; ?>
                    <span style="color:var(--ink-muted);font-size:13px;">Aperçu du ruban affiché sur le site</span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="panel-grid" aria-describedby="sponsors-form-help">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo $current['id']; ?>"><?php endif; ?>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="name">Nom du sponsor *</label>
                        <input type="text" id="name" name="name" required value="<?php echo $editing ? htmlspecialchars($current['name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="website">Site web *</label>
                        <input type="url" id="website" name="website" required placeholder="https://..." value="<?php echo $editing ? htmlspecialchars($current['website']) : ''; ?>">
                    </div>
                </div>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="display_order">Ordre d'affichage</label>
                        <input type="number" id="display_order" name="display_order" value="<?php echo $editing ? $current['display_order'] : 0; ?>">
                        <small>0 = premier, valeurs croissantes.</small>
                    </div>
                    <div class="form-group" style="align-self:center;">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo (!$editing || $current['is_active']) ? 'checked' : ''; ?>>
                            Sponsor actif (visible sur le site)
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="logo">Logo (PNG, JPG, WEBP) *</label>
                    <input type="file" id="logo" name="logo" accept="image/*" <?php echo $editing ? '' : 'required'; ?>>
                    <small>Formats acceptés : PNG, WEBP, AVIF (fond transparent recommandé). Taille max : <?php echo round(MAX_UPLOAD_SIZE / 1024 / 1024, 1); ?> MB.</small>
                    <?php if ($editing && !empty($current['logo_path'])): ?>
                        <div class="sponsor-logo-preview">
                            <img src="<?php echo htmlspecialchars($current['logo_path']); ?>" alt="Logo actuel">
                            <span>Logo actuel</span>
                        </div>
                    <?php endif; ?>
                </div>

                <p id="sponsors-form-help" style="color:var(--ink-muted);font-size:13px;">Les sponsors actifs apparaîtront dans le ruban déroulant du site public.</p>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editing ? 'Mettre à jour le sponsor' : 'Ajouter le sponsor'; ?></button>
                    <a href="sponsors.php" class="btn-secondary" data-switch-tab="tab-sponsors-list">Annuler</a>
                </div>
            </form>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
