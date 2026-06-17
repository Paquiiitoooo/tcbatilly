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
        $event_time = sanitize($_POST['event_time'] ?? '(journée)');
        $day = sanitize($_POST['day']);
        $month = sanitize($_POST['month']);
        $category = $_POST['category'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($action === 'create') {
            $stmt = $db->prepare("
                INSERT INTO events (title, description, event_date, event_time, day, month, category, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $description, $event_date, $event_time, $day, $month, $category, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'event', $db->lastInsertId(), $title);
            redirect('events.php?success=' . urlencode('Événement créé avec succès'));
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("
                UPDATE events 
                SET title = ?, description = ?, event_date = ?, event_time = ?, day = ?, month = ?, category = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $event_date, $event_time, $day, $month, $category, $is_active, $id]);
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

include 'includes/header.php';
?>

<div class="content-section">
    <div class="section-header-actions">
        <h2>
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <?php echo $editing ? 'Modifier un événement' : 'Ajouter un événement'; ?>
        </h2>
        <?php if ($editing): ?>
            <a href="events.php" class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Annuler
            </a>
        <?php endif; ?>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?php echo $current_event['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="title">Titre de l'événement *</label>
            <input type="text" id="title" name="title" required 
                   value="<?php echo $editing ? htmlspecialchars($current_event['title']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="description">Description *</label>
            <textarea id="description" name="description" required rows="4"><?php echo $editing ? htmlspecialchars($current_event['description']) : ''; ?></textarea>
        </div>

        <div class="form-grid-responsive">
            <div class="form-group">
                <label for="event_date">Date complète *</label>
                <input type="date" id="event_date" name="event_date" required 
                       value="<?php echo $editing ? $current_event['event_date'] : ''; ?>">
                <small>Format: YYYY-MM-DD</small>
            </div>

            <div class="form-group">
                <label for="event_time">Heure / Type *</label>
                <input type="text" id="event_time" name="event_time" required 
                       placeholder="Ex: 14h00, 10h-12h, (journée)"
                       value="<?php echo $editing ? htmlspecialchars($current_event['event_time'] ?? '(journée)') : '(journée)'; ?>">
                <small>Exemples: 14h00, 10h-12h, (journée), Matin, Après-midi</small>
            </div>

            <div class="form-group">
                <label for="day">Jour (affichage) *</label>
                <input type="text" id="day" name="day" required placeholder="Ex: 14 ou 20-31"
                       value="<?php echo $editing ? htmlspecialchars($current_event['day']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="month">Mois (affichage) *</label>
                <input type="text" id="month" name="month" required placeholder="Ex: Nov, Déc"
                       value="<?php echo $editing ? htmlspecialchars($current_event['month']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="category">Catégorie *</label>
                <select id="category" name="category" required>
                    <option value="tournoi" <?php echo ($editing && $current_event['category'] === 'tournoi') ? 'selected' : ''; ?>>Tournoi</option>
                    <option value="stage" <?php echo ($editing && $current_event['category'] === 'stage') ? 'selected' : ''; ?>>Stage</option>
                    <option value="evenement" <?php echo ($editing && $current_event['category'] === 'evenement') ? 'selected' : ''; ?>>Événement</option>
                    <option value="autre" <?php echo ($editing && $current_event['category'] === 'autre') ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" <?php echo (!$editing || $current_event['is_active']) ? 'checked' : ''; ?>>
                Événement actif (visible sur le site)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                <?php echo $editing ? 'Mettre à jour' : 'Créer l\'événement'; ?>
            </button>
        </div>
    </form>
</div>

<div class="content-section">
    <h2>
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
        </svg>
        Liste des événements (<?php echo count($events); ?>)
    </h2>

    <?php if (empty($events)): ?>
        <p style="text-align: center; color: #999; padding: 40px;">Aucun événement trouvé</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Catégorie</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($event['title']); ?></strong></td>
                            <td><?php echo format_date($event['event_date']); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo ucfirst($event['category']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($event['is_active']): ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="events.php?edit=<?php echo $event['id']; ?>" class="btn-secondary btn-table-action">
                                        ✏️ Modifier
                                    </a>
                                    <form method="POST" class="table-action-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn-danger btn-table-action" 
                                                data-confirm-delete="Êtes-vous sûr de vouloir supprimer cet événement ?">
                                            🗑️ Supprimer
                                        </button>
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

