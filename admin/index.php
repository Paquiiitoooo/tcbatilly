<?php
/**
 * TC BATILLY - Tableau de bord admin
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

require_login();

$db = get_db();
$page_title = 'Tableau de bord';

$count_content = static function (PDO $db, string $table): array {
    try {
        $row = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(is_active = 1), 0) AS active FROM {$table}")->fetch();
        return ['total' => (int) $row['total'], 'active' => (int) $row['active']];
    } catch (PDOException $e) {
        return ['total' => 0, 'active' => 0];
    }
};

$content_rows = [
    ['events.php', 'Événements', $count_content($db, 'events')],
    ['news.php', 'Actualités', $count_content($db, 'news')],
    ['pricing.php', 'Tarifs', $count_content($db, 'pricing')],
    ['coaches.php', 'Entraîneurs', $count_content($db, 'coaches')],
    ['services.php', 'Services', $count_content($db, 'services')],
    ['sponsors.php', 'Sponsors', $count_content($db, 'sponsors')],
    ['polls.php', 'Sondages', $count_content($db, 'polls')],
];

$poll_votes = null;
try {
    $poll_votes = (int) $db->query('SELECT COUNT(*) FROM poll_votes')->fetchColumn();
} catch (PDOException $e) {
    $poll_votes = null;
}

$accounts = is_admin()
    ? $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(role = 'admin'), 0) AS admins FROM users")->fetch()
    : null;

$activity_stmt = $db->prepare("
    SELECT al.*, u.username
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    " . (is_admin() ? '' : 'WHERE al.user_id = ?') . "
    ORDER BY al.created_at DESC
    LIMIT 8
");
$activity_stmt->execute(is_admin() ? [] : [(int) $_SESSION['user_id']]);
$recent_activities = $activity_stmt->fetchAll();

$verbs = ['create' => 'a créé', 'update' => 'a modifié', 'delete' => 'a supprimé'];
$session_verbs = ['login' => 's\'est connecté', 'logout' => 's\'est déconnecté'];
$entities = [
    'news' => 'une actualité',
    'event' => 'un événement',
    'poll' => 'un sondage',
    'poll_option' => 'une option de sondage',
    'poll_licencie' => 'un licencié',
    'sponsor' => 'un sponsor',
    'coach' => 'un entraîneur',
    'service' => 'un service',
    'pricing' => 'un tarif',
    'user' => 'un compte',
    'setting' => 'un réglage',
];

$days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$today_label = $days[(int) date('w')] . ' ' . date('j') . ' ' . $months[(int) date('n') - 1] . ' ' . date('Y');

include 'includes/header.php';
?>

<p class="board-date"><?php echo $today_label; ?></p>

<div class="quick-actions">
    <a class="btn-primary" href="news.php?action=create">Nouvelle actualité</a>
    <a class="btn-secondary" href="events.php?action=create">Nouvel événement</a>
    <a class="btn-secondary" href="polls.php#poll-form">Nouveau sondage</a>
    <a class="btn-secondary" href="sponsors.php?action=create">Nouveau sponsor</a>
</div>

<div class="dashboard-layout">
    <section class="dash-block" aria-labelledby="overview-title">
        <h2 id="overview-title">Sur le site</h2>

        <div class="table-container">
            <table class="board-table">
                <thead>
                    <tr>
                        <th scope="col">Rubrique</th>
                        <th scope="col" class="num">En ligne</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col"><span class="visually-hidden">Gérer</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($content_rows as [$href, $label, $counts]): ?>
                        <tr>
                            <td><?php echo $label; ?></td>
                            <td class="num"><?php echo $counts['active']; ?></td>
                            <td class="num dim"><?php echo $counts['total']; ?></td>
                            <td class="go"><a href="<?php echo $href; ?>">Gérer</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($poll_votes !== null): ?>
            <p class="board-note"><?php echo $poll_votes; ?> vote<?php echo $poll_votes > 1 ? 's' : ''; ?> reçu<?php echo $poll_votes > 1 ? 's' : ''; ?> dans les sondages. <a href="polls.php?tab=results">Voir les résultats</a></p>
        <?php endif; ?>
        <?php if ($accounts !== null): ?>
            <p class="board-note"><?php echo (int) $accounts['total']; ?> compte<?php echo $accounts['total'] > 1 ? 's' : ''; ?>, dont <?php echo (int) $accounts['admins']; ?> administrateur<?php echo $accounts['admins'] > 1 ? 's' : ''; ?>. <a href="settings.php">Gérer les comptes</a></p>
        <?php endif; ?>
    </section>

    <section class="dash-block" aria-labelledby="activity-title">
        <h2 id="activity-title">Dernières actions</h2>

        <div class="activity-list">
            <?php if (empty($recent_activities)): ?>
                <p class="activity-empty">Rien pour le moment.</p>
            <?php else: ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <?php
                    $action = $activity['action'];
                    if (isset($session_verbs[$action])) {
                        $sentence = $session_verbs[$action];
                    } else {
                        $verb = $verbs[$action] ?? htmlspecialchars($action);
                        $what = $entities[$activity['entity_type']] ?? htmlspecialchars((string) $activity['entity_type']);
                        $sentence = $verb . ' ' . $what;
                    }
                    ?>
                    <div class="activity-item">
                        <time class="activity-time" datetime="<?php echo htmlspecialchars($activity['created_at']); ?>"><?php echo format_date($activity['created_at'], 'd/m à H:i'); ?></time>
                        <strong><?php echo htmlspecialchars($activity['username']); ?></strong> <?php echo $sentence; ?>
                        <?php if ($activity['details']): ?>
                            <span class="activity-name"><?php echo htmlspecialchars($activity['details']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (is_admin()): ?>
            <p class="board-note"><a href="logs.php">Tout l'historique</a></p>
        <?php endif; ?>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
