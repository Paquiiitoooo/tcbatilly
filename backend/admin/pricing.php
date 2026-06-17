<?php
/**
 * TC BATILLY - Gestion des tarifs
 */
require_once '../config/config.php';
require_once '../config/database.php';

require_login();

$db = get_db();
$page_title = 'Tarifs';
$page_description = 'Gérer les formules tarifaires du club';

// Traitement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $name = sanitize($_POST['name']);
        $badge = sanitize($_POST['badge'] ?? '');
        $badge_type = $_POST['badge_type'];
        $price = floatval($_POST['price']);
        $period = sanitize($_POST['period']);
        $note = sanitize($_POST['note'] ?? '');
        $display_order = intval($_POST['display_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Features (tableau)
        $features = array_filter(array_map('trim', $_POST['features'] ?? []));
        $features_json = json_encode($features, JSON_UNESCAPED_UNICODE);
        
        if ($action === 'create') {
            $stmt = $db->prepare("
                INSERT INTO pricing (name, badge, badge_type, price, period, features, note, display_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $badge, $badge_type, $price, $period, $features_json, $note, $display_order, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'pricing', $db->lastInsertId(), $name);
            redirect('pricing.php?success=' . urlencode('Tarif créé avec succès'));
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("
                UPDATE pricing 
                SET name = ?, badge = ?, badge_type = ?, price = ?, period = ?, features = ?, note = ?, display_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $badge, $badge_type, $price, $period, $features_json, $note, $display_order, $is_active, $id]);
            log_activity($db, $_SESSION['user_id'], 'update', 'pricing', $id, $name);
            redirect('pricing.php?success=' . urlencode('Tarif mis à jour'));
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM pricing WHERE id = ?");
        $stmt->execute([$id]);
        log_activity($db, $_SESSION['user_id'], 'delete', 'pricing', $id);
        redirect('pricing.php?success=' . urlencode('Tarif supprimé'));
    }
}

$stmt = $db->query("SELECT * FROM pricing ORDER BY display_order ASC");
$pricing_list = $stmt->fetchAll();

$editing = false;
$current_pricing = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM pricing WHERE id = ?");
    $stmt->execute([$id]);
    $current_pricing = $stmt->fetch();
    if ($current_pricing) {
        $current_pricing['features'] = json_decode($current_pricing['features'], true);
    }
}

include 'includes/header.php';
?>

<div class="content-section">
    <div class="section-header-actions">
        <h2>💰 <?php echo $editing ? 'Modifier un tarif' : 'Ajouter un tarif'; ?></h2>
        <?php if ($editing): ?>
            <a href="pricing.php" class="btn-secondary">❌ Annuler</a>
        <?php endif; ?>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?php echo $current_pricing['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="name">Nom de la formule *</label>
            <input type="text" id="name" name="name" required 
                   value="<?php echo $editing ? htmlspecialchars($current_pricing['name']) : ''; ?>">
        </div>

        <div class="form-grid-responsive">
            <div class="form-group">
                <label for="price">Prix (€) *</label>
                <input type="number" id="price" name="price" step="0.01" required 
                       value="<?php echo $editing ? $current_pricing['price'] : ''; ?>">
            </div>

            <div class="form-group">
                <label for="period">Période</label>
                <input type="text" id="period" name="period" placeholder="/ an" 
                       value="<?php echo $editing ? htmlspecialchars($current_pricing['period']) : '/ an'; ?>">
            </div>

            <div class="form-group">
                <label for="badge">Badge</label>
                <input type="text" id="badge" name="badge" placeholder="Jeunes, Famille..." 
                       value="<?php echo $editing ? htmlspecialchars($current_pricing['badge']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="badge_type">Type de badge</label>
                <select id="badge_type" name="badge_type">
                    <option value="default" <?php echo ($editing && $current_pricing['badge_type'] === 'default') ? 'selected' : ''; ?>>Normal</option>
                    <option value="popular" <?php echo ($editing && $current_pricing['badge_type'] === 'popular') ? 'selected' : ''; ?>>Populaire</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Caractéristiques (une par ligne)</label>
            <?php 
            $features = $editing ? $current_pricing['features'] : ['', '', ''];
            foreach ($features as $i => $feature): 
            ?>
                <input type="text" name="features[]" placeholder="Ex: Accès illimité aux courts" 
                       value="<?php echo htmlspecialchars($feature); ?>" style="margin-bottom: 8px;">
            <?php endforeach; ?>
            <button type="button" onclick="addFeature()" class="btn-secondary" style="margin-top: 8px;">+ Ajouter une caractéristique</button>
        </div>

        <div class="form-group">
            <label for="note">Note</label>
            <input type="text" id="note" name="note" placeholder="Ex: Pour les moins de 18 ans" 
                   value="<?php echo $editing ? htmlspecialchars($current_pricing['note']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="display_order">Ordre d'affichage</label>
            <input type="number" id="display_order" name="display_order" 
                   value="<?php echo $editing ? $current_pricing['display_order'] : 0; ?>">
            <small>Les tarifs sont affichés dans l'ordre croissant (0, 1, 2...)</small>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" <?php echo (!$editing || $current_pricing['is_active']) ? 'checked' : ''; ?>>
                Tarif actif
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                💾 <?php echo $editing ? 'Mettre à jour' : 'Créer le tarif'; ?>
            </button>
        </div>
    </form>
</div>

<div class="content-section">
    <h2>📋 Liste des tarifs (<?php echo count($pricing_list); ?>)</h2>

    <?php if (empty($pricing_list)): ?>
        <p style="text-align: center; color: #999; padding: 40px;">Aucun tarif trouvé</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Formule</th>
                        <th>Prix</th>
                        <th>Badge</th>
                        <th>Ordre</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pricing_list as $pricing): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($pricing['name']); ?></strong></td>
                            <td><strong style="color: #667eea; font-size: 18px;"><?php echo number_format($pricing['price'], 2); ?>€</strong> <?php echo htmlspecialchars($pricing['period']); ?></td>
                            <td>
                                <?php if ($pricing['badge']): ?>
                                    <span class="badge <?php echo $pricing['badge_type'] === 'popular' ? 'badge-success' : 'badge-info'; ?>">
                                        <?php echo htmlspecialchars($pricing['badge']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $pricing['display_order']; ?></td>
                            <td>
                                <span class="badge <?php echo $pricing['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $pricing['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="pricing.php?edit=<?php echo $pricing['id']; ?>" class="btn-secondary btn-table-action">✏️ Modifier</a>
                                    <form method="POST" class="table-action-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $pricing['id']; ?>">
                                        <button type="submit" class="btn-danger btn-table-action" 
                                                data-confirm-delete="Supprimer ce tarif ?">🗑️ Supprimer</button>
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

<script>
function addFeature() {
    const container = document.querySelector('label:has(+ input[name="features[]"])').parentElement;
    const newInput = document.createElement('input');
    newInput.type = 'text';
    newInput.name = 'features[]';
    newInput.placeholder = 'Nouvelle caractéristique';
    newInput.style.marginBottom = '8px';
    container.querySelector('button').before(newInput);
}
</script>

<?php include 'includes/footer.php'; ?>

