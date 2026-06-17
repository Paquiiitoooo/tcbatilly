<?php
/**
 * TC BATILLY - Gestion des événements
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

require_login();

$db = get_db();
$page_title = 'Événements';
$page_description = 'Gérer les événements et le calendrier du club';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $event_date = $_POST['event_date'];
        $day = sanitize($_POST['day']);
        $month = sanitize($_POST['month']);
        $category = $_POST['category'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO events (title, description, event_date, day, month, category, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $event_date, $day, $month, $category, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'event', $db->lastInsertId(), $title);
            redirect('events.php?success=' . urlencode('Événement créé avec succès'));
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, day = ?, month = ?, category = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$title, $description, $event_date, $day, $month, $category, $is_active, $id]);
            log_activity($db, $_SESSION['user_id'], 'update', 'event', $id, $title);
            redirect('events.php?success=' . urlencode('Événement mis à jour'));
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);
        log_activity($db, $_SESSION['user_id'], 'delete', 'event', $id);
        redirect('events.php?success=' . urlencode('Événement supprimé'));
    }
}

// Récupérer les événements
$stmt = $db->query("SELECT * FROM events ORDER BY event_date ASC");
$events = $stmt->fetchAll();

// Mode édition
$editing = false;
$current_event = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $current_event = $stmt->fetch();
}

$form_active = $editing || isset($_GET['action']);

include 'includes/header.php';
?>

<div class="content-section" aria-labelledby="events-tabs">
    <div class="view-tabs" role="tablist" aria-label="Gestion des événements" id="events-tabs">
        <button type="button" class="view-tab" id="tab-events-list" role="tab" aria-controls="panel-events-list" aria-selected="<?php echo $form_active ? 'false' : 'true'; ?>">
            <span aria-hidden="true">📅</span>
            <span>Événements programmés</span>
        </button>
        <button type="button" class="view-tab" id="tab-events-form" role="tab" aria-controls="panel-events-form" aria-selected="<?php echo $form_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo $editing ? '✏️' : '➕'; ?></span>
            <span><?php echo $editing ? 'Modifier' : 'Ajouter'; ?> un événement</span>
        </button>
    </div>

    <div class="tab-panels">
        <section class="tab-panel" id="panel-events-list" role="tabpanel" aria-labelledby="tab-events-list" aria-hidden="<?php echo $form_active ? 'true' : 'false'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    Événements à venir <span class="badge badge-info"><?php echo count($events); ?></span>
                </h2>
                <a href="events.php?action=create" class="btn-secondary" data-switch-tab="tab-events-form">➕ Nouvel événement</a>
            </header>

            <?php if (empty($events)): ?>
                <div class="empty-state" style="text-align:center;padding:48px 20px;color:var(--ink-muted);">
                    <p>Aucun événement programmé pour le moment.</p>
                    <p><a class="btn-primary" href="events.php?action=create" data-switch-tab="tab-events-form">Créer le premier événement</a></p>
                </div>
            <?php else: ?>
                <div class="table-container" role="application" aria-label="Tableau des événements">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Titre</th>
                                <th scope="col">Date</th>
                                <th scope="col">Catégorie</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                        <p style="color:var(--ink-muted);font-size:13px;margin-top:6px;max-width:360px;">
                                            <?php echo htmlspecialchars(substr($event['description'], 0, 120)) . (strlen($event['description']) > 120 ? '…' : ''); ?>
                                        </p>
                                    </td>
                                    <td><?php echo format_date($event['event_date']); ?></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars(ucfirst($event['category'])); ?></span></td>
                                    <td>
                                        <?php if ($event['is_active']): ?>
                                            <span class="badge badge-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <a href="events.php?edit=<?php echo $event['id']; ?>" class="btn-secondary" data-switch-tab="tab-events-form">✏️ Modifier</a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn-danger" data-confirm-delete="Supprimer cet événement ?">🗑️ Supprimer</button>
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

        <section class="tab-panel" id="panel-events-form" role="tabpanel" aria-labelledby="tab-events-form" aria-hidden="<?php echo $form_active ? 'false' : 'true'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo $editing ? '✏️ Modifier un événement' : '➕ Ajouter un événement'; ?>
                </h2>
                <?php if ($editing): ?>
                    <a href="events.php" class="btn-ghost" data-switch-tab="tab-events-list">↩️ Retour à la liste</a>
                <?php endif; ?>
            </header>

            <form method="POST" class="panel-grid" aria-describedby="events-form-help">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo $current_event['id']; ?>"><?php endif; ?>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="title">Titre de l'événement *</label>
                        <input type="text" id="title" name="title" required value="<?php echo $editing ? htmlspecialchars($current_event['title']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="event_date">Date complète *</label>
                        <input type="date" id="event_date" name="event_date" required value="<?php echo $editing ? $current_event['event_date'] : ''; ?>">
                        <small>Format attendu : AAAA-MM-JJ</small>
                    </div>
                </div>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="day">Jour (affichage) *</label>
                        <input type="text" id="day" name="day" required placeholder="Ex: 14 ou 20-31" value="<?php echo $editing ? htmlspecialchars($current_event['day']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="month">Mois (affichage) *</label>
                        <input type="text" id="month" name="month" required placeholder="Ex: Nov, Déc" value="<?php echo $editing ? htmlspecialchars($current_event['month']) : ''; ?>">
                    </div>
                </div>

                <div class="panel-grid">
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" required rows="5" aria-describedby="event-description-help"><?php echo $editing ? htmlspecialchars($current_event['description']) : ''; ?></textarea>
                        <small id="event-description-help">Décrivez l'événement tel qu'il apparaîtra sur le site.</small>
                    </div>

                    <div class="form-group">
                        <label for="category">Catégorie *</label>
                        <select id="category" name="category" required>
                            <?php
                            $categories = [
                                'tournoi' => 'Tournoi',
                                'stage' => 'Stage',
                                'evenement' => 'Événement',
                                'autre' => 'Autre'
                            ];
                            foreach ($categories as $value => $label):
                                $selected = ($editing && $current_event['category'] === $value) ? 'selected' : '';
                                echo "<option value=\"$value\" $selected>$label</option>";
                            endforeach;
                            ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo (!$editing || $current_event['is_active']) ? 'checked' : ''; ?>>
                            Événement actif (visible sur le site)
                        </label>
                    </div>
                </div>

                <p id="events-form-help" style="color:var(--ink-muted);font-size:13px;">Les événements actifs alimentent automatiquement la section calendrier du site.</p>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editing ? 'Mettre à jour l\'événement' : 'Créer l\'événement'; ?></button>
                    <a href="events.php" class="btn-secondary" data-switch-tab="tab-events-list">Annuler</a>
                </div>
            </form>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

