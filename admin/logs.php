<?php
/**
 * TC BATILLY - Historique des actions
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

require_admin();

$db = get_db();
$page_title = 'Historique des actions';
$page_description = 'Consultez l\'ensemble des opérations réalisées dans l\'espace d\'administration.';

$per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$filter_action = sanitize($_GET['filter_action'] ?? '');
$filter_entity = sanitize($_GET['filter_entity'] ?? '');
$filter_user = (int)($_GET['filter_user'] ?? 0);

$conditions = [];
$params = [];

if ($filter_action !== '') {
    $conditions[] = 'al.action = :action';
    $params[':action'] = $filter_action;
}
if ($filter_entity !== '') {
    $conditions[] = 'al.entity_type = :entity';
    $params[':entity'] = $filter_entity;
}
if ($filter_user > 0) {
    $conditions[] = 'al.user_id = :user_id';
    $params[':user_id'] = $filter_user;
}

$where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$count_stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs al $where_sql");
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_logs = (int)$count_stmt->fetchColumn();

$logs_stmt = $db->prepare("SELECT al.*, u.username, u.email FROM activity_logs al JOIN users u ON al.user_id = u.id $where_sql ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) {
    $logs_stmt->bindValue($key, $value);
}
$logs_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$logs_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll();

$total_pages = max(1, (int)ceil($total_logs / $per_page));

$actions = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
$entities = $db->query("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL AND entity_type <> '' ORDER BY entity_type ASC")->fetchAll(PDO::FETCH_COLUMN);
$users = $db->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();

$base_filters = $_GET;
unset($base_filters['page']);
$buildPageUrl = function (int $target_page) use ($base_filters) {
    $query = $base_filters;
    $query['page'] = $target_page;
    return 'logs.php' . (!empty($query) ? '?' . http_build_query($query) : '');
};

$action_labels = [
    'create' => 'Création',
    'update' => 'Modification',
    'delete' => 'Suppression',
    'login' => 'Connexion',
    'logout' => 'Déconnexion'
];

include 'includes/header.php';
?>

<div class="content-section">
    <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;">
        <div>
            <h2 style="margin:0;font-size:22px;">Historique détaillé</h2>
            <p style="color:var(--ink-muted);font-size:14px;margin-top:6px;"><?php echo number_format($total_logs); ?> actions enregistrées depuis la mise en service.</p>
        </div>
    </div>

    <form method="GET" class="filter-bar" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-bottom:20px;">
        <div class="form-group" style="min-width:180px;">
            <label for="filter_action">Type d'action</label>
            <select id="filter_action" name="filter_action">
                <option value="">Toutes</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($action_labels[$action] ?? ucfirst($action)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="min-width:200px;">
            <label for="filter_entity">Type d'entité</label>
            <select id="filter_entity" name="filter_entity">
                <option value="">Toutes</option>
                <?php foreach ($entities as $entity): ?>
                    <option value="<?php echo htmlspecialchars($entity); ?>" <?php echo $filter_entity === $entity ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst($entity)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="min-width:200px;">
            <label for="filter_user">Utilisateur</label>
            <select id="filter_user" name="filter_user">
                <option value="0">Tous</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user === (int)$user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn-primary">Filtrer</button>
            <?php if ($filter_action || $filter_entity || $filter_user): ?>
                <a href="logs.php" class="btn-secondary">Réinitialiser</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-container" role="application" aria-label="Tableau de l'historique des actions">
        <table>
            <thead>
                <tr>
                    <th scope="col">Date / heure</th>
                    <th scope="col">Utilisateur</th>
                    <th scope="col">Action</th>
                    <th scope="col">Cible</th>
                    <th scope="col">Détails</th>
                    <th scope="col">IP / Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:36px;color:var(--ink-muted);">Aucune action ne correspond aux filtres sélectionnés.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo format_date($log['created_at'], DATETIME_FORMAT); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                <br>
                                <small style="color:var(--ink-muted);">ID #<?php echo $log['user_id']; ?></small>
                            </td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($action_labels[$log['action']] ?? $log['action']); ?></span></td>
                            <td>
                                <?php if (!empty($log['entity_type'])): ?>
                                    <span class="badge badge-secondary" style="background:rgba(57,90,100,0.12);color:#395a64;">
                                        <?php echo htmlspecialchars($log['entity_type']); ?><?php echo $log['entity_id'] ? ' #' . (int)$log['entity_id'] : ''; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:320px;">
                                <?php echo !empty($log['details']) ? htmlspecialchars($log['details']) : '<span style="color:var(--ink-muted);">—</span>'; ?>
                            </td>
                            <td style="max-width:260px;">
                                <small style="display:block;font-size:12px;color:var(--ink-muted);">IP : <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></small>
                                <?php if (!empty($log['user_agent'])): ?>
                                    <small style="display:block;font-size:12px;color:var(--ink-muted);margin-top:4px;">UA : <?php echo htmlspecialchars(substr($log['user_agent'], 0, 70)) . (strlen($log['user_agent']) > 70 ? '…' : ''); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="pagination" aria-label="Pagination" style="display:flex;gap:8px;margin-top:24px;flex-wrap:wrap;">
            <?php if ($page > 1): ?>
                <a class="btn-secondary" href="<?php echo $buildPageUrl($page - 1); ?>">← Page précédente</a>
            <?php endif; ?>
            <span style="align-self:center;color:var(--ink-muted);font-size:13px;">Page <?php echo $page; ?> sur <?php echo $total_pages; ?></span>
            <?php if ($page < $total_pages): ?>
                <a class="btn-secondary" href="<?php echo $buildPageUrl($page + 1); ?>">Page suivante →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

