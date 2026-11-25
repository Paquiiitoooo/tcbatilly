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
        $end_input = trim($_POST['event_end_date'] ?? '');
        $category = $_POST['category'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $start = DateTime::createFromFormat('Y-m-d', $event_date);
        if (!$start) {
            redirect('events.php?error=' . urlencode('Date de début invalide'));
        }
        $event_end_date = null;
        $end = $end_input !== '' ? DateTime::createFromFormat('Y-m-d', $end_input) : false;
        if ($end && $end > $start) {
            $event_end_date = $end->format('Y-m-d');
        }

        // Jour et mois d'affichage (anciens champs) déduits des dates
        $months_short = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
        $day = $start->format('j');
        $month = $months_short[(int) $start->format('n') - 1];
        if ($event_end_date !== null) {
            $day .= '-' . $end->format('j');
            if ($start->format('Ym') !== $end->format('Ym')) {
                $month .= '-' . $months_short[(int) $end->format('n') - 1];
            }
        }

        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO events (title, description, event_date, event_end_date, day, month, category, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $event_date, $event_end_date, $day, $month, $category, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'event', $db->lastInsertId(), $title);
            redirect('events.php?success=' . urlencode('Événement créé avec succès'));
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, event_end_date = ?, day = ?, month = ?, category = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$title, $description, $event_date, $event_end_date, $day, $month, $category, $is_active, $id]);
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
            <span aria-hidden="true"><?php echo admin_icon('calendar', 18); ?></span>
            <span>Événements programmés</span>
        </button>
        <button type="button" class="view-tab" id="tab-events-form" role="tab" aria-controls="panel-events-form" aria-selected="<?php echo $form_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo $editing ? admin_icon('edit', 18) : admin_icon('plus', 18); ?></span>
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
                <a href="events.php?action=create" class="btn-secondary" data-switch-tab="tab-events-form"><?php echo admin_icon('plus', 16); ?><span>Nouvel événement</span></a>
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
                                    <td><?php echo format_date($event['event_date']); ?><?php if (!empty($event['event_end_date'])): ?><br><span style="color:var(--ink-muted);">au <?php echo format_date($event['event_end_date']); ?></span><?php endif; ?></td>
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
                                            <a href="events.php?edit=<?php echo $event['id']; ?>" class="btn-secondary" data-switch-tab="tab-events-form"><?php echo admin_icon('edit', 15); ?><span>Modifier</span></a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn-danger" data-confirm-delete="Supprimer cet événement ?"><?php echo admin_icon('trash', 15); ?><span>Supprimer</span></button>
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
                    <?php echo $editing ? admin_icon('edit', 22) : admin_icon('plus', 22); ?>
                    <?php echo $editing ? 'Modifier un événement' : 'Ajouter un événement'; ?>
                </h2>
                <?php if ($editing): ?>
                    <a href="events.php" class="btn-ghost" data-switch-tab="tab-events-list"><?php echo admin_icon('arrow-left', 16); ?><span>Retour à la liste</span></a>
                <?php endif; ?>
            </header>

            <form method="POST" class="panel-grid">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo $current_event['id']; ?>"><?php endif; ?>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="title">Titre de l'événement *</label>
                        <input type="text" id="title" name="title" required value="<?php echo $editing ? htmlspecialchars($current_event['title']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="event_date">Date, ou premier jour *</label>
                        <input type="date" id="event_date" name="event_date" required value="<?php echo $editing ? $current_event['event_date'] : ''; ?>">
                    </div>
                </div>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="event_end_date">Dernier jour</label>
                        <input type="date" id="event_end_date" name="event_end_date" value="<?php echo ($editing && !empty($current_event['event_end_date'])) ? $current_event['event_end_date'] : ''; ?>">
                        <small>À remplir seulement si l'événement dure plusieurs jours.</small>
                    </div>
                </div>

                <div class="panel-grid">
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" required rows="5"><?php echo $editing ? htmlspecialchars($current_event['description']) : ''; ?></textarea>
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


                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editing ? 'Mettre à jour l\'événement' : 'Créer l\'événement'; ?></button>
                    <a href="events.php" class="btn-secondary" data-switch-tab="tab-events-list">Annuler</a>
                </div>
            </form>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

