<?php
/**
 * TC BATILLY - Gestion des actualités
 */
require_once '../config/config.php';
require_once '../config/database.php';

require_login();

$db = get_db();
$page_title = 'Actualités';
$page_description = 'Gérer les actualités et les annonces du club';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $news_date = $_POST['news_date'];
        $emoji = sanitize($_POST['emoji']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($action === 'create') {
            $stmt = $db->prepare("
                INSERT INTO news (title, content, news_date, emoji, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $content, $news_date, $emoji, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'news', $db->lastInsertId(), $title);
            redirect('news.php?success=' . urlencode('Actualité créée avec succès'));
        } else {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("
                UPDATE news 
                SET title = ?, content = ?, news_date = ?, emoji = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $content, $news_date, $emoji, $is_active, $id]);
            log_activity($db, $_SESSION['user_id'], 'update', 'news', $id, $title);
            redirect('news.php?success=' . urlencode('Actualité mise à jour'));
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        log_activity($db, $_SESSION['user_id'], 'delete', 'news', $id);
        redirect('news.php?success=' . urlencode('Actualité supprimée'));
    }
}

// Récupérer les actualités
$stmt = $db->query("SELECT * FROM news ORDER BY news_date DESC");
$news_list = $stmt->fetchAll();

// Mode édition
$editing = false;
$current_news = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $current_news = $stmt->fetch();
}

include 'includes/header.php';
?>

<div class="content-section">
    <div class="section-header-actions">
        <h2>
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 19l7-7 3 3-7 7-3-3z"></path>
                <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path>
            </svg>
            <?php echo $editing ? 'Modifier une actualité' : 'Ajouter une actualité'; ?>
        </h2>
        <?php if ($editing): ?>
            <a href="news.php" class="btn-secondary">
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
            <input type="hidden" name="id" value="<?php echo $current_news['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="title">Titre de l'actualité *</label>
            <input type="text" id="title" name="title" required 
                   value="<?php echo $editing ? htmlspecialchars($current_news['title']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="content">Contenu *</label>
            <textarea id="content" name="content" required rows="6"><?php echo $editing ? htmlspecialchars($current_news['content']) : ''; ?></textarea>
            <small>Description complète de l'actualité</small>
        </div>

        <div class="form-grid-responsive">
            <div class="form-group">
                <label for="news_date">Date de publication *</label>
                <input type="date" id="news_date" name="news_date" required 
                       value="<?php echo $editing ? $current_news['news_date'] : date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="emoji">Emoji</label>
                <input type="text" id="emoji" name="emoji" placeholder="📰" maxlength="10"
                       value="<?php echo $editing ? htmlspecialchars($current_news['emoji']) : '📰'; ?>">
                <small>Emoji représentant l'actualité</small>
            </div>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" <?php echo (!$editing || $current_news['is_active']) ? 'checked' : ''; ?>>
                Actualité active (visible sur le site)
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                <?php echo $editing ? 'Mettre à jour' : 'Créer l\'actualité'; ?>
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
        Liste des actualités (<?php echo count($news_list); ?>)
    </h2>

    <?php if (empty($news_list)): ?>
        <p style="text-align: center; color: #999; padding: 40px;">Aucune actualité trouvée</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Emoji</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($news_list as $news): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($news['title']); ?></strong>
                                <br>
                                <small style="color: #999;">
                                    <?php echo htmlspecialchars(substr($news['content'], 0, 100)) . '...'; ?>
                                </small>
                            </td>
                            <td><?php echo format_date($news['news_date']); ?></td>
                            <td style="font-size: 24px; text-align: center;">
                                <?php echo htmlspecialchars($news['emoji']); ?>
                            </td>
                            <td>
                                <?php if ($news['is_active']): ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="news.php?edit=<?php echo $news['id']; ?>" class="btn-secondary btn-table-action">
                                        ✏️ Modifier
                                    </a>
                                    <form method="POST" class="table-action-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
                                        <button type="submit" class="btn-danger btn-table-action" 
                                                data-confirm-delete="Êtes-vous sûr de vouloir supprimer cette actualité ?">
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

