<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_login();

$db = get_db();
$page_title = 'Entraîneurs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $name = sanitize($_POST['name']);
        $role = sanitize($_POST['role']);
        $image_url = sanitize($_POST['image_url']);
        $bio = sanitize($_POST['bio'] ?? '');
        $display_order = intval($_POST['display_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO coaches (name, role, image_url, bio, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $role, $image_url, $bio, $display_order, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'coach', $db->lastInsertId(), $name);
            redirect('coaches.php?success=Entraîneur créé');
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE coaches SET name = ?, role = ?, image_url = ?, bio = ?, display_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $role, $image_url, $bio, $display_order, $is_active, $id]);
            log_activity($db, $_SESSION['user_id'], 'update', 'coach', $id, $name);
            redirect('coaches.php?success=Entraîneur mis à jour');
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM coaches WHERE id = ?");
        $stmt->execute([$id]);
        log_activity($db, $_SESSION['user_id'], 'delete', 'coach', $id);
        redirect('coaches.php?success=Entraîneur supprimé');
    }
}

$stmt = $db->query("SELECT * FROM coaches ORDER BY display_order ASC");
$coaches = $stmt->fetchAll();

$editing = false;
$current = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $stmt = $db->prepare("SELECT * FROM coaches WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $current = $stmt->fetch();
}

include 'includes/header.php';
?>

<div class="content-section">
    <h2>👥 <?php echo $editing ? 'Modifier' : 'Ajouter'; ?> un entraîneur</h2>
    <?php if ($editing): ?><a href="coaches.php" class="btn-secondary" style="float: right; margin-top: -40px;">❌ Annuler</a><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo $current['id']; ?>"><?php endif; ?>

        <div class="form-group">
            <label for="name">Nom complet *</label>
            <input type="text" id="name" name="name" required value="<?php echo $editing ? htmlspecialchars($current['name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="role">Rôle/Spécialité *</label>
            <input type="text" id="role" name="role" required placeholder="Ex: Entraîneur principal - spécialité jeunes"
                   value="<?php echo $editing ? htmlspecialchars($current['role']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="image_url">URL de l'image *</label>
            <input type="url" id="image_url" name="image_url" required placeholder="https://..."
                   value="<?php echo $editing ? htmlspecialchars($current['image_url']) : ''; ?>">
            <small>URL de la photo de l'entraîneur</small>
        </div>

        <div class="form-group">
            <label for="bio">Biographie (optionnel)</label>
            <textarea id="bio" name="bio" rows="3"><?php echo $editing ? htmlspecialchars($current['bio']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label for="display_order">Ordre d'affichage</label>
            <input type="number" id="display_order" name="display_order" value="<?php echo $editing ? $current['display_order'] : 0; ?>">
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="is_active" <?php echo (!$editing || $current['is_active']) ? 'checked' : ''; ?>> Entraîneur actif</label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">💾 <?php echo $editing ? 'Mettre à jour' : 'Créer'; ?></button>
        </div>
    </form>
</div>

<div class="content-section">
    <h2>📋 Liste des entraîneurs (<?php echo count($coaches); ?>)</h2>

    <?php if (empty($coaches)): ?>
        <p style="text-align: center; color: #999; padding: 40px;">Aucun entraîneur trouvé</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Nom</th>
                        <th>Rôle</th>
                        <th>Ordre</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coaches as $coach): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars($coach['image_url']); ?>" alt="<?php echo htmlspecialchars($coach['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;"></td>
                            <td><strong><?php echo htmlspecialchars($coach['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($coach['role']); ?></td>
                            <td><?php echo $coach['display_order']; ?></td>
                            <td><span class="badge <?php echo $coach['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $coach['is_active'] ? 'Actif' : 'Inactif'; ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="coaches.php?edit=<?php echo $coach['id']; ?>" class="btn-secondary btn-table-action">✏️</a>
                                    <form method="POST" class="table-action-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $coach['id']; ?>">
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

