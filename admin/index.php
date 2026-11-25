<?php
/**
 * TC BATILLY - Tableau de bord admin
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

require_login();

$db = get_db();
$page_title = 'Tableau de bord';
$page_description = 'Ce qui est en ligne sur le site du club et ce qui vient de changer.';

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
    ['pricing.php', 'Formules tarifaires', $count_content($db, 'pricing')],
    ['coaches.php', 'Entraîneurs', $count_content($db, 'coaches')],
    ['services.php', 'Services', $count_content($db, 'services')],
    ['sponsors.php', 'Sponsors', $count_content($db, 'sponsors')],
];

$poll_summary = null;
try {
    $polls = $count_content($db, 'polls');
    $votes = (int) $db->query('SELECT COUNT(*) FROM poll_votes')->fetchColumn();
    $poll_summary = ['open' => $polls['active'], 'votes' => $votes];
} catch (PDOException $e) {
    $poll_summary = null;
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
    LIMIT 7
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
    'setting' => 'un paramètre',
];

include 'includes/header.php';
?>

<div class="quick-actions">
    <a class="btn-primary" href="news.php?action=create"><?php echo admin_icon('plus', 17); ?><span>Publier une actualité</span></a>
    <a class="btn-secondary" href="events.php?action=create"><?php echo admin_icon('plus', 17); ?><span>Ajouter un événement</span></a>
    <a class="btn-secondary" href="polls.php#poll-form"><?php echo admin_icon('plus', 17); ?><span>Créer un sondage</span></a>
    <a class="btn-secondary" href="sponsors.php?action=create"><?php echo admin_icon('plus', 17); ?><span>Ajouter un sponsor</span></a>
</div>

<div class="dashboard-layout">
    <section class="dash-block" aria-labelledby="overview-title">
        <div class="dash-block-head">
            <div>
                <h2 id="overview-title">Contenu du site</h2>
                <p>Éléments en ligne sur le total créé.</p>
            </div>
        </div>

        <ul class="score-list">
            <?php foreach ($content_rows as [$href, $label, $counts]): ?>
                <li>
                    <a class="score-row" href="<?php echo $href; ?>">
                        <span class="score-label"><strong><?php echo $label; ?></strong></span>
                        <span class="score-figure"><?php echo $counts['active']; ?><i>/<?php echo $counts['total']; ?></i></span>
                        <?php echo admin_icon('chevron-right', 18, 'score-go'); ?>
                    </a>
                </li>
            <?php endforeach; ?>

            <?php if ($poll_summary !== null): ?>
                <li>
                    <a class="score-row" href="polls.php">
                        <span class="score-label">
                            <strong>Sondages</strong>
                            <span>votes reçus, <?php echo $poll_summary['open']; ?> sondage<?php echo $poll_summary['open'] > 1 ? 's' : ''; ?> ouvert<?php echo $poll_summary['open'] > 1 ? 's' : ''; ?></span>
                        </span>
                        <span class="score-figure"><?php echo $poll_summary['votes']; ?></span>
                        <?php echo admin_icon('chevron-right', 18, 'score-go'); ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($accounts !== null): ?>
                <li>
                    <a class="score-row" href="settings.php">
                        <span class="score-label">
                            <strong>Comptes</strong>
                            <span>administrateurs / comptes</span>
                        </span>
                        <span class="score-figure"><?php echo (int) $accounts['admins']; ?><i>/<?php echo (int) $accounts['total']; ?></i></span>
                        <?php echo admin_icon('chevron-right', 18, 'score-go'); ?>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </section>

    <section class="dash-block" aria-labelledby="activity-title">
        <div class="dash-block-head">
            <div>
                <h2 id="activity-title">Dernières actions</h2>
                <p>Ce qui a été modifié dans l'administration.</p>
            </div>
            <?php if (is_admin()): ?>
                <a href="logs.php">Tout voir</a>
            <?php endif; ?>
        </div>

        <div class="activity-list">
            <?php if (empty($recent_activities)): ?>
                <p class="activity-empty">Aucune action pour le moment.</p>
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
                        <time class="activity-time" datetime="<?php echo htmlspecialchars($activity['created_at']); ?>">
                            <?php echo format_date($activity['created_at'], 'd/m H:i'); ?>
                        </time>
                        <div class="activity-details">
                            <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                            <?php echo $sentence; ?>
                            <?php if ($activity['details']): ?>
                                <span class="activity-name"><?php echo htmlspecialchars($activity['details']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
