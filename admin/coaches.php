<?php
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';
require_login();

$db = get_db();
$page_title = 'Entraîneurs';
$page_description = 'Gérer l’équipe pédagogique du club';

$available_diplomas = [
    'Diplôme d\'État - DE JEPS',
    'Diplôme d\'État Supérieur - DES JEPS',
    'Certificat de Qualification Professionnelle - CQP AMT',
    'Certificat de Qualification Professionnelle - CQP ET',
    'Brevet d\'État 1er degré - BE1',
    'Brevet d\'État 2e degré - BE2',
    'Diplôme d\'Initiateur Fédéral',
    'Moniteur Fédéral',
    'Entraîneur National',
    'Préparateur Physique Spécialisé'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = sanitize($_POST['name']);
        $role = sanitize($_POST['role']);
        $image_url = sanitize($_POST['image_url']);
        $diploma = sanitize($_POST['diploma'] ?? '');
        $display_order = intval($_POST['display_order']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO coaches (name, role, image_url, bio, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $role, $image_url, $diploma, $display_order, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'coach', $db->lastInsertId(), $name);
            redirect('coaches.php?success=Entraîneur créé');
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE coaches SET name = ?, role = ?, image_url = ?, bio = ?, display_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $role, $image_url, $diploma, $display_order, $is_active, $id]);
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

$form_active = $editing || isset($_GET['action']);

include 'includes/header.php';
?>

<div class="content-section" aria-labelledby="coaches-tabs">
    <div class="view-tabs" role="tablist" aria-label="Gestion des entraîneurs" id="coaches-tabs">
        <button type="button" class="view-tab" id="tab-coaches-list" role="tab" aria-controls="panel-coaches-list" aria-selected="<?php echo $form_active ? 'false' : 'true'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('coaches', 18); ?></span>
            <span>Équipe du club</span>
        </button>
        <button type="button" class="view-tab" id="tab-coaches-form" role="tab" aria-controls="panel-coaches-form" aria-selected="<?php echo $form_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo $editing ? admin_icon('edit', 18) : admin_icon('plus', 18); ?></span>
            <span><?php echo $editing ? 'Modifier' : 'Ajouter'; ?> un entraîneur</span>
        </button>
    </div>

    <div class="tab-panels">
        <section class="tab-panel" id="panel-coaches-list" role="tabpanel" aria-labelledby="tab-coaches-list" aria-hidden="<?php echo $form_active ? 'true' : 'false'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo admin_icon('coaches', 22); ?>
                    Liste des entraîneurs
                    <span class="badge badge-info"><?php echo count($coaches); ?></span>
                </h2>
                <a class="btn-secondary" href="coaches.php?action=create" data-switch-tab="tab-coaches-form"><?php echo admin_icon('plus', 16); ?><span>Ajouter</span></a>
            </header>

            <?php if (empty($coaches)): ?>
                <div class="empty-state" style="text-align:center;padding:48px 20px;color:var(--ink-muted);">
                    <p>Aucun entraîneur enregistré pour le moment.</p>
                    <p><a class="btn-primary" href="coaches.php?action=create" data-switch-tab="tab-coaches-form">Créer le premier profil</a></p>
                </div>
            <?php else: ?>
                <div class="table-container" role="application" aria-label="Tableau des entraîneurs">
                    <table>
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Nom & rôle</th>
                                <th>Diplôme</th>
                                <th>Ordre</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coaches as $coach): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($coach['image_url']); ?>" alt="<?php echo htmlspecialchars($coach['name']); ?>" style="width:54px;height:54px;object-fit:cover;border-radius:16px;">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($coach['name']); ?></strong>
                                        <p style="color:var(--ink-muted);font-size:13px;margin-top:4px;"><?php echo htmlspecialchars($coach['role']); ?></p>
                                    </td>
                                    <td>
                                        <?php if (!empty($coach['bio'])): ?>
                                            <span class="badge badge-secondary" style="background:rgba(209,135,90,0.12);color:var(--brand-dark);">
                                                <?php echo htmlspecialchars($coach['bio']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $coach['display_order']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $coach['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $coach['is_active'] ? 'Actif' : 'Inactif'; ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <a href="coaches.php?edit=<?php echo $coach['id']; ?>" class="btn-secondary" data-switch-tab="tab-coaches-form"><?php echo admin_icon('edit', 15); ?><span>Modifier</span></a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $coach['id']; ?>">
                                                <button type="submit" class="btn-danger" data-confirm-delete="Supprimer cet entraîneur ?"><?php echo admin_icon('trash', 15); ?><span>Supprimer</span></button>
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

        <section class="tab-panel" id="panel-coaches-form" role="tabpanel" aria-labelledby="tab-coaches-form" aria-hidden="<?php echo $form_active ? 'false' : 'true'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo $editing ? admin_icon('edit', 22) : admin_icon('plus', 22); ?>
                    <?php echo $editing ? 'Modifier un entraîneur' : 'Ajouter un entraîneur'; ?>
                </h2>
                <?php if ($editing): ?>
                    <a href="coaches.php" class="btn-ghost" data-switch-tab="tab-coaches-list"><?php echo admin_icon('arrow-left', 16); ?><span>Retour à la liste</span></a>
                <?php endif; ?>
            </header>

            <form method="POST" class="panel-grid" aria-describedby="coach-form-help">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo $current['id']; ?>"><?php endif; ?>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="name">Nom complet *</label>
                        <input type="text" id="name" name="name" required value="<?php echo $editing ? htmlspecialchars($current['name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="role">Rôle / spécialité *</label>
                        <input type="text" id="role" name="role" required placeholder="Ex: Préparation physique & coaching avancé" value="<?php echo $editing ? htmlspecialchars($current['role']) : ''; ?>">
                    </div>
                </div>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="image_url">Photo (URL) *</label>
                        <input type="url" id="image_url" name="image_url" required placeholder="https://..." value="<?php echo $editing ? htmlspecialchars($current['image_url']) : ''; ?>">
                        <small>Utilisez une image carrée de bonne résolution.</small>
                    </div>
                    <div class="form-group">
                        <label for="diploma">Diplôme FFT *</label>
                        <select id="diploma" name="diploma" required>
                            <option value="">Sélectionner un diplôme</option>
                            <?php
                            $selected_diploma = $editing ? ($current['bio'] ?? '') : '';
                            foreach ($available_diplomas as $diploma_option):
                                $selected = $selected_diploma === $diploma_option ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($diploma_option) . "\" $selected>" . htmlspecialchars($diploma_option) . "</option>";
                            endforeach;
                            if ($editing && $selected_diploma && !in_array($selected_diploma, $available_diplomas, true)) {
                                echo '<option value="' . htmlspecialchars($selected_diploma) . '" selected>' . htmlspecialchars($selected_diploma) . ' (non référencé)</option>';
                            }
                            ?>
                        </select>
                    </div>
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
                            Entraîneur actif (visible sur le site)
                        </label>
                    </div>
                </div>

                <p id="coach-form-help" style="color:var(--ink-muted);font-size:13px;">Les profils actifs apparaissent dans la section "Nos Entraîneurs" du site public.</p>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo admin_icon('save', 16); ?><span><?php echo $editing ? 'Mettre à jour le profil' : 'Créer le profil'; ?></span></button>
                    <a href="coaches.php" class="btn-secondary" data-switch-tab="tab-coaches-list">Annuler</a>
                </div>
            </form>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

