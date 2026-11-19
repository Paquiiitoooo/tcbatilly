<?php
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';
require_login();

$db = get_db();
$page_title = 'Actualites';
$page_description = 'Gerer les actualites et les annonces du club';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $news_date = $_POST['news_date'];
        $emoji = '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'create') {
            $stmt = $db->prepare('INSERT INTO news (title, content, news_date, emoji, is_active) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $content, $news_date, $emoji, $is_active]);
            log_activity($db, $_SESSION['user_id'], 'create', 'news', $db->lastInsertId(), $title);
            redirect('news.php?success=' . urlencode('Actualite creee avec succes'));
        }

        $id = (int)$_POST['id'];
        $stmt = $db->prepare('UPDATE news SET title = ?, content = ?, news_date = ?, emoji = ?, is_active = ? WHERE id = ?');
        $stmt->execute([$title, $content, $news_date, $emoji, $is_active, $id]);
        log_activity($db, $_SESSION['user_id'], 'update', 'news', $id, $title);
        redirect('news.php?success=' . urlencode('Actualite mise a jour'));
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('DELETE FROM news WHERE id = ?');
        $stmt->execute([$id]);
        log_activity($db, $_SESSION['user_id'], 'delete', 'news', $id);
        redirect('news.php?success=' . urlencode('Actualite supprimee'));
    }
}

$stmt = $db->query('SELECT * FROM news ORDER BY news_date DESC');
$news_list = $stmt->fetchAll();

$editing = false;
$current_news = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM news WHERE id = ?');
    $stmt->execute([$id]);
    $current_news = $stmt->fetch();
}

$form_active = $editing || isset($_GET['action']);
$list_active = !$form_active;

include 'includes/header.php';
?>

<div class="content-section" aria-labelledby="news-tabs">
    <div class="view-tabs" role="tablist" aria-label="Gestion des actualités" id="news-tabs">
        <button type="button" class="view-tab" id="tab-news-list" role="tab"
                aria-controls="panel-news-list" aria-selected="<?php echo $list_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo admin_icon('news', 18); ?></span>
            <span>Actualités publiées</span>
        </button>
        <button type="button" class="view-tab" id="tab-news-form" role="tab"
                aria-controls="panel-news-form" aria-selected="<?php echo $form_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true"><?php echo $editing ? admin_icon('edit', 18) : admin_icon('plus', 18); ?></span>
            <span><?php echo $editing ? 'Modifier' : 'Ajouter'; ?> une actualité</span>
        </button>
    </div>

    <div class="tab-panels">
        <section class="tab-panel" id="panel-news-list" role="tabpanel" aria-labelledby="tab-news-list" aria-hidden="<?php echo $list_active ? 'false' : 'true'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    Liste des actualites <span class="badge badge-info"><?php echo count($news_list); ?></span>
                </h2>
                <a class="btn-secondary" href="news.php?action=create" data-switch-tab="tab-news-form">
                    <?php echo admin_icon('plus', 16); ?><span>Nouvelle actualité</span>
                </a>
            </header>

            <?php if (empty($news_list)): ?>
                <div class="empty-state" style="text-align:center;padding:48px 20px;color:var(--ink-muted);">
                    <p>Aucune actualite enregistree pour le moment.</p>
                    <p><a class="btn-primary" href="news.php?action=create" data-switch-tab="tab-news-form">Creer la premiere actualite</a></p>
                </div>
            <?php else: ?>
                <div class="table-container" role="application" aria-label="Tableau des actualites">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Titre</th>
                                <th scope="col">Date</th>
                                <th scope="col">Statut</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($news_list as $news): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($news['title']); ?></strong>
                                        <p style="color: var(--ink-muted); font-size: 13px; margin-top: 6px;">
                                            <?php echo htmlspecialchars(substr($news['content'], 0, 120)) . (strlen($news['content']) > 120 ? '...' : ''); ?>
                                        </p>
                                    </td>
                                    <td><?php echo format_date($news['news_date']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $news['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $news['is_active'] ? 'Actif' : 'Inactif'; ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <a href="news.php?edit=<?php echo $news['id']; ?>" class="btn-secondary" data-switch-tab="tab-news-form">
                                                <?php echo admin_icon('edit', 15); ?><span>Modifier</span>
                                            </a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
                                                <button type="submit" class="btn-danger"
                                                        data-confirm-delete="Êtes-vous sûr de vouloir supprimer cette actualité ?">
                                                    <?php echo admin_icon('trash', 15); ?><span>Supprimer</span>
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
        </section>

        <section class="tab-panel" id="panel-news-form" role="tabpanel" aria-labelledby="tab-news-form" aria-hidden="<?php echo $form_active ? 'false' : 'true'; ?>">
            <header class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                <h2 style="margin:0;display:flex;align-items:center;gap:10px;font-size:20px;">
                    <?php echo $editing ? 'Modifier une actualite' : 'Ajouter une actualite'; ?>
                </h2>
                <?php if ($editing): ?>
                    <a href="news.php" class="btn-ghost" data-switch-tab="tab-news-list"><?php echo admin_icon('arrow-left', 16); ?><span>Retour à la liste</span></a>
                <?php endif; ?>
            </header>

            <form method="POST" action="" class="panel-grid" aria-describedby="news-form-help">
                <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?php echo $current_news['id']; ?>">
                <?php endif; ?>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="title">Titre de l'actualite *</label>
                        <input type="text" id="title" name="title" required value="<?php echo $editing ? htmlspecialchars($current_news['title']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="news_date">Date de publication *</label>
                        <input type="date" id="news_date" name="news_date" required value="<?php echo $editing ? $current_news['news_date'] : date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="panel-grid two-columns">
                    <div class="form-group">
                        <label for="content">Contenu *</label>
                        <textarea id="content" name="content" required rows="8" aria-describedby="news-content-help"><?php echo $editing ? htmlspecialchars($current_news['content']) : ''; ?></textarea>
                        <small id="news-content-help">Decrivez l'actualite telle qu'elle apparaitra sur le site.</small>
                    </div>

                    <div class="form-group" role="checkbox">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo (!$editing || $current_news['is_active']) ? 'checked' : ''; ?>>
                            Actualite active (visible sur le site)
                        </label>
                    </div>
                </div>

                <p id="news-form-help" style="color:var(--ink-muted);font-size:13px;">
                    Les actualites actives sont immediatement visibles sur la page publique.
                </p>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editing ? 'Mettre a jour l actualite' : 'Publier l actualite'; ?></button>
                    <a href="news.php" class="btn-secondary" data-switch-tab="tab-news-list">Annuler</a>
                </div>
            </form>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
