<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_login();

$db = get_db();
$page_title = 'Services';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $icon = sanitize($_POST['icon'] ?? '');
        $display_order = intval($_POST['display_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO services (title, description, icon, display_order, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $icon, $display_order, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'service', $db->lastInsertId(), $title);
            redirect('services.php?success=Service créé');
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE services SET title = ?, description = ?, icon = ?, display_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$title, $description, $icon, $display_order, $is_active, $id]);
            log_activity($db, $_SESSION['user_id'], 'update', 'service', $id, $title);
            redirect('services.php?success=Service mis à jour');
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$id]);
        log_activity($db, $_SESSION['user_id'], 'delete', 'service', $id);
        redirect('services.php?success=Service supprimé');
    }
}

$stmt = $db->query("SELECT * FROM services ORDER BY display_order ASC");
$services = $stmt->fetchAll();

$editing = false;
$current = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $current = $stmt->fetch();
}

include 'includes/header.php';
?>

<div class="content-section">
    <h2>⚡ <?php echo $editing ? 'Modifier' : 'Ajouter'; ?> un service</h2>
    <?php if ($editing): ?><a href="services.php" class="btn-secondary" style="float: right; margin-top: -40px;">❌ Annuler</a><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo $current['id']; ?>"><?php endif; ?>

        <div class="form-group">
            <label for="title">Titre du service *</label>
            <input type="text" id="title" name="title" required value="<?php echo $editing ? htmlspecialchars($current['title']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="description">Description *</label>
            <textarea id="description" name="description" required rows="4"><?php echo $editing ? htmlspecialchars($current['description']) : ''; ?></textarea>
        </div>

        <div class="form-grid-responsive">
            <div class="form-group">
                <label for="icon">Icône/Emoji</label>
                <input type="text" id="icon" name="icon" placeholder="⚡" maxlength="10"
                       value="<?php echo $editing ? htmlspecialchars($current['icon']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="display_order">Ordre</label>
                <input type="number" id="display_order" name="display_order" value="<?php echo $editing ? $current['display_order'] : 0; ?>">
            </div>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="is_active" <?php echo (!$editing || $current['is_active']) ? 'checked' : ''; ?>> Service actif</label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">💾 <?php echo $editing ? 'Mettre à jour' : 'Créer'; ?></button>
        </div>
    </form>
</div>

<div class="content-section">
    <h2>📋 Liste des services (<?php echo count($services); ?>)</h2>

    <?php if (empty($services)): ?>
        <p style="text-align: center; color: #999; padding: 40px;">Aucun service trouvé</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Description</th>
                        <th>Ordre</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <?php if ($service['icon']): ?>
                                    <span style="font-size: 20px; margin-right: 8px;"><?php echo htmlspecialchars($service['icon']); ?></span>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($service['title']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars(substr($service['description'], 0, 100)) . '...'; ?></td>
                            <td><?php echo $service['display_order']; ?></td>
                            <td><span class="badge <?php echo $service['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $service['is_active'] ? 'Actif' : 'Inactif'; ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="services.php?edit=<?php echo $service['id']; ?>" class="btn-secondary btn-table-action">✏️</a>
                                    <form method="POST" class="table-action-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                        <button type="submit" class="btn-danger btn-table-action" data-confirm-delete="Supprimer ?">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

