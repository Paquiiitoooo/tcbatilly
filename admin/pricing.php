<?php
/**
 * TC BATILLY - Gestion des tarifs
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

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

        $features = array_filter(array_map('trim', $_POST['features'] ?? []));
        if (empty($features)) {
            $features = [];
        }
        $features_json = json_encode(array_values($features), JSON_UNESCAPED_UNICODE);

        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO pricing (name, badge, badge_type, price, period, features, note, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $badge, $badge_type, $price, $period, $features_json, $note, $display_order, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'pricing', $db->lastInsertId(), $name);
            redirect('pricing.php?success=' . urlencode('Tarif créé avec succès'));
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE pricing SET name = ?, badge = ?, badge_type = ?, price = ?, period = ?, features = ?, note = ?, display_order = ?, is_active = ? WHERE id = ?");
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

$stmt = $db->query("SELECT * FROM pricing ORDER BY display_order ASC, id ASC");
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
        $current_pricing['features'] = json_decode($current_pricing['features'], true) ?: [];
    }
}

$form_active = $editing || isset($_GET['action']);
$default_features = ['', '', ''];

include 'includes/header.php';
?>

<div class="content-section" aria-labelledby="pricing-tabs">
    <div class="view-tabs" role="tablist" aria-label="Gestion des tarifs" id="pricing-tabs">
        <button type="button" class="view-tab" id="tab-pricing-list" role="tab" aria-controls="panel-pricing-list" aria-selected="<?php echo $form_active ? 'false' : 'true'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('pricing', 18); ?></span>
            <span>Formules publiées</span>
        </button>
        <button type="button" class="view-tab" id="tab-pricing-form" role="tab" aria-controls="panel-pricing-form" aria-selected="<?php echo $form_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo $editing ? admin_icon('edit', 18) : admin_icon('plus', 18); ?></span>
            <span><?php echo $editing ? 'Modifier' : 'Ajouter'; ?> un tarif</span>
        </button>
    </div>

    <div class="tab-panels">
        <section class="tab-panel" id="panel-pricing-list" role="tabpanel" aria-labelledby="tab-pricing-list" aria-hidden="<?php echo $form_active ? 'true' : 'false'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    Formules tarifaires <span class="badge badge-info"><?php echo count($pricing_list); ?></span>
                </h2>
                <a href="pricing.php?action=create" class="btn-secondary" data-switch-tab="tab-pricing-form"><?php echo admin_icon('plus', 16); ?><span>Nouvelle formule</span></a>
            </header>

            <?php if (empty($pricing_list)): ?>
                <div class="empty-state" style="text-align:center;padding:48px 20px;color:var(--ink-muted);">
                    <p>Aucun tarif enregistré.</p>
                    <p><a class="btn-primary" href="pricing.php?action=create" data-switch-tab="tab-pricing-form">Créer un tarif</a></p>
                </div>
            <?php else: ?>
                <div class="table-container" role="application" aria-label="Tableau des tarifs">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Formule</th>
                                <th scope="col">Prix</th>
                                <th scope="col">Badge</th>
                                <th scope="col">Ordre</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pricing_list as $pricing): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($pricing['name']); ?></strong>
                                        <?php if (!empty($pricing['note'])): ?>
                                            <p style="color:var(--ink-muted);font-size:13px;margin-top:6px;max-width:360px;"><?php echo htmlspecialchars($pricing['note']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color:#d1875a;font-size:18px;">
                                            <?php echo number_format($pricing['price'], 2, ',', ' '); ?>€
                                        </strong>
                                        <span><?php echo htmlspecialchars($pricing['period']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($pricing['badge'])): ?>
                                            <span class="badge <?php echo $pricing['badge_type'] === 'popular' ? 'badge-success' : 'badge-info'; ?>"><?php echo htmlspecialchars($pricing['badge']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $pricing['display_order']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $pricing['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $pricing['is_active'] ? 'Actif' : 'Inactif'; ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <a href="pricing.php?edit=<?php echo $pricing['id']; ?>" class="btn-secondary" data-switch-tab="tab-pricing-form"><?php echo admin_icon('edit', 15); ?><span>Modifier</span></a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $pricing['id']; ?>">
                                                <button type="submit" class="btn-danger" data-confirm-delete="Supprimer ce tarif ?"><?php echo admin_icon('trash', 15); ?><span>Supprimer</span></button>
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

        <section class="tab-panel" id="panel-pricing-form" role="tabpanel" aria-labelledby="tab-pricing-form" aria-hidden="<?php echo $form_active ? 'false' : 'true'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo $editing ? admin_icon('edit', 22) : admin_icon('plus', 22); ?>
                    <?php echo $editing ? 'Modifier un tarif' : 'Ajouter un tarif'; ?>
                </h2>
                <?php if ($editing): ?>
                    <a href="pricing.php" class="btn-ghost" data-switch-tab="tab-pricing-list"><?php echo admin_icon('arrow-left', 16); ?><span>Retour à la liste</span></a>
                <?php endif; ?>
            </header>

            <form method="POST" class="panel-grid">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo $current_pricing['id']; ?>"><?php endif; ?>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="name">Nom de la formule *</label>
                        <input type="text" id="name" name="name" required value="<?php echo $editing ? htmlspecialchars($current_pricing['name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="price">Prix (€) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo $editing ? $current_pricing['price'] : ''; ?>">
                    </div>
                </div>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="period">Mention devant le prix</label>
                        <input type="text" id="period" name="period" placeholder="à partir de" value="<?php echo $editing ? htmlspecialchars($current_pricing['period']) : ''; ?>">
                        <small>Par exemple « à partir de » ou « tarif unique ». Une mention qui commence par « / » (comme « / an ») s'affiche après le prix.</small>
                    </div>
                    <div class="form-group">
                        <label for="display_order">Ordre d'affichage</label>
                        <input type="number" id="display_order" name="display_order" value="<?php echo $editing ? $current_pricing['display_order'] : 0; ?>">
                        <small>Les formules sont affichées dans l'ordre croissant.</small>
                    </div>
                </div>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="badge">Badge</label>
                        <input type="text" id="badge" name="badge" placeholder="Jeunes, Populaire..." value="<?php echo $editing ? htmlspecialchars($current_pricing['badge']) : ''; ?>">
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
                    <div id="features-wrapper">
                        <?php
                        $features_values = $editing ? $current_pricing['features'] : $default_features;
                        if (empty($features_values)) {
                            $features_values = $default_features;
                        }
                        foreach ($features_values as $feature):
                        ?>
                            <input type="text" name="features[]" placeholder="Ex: Accès illimité aux courts" value="<?php echo htmlspecialchars($feature); ?>" style="margin-bottom:8px;">
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-secondary" style="margin-top:8px;" onclick="addFeatureField()">+ Ajouter une caractéristique</button>
                </div>

                <div class="form-group">
                    <label for="note">Note</label>
                    <input type="text" id="note" name="note" placeholder="Ex: Pour les moins de 18 ans" value="<?php echo $editing ? htmlspecialchars($current_pricing['note']) : ''; ?>">
                </div>

                <div class="form-group" style="margin-top:8px;">
                    <label>
                        <input type="checkbox" name="is_active" <?php echo (!$editing || $current_pricing['is_active']) ? 'checked' : ''; ?>>
                        Tarif actif (visible sur le site)
                    </label>
                </div>


                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editing ? 'Mettre à jour la formule' : 'Créer la formule'; ?></button>
                    <a href="pricing.php" class="btn-secondary" data-switch-tab="tab-pricing-list">Annuler</a>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
function addFeatureField() {
    const wrapper = document.getElementById('features-wrapper');
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'features[]';
    input.placeholder = 'Nouvelle caractéristique';
    input.style.marginBottom = '8px';
    wrapper.appendChild(input);
}
</script>

<?php include 'includes/footer.php'; ?>

