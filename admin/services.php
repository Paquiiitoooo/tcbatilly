<?php
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';
require_login();

$db = get_db();
$page_title = 'Services';
$page_description = 'Gerer les prestations proposees sur le site';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $icon = '';
        $display_order = intval($_POST['display_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'create') {
            $stmt = $db->prepare('INSERT INTO services (title, description, icon, display_order, is_active) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $description, $icon, $display_order, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'service', $db->lastInsertId(), $title);
            redirect('services.php?success=' . urlencode('Service cree'));
        }

        $id = (int)$_POST['id'];
        $stmt = $db->prepare('UPDATE services SET title = ?, description = ?, icon = ?, display_order = ?, is_active = ? WHERE id = ?');
        $stmt->execute([$title, $description, $icon, $display_order, $is_active, $id]);
        log_activity($db, $_SESSION['user_id'], 'update', 'service', $id, $title);
        redirect('services.php?success=' . urlencode('Service mis a jour'));
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('DELETE FROM services WHERE id = ?');
        $stmt->execute([$id]);
        log_activity($db, $_SESSION['user_id'], 'delete', 'service', $id);
        redirect('services.php?success=' . urlencode('Service supprime'));
    }
}

$stmt = $db->query('SELECT * FROM services ORDER BY display_order ASC, id ASC');
$services = $stmt->fetchAll();

$editing = false;
$current = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $stmt = $db->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $current = $stmt->fetch();
}

$form_active = $editing || isset($_GET['action']);

include 'includes/header.php';
?>

<div class="content-section" aria-labelledby="services-tabs">
    <div class="view-tabs" role="tablist" aria-label="Gestion des services" id="services-tabs">
        <button type="button" class="view-tab" id="tab-services-list" role="tab" aria-controls="panel-services-list" aria-selected="<?php echo $form_active ? 'false' : 'true'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('services', 18); ?></span>
            <span>Services en ligne</span>
        </button>
        <button type="button" class="view-tab" id="tab-services-form" role="tab" aria-controls="panel-services-form" aria-selected="<?php echo $form_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo $editing ? admin_icon('edit', 18) : admin_icon('plus', 18); ?></span>
            <span><?php echo $editing ? 'Modifier' : 'Ajouter'; ?> un service</span>
        </button>
    </div>

    <div class="tab-panels">
        <section class="tab-panel" id="panel-services-list" role="tabpanel" aria-labelledby="tab-services-list" aria-hidden="<?php echo $form_active ? 'true' : 'false'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    Services proposes <span class="badge badge-info"><?php echo count($services); ?></span>
                </h2>
                <a href="services.php?action=create" class="btn-secondary" data-switch-tab="tab-services-form"><?php echo admin_icon('plus', 16); ?><span>Nouveau service</span></a>
            </header>

            <?php if (empty($services)): ?>
                <div class="empty-state" style="text-align:center;padding:48px 20px;color:var(--ink-muted);">
                    <p>Aucun service reference.</p>
                    <p><a class="btn-primary" href="services.php?action=create" data-switch-tab="tab-services-form">Ajouter un service</a></p>
                </div>
            <?php else: ?>
                <div class="table-container" role="application" aria-label="Tableau des services">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Titre</th>
                                <th scope="col">Description</th>
                                <th scope="col">Ordre</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($service['title'] ?? ''); ?></strong></td>
                                    <td style="max-width:360px;">
                                        <?php
                                        $description = $service['description'] ?? '';
                                        $excerpt = mb_substr($description, 0, 120);
                                        if (mb_strlen($description) > 120) {
                                            $excerpt .= '...';
                                        }
                                        echo htmlspecialchars($excerpt);
                                        ?>
                                    </td>
                                    <td><?php echo $service['display_order']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $service['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $service['is_active'] ? 'Actif' : 'Inactif'; ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <a href="services.php?edit=<?php echo $service['id']; ?>" class="btn-secondary" data-switch-tab="tab-services-form"><?php echo admin_icon('edit', 15); ?><span>Modifier</span></a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                                <button type="submit" class="btn-danger" data-confirm-delete="Supprimer ce service ?"><?php echo admin_icon('trash', 15); ?><span>Supprimer</span></button>
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

        <section class="tab-panel" id="panel-services-form" role="tabpanel" aria-labelledby="tab-services-form" aria-hidden="<?php echo $form_active ? 'false' : 'true'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo $editing ? admin_icon('edit', 22) : admin_icon('plus', 22); ?>
                    <?php echo $editing ? 'Modifier un service' : 'Ajouter un service'; ?>
                </h2>
                <?php if ($editing): ?>
                    <a href="services.php" class="btn-ghost" data-switch-tab="tab-services-list"><?php echo admin_icon('arrow-left', 16); ?><span>Retour à la liste</span></a>
                <?php endif; ?>
            </header>

            <form method="POST" class="panel-grid" aria-describedby="services-form-help">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo $current['id']; ?>"><?php endif; ?>

                <div class="form-group">
                    <label for="title">Titre du service *</label>
                    <input type="text" id="title" name="title" required value="<?php echo $editing ? htmlspecialchars($current['title'] ?? '') : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" required rows="5" aria-describedby="services-description-help"><?php echo $editing ? htmlspecialchars($current['description'] ?? '') : ''; ?></textarea>
                    <small id="services-description-help">Decrivez clairement ce que propose ce service aux membres.</small>
                </div>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="display_order">Ordre d'affichage</label>
                        <input type="number" id="display_order" name="display_order" value="<?php echo $editing ? $current['display_order'] : 0; ?>">
                        <small>0 s'affichera en premier.</small>
                    </div>
                    <div class="form-group" style="align-self:center;">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo (!$editing || $current['is_active']) ? 'checked' : ''; ?>>
                            Service actif (visible sur le site)
                        </label>
                    </div>
                </div>

                <p id="services-form-help" style="color:var(--ink-muted);font-size:13px;">Les services actifs alimentent la section services du site public.</p>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editing ? 'Mettre a jour le service' : 'Creer le service'; ?></button>
                    <a href="services.php" class="btn-secondary" data-switch-tab="tab-services-list">Annuler</a>
                </div>
            </form>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
