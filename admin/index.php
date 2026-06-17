<?php
/**
 * TC BATILLY - Dashboard Admin
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

require_login();

$db = get_db();

// Récupérer les statistiques principales
$stats = [];

$stats['events'] = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active FROM events")->fetch();
$stats['news'] = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active FROM news")->fetch();
$stats['pricing'] = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active FROM pricing")->fetch();
$stats['coaches'] = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active FROM coaches")->fetch();
$stats['services'] = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active FROM services")->fetch();
$stats['users'] = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admins FROM users")->fetch();
$stats['logs'] = $db->query("SELECT COUNT(*) AS total FROM activity_logs")->fetch();

// Dernières activités (2 plus récentes)
$activity_stmt = $db->prepare("
    SELECT al.*, u.username
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 2
");
$activity_stmt->execute();
$recent_activities = $activity_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <section class="dashboard-overview">
        <div class="metric-grid">
            <article class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #d1875a 0%, #f0c2a0 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['events']['active']; ?> / <?php echo $stats['events']['total']; ?></h3>
                    <p>Événements actifs</p>
                </div>
                <a href="events.php" class="stat-link">Gérer →</a>
            </article>

            <article class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #395a64 0%, #4b7a89 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M12 19l7-7 3 3-7 7-3-3z"></path>
                        <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"></path>
                        <path d="M2 2l7.586 7.586"></path>
                        <circle cx="11" cy="11" r="2"></circle>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['news']['active']; ?> / <?php echo $stats['news']['total']; ?></h3>
                    <p>Actualités actives</p>
                </div>
                <a href="news.php" class="stat-link">Gérer →</a>
            </article>

            <article class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #b06f4a 0%, #d1875a 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pricing']['active']; ?> / <?php echo $stats['pricing']['total']; ?></h3>
                    <p>Formules tarifaires</p>
                </div>
                <a href="pricing.php" class="stat-link">Gérer →</a>
            </article>

            <article class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #728a90 0%, #a6bfc5 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['coaches']['active']; ?> / <?php echo $stats['coaches']['total']; ?></h3>
                    <p>Entraîneurs actifs</p>
                </div>
                <a href="coaches.php" class="stat-link">Gérer →</a>
            </article>

            <article class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #d1875a 0%, #e6b68a 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['services']['active']; ?> / <?php echo $stats['services']['total']; ?></h3>
                    <p>Services publiés</p>
                </div>
                <a href="services.php" class="stat-link">Gérer →</a>
            </article>

            <article class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #603813 0%, #a06f4b 100%);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['users']['admins']; ?> / <?php echo $stats['users']['total']; ?></h3>
                    <p>Admins / comptes</p>
                </div>
                <a href="settings.php" class="stat-link">Gérer →</a>
            </article>
        </div>
    </section>

    <aside class="activity-card">
        <div class="activity-card-header">
            <div>
                <h2>Activité récente</h2>
                <p>2 dernières actions au sein du back-office</p>
            </div>
            <a href="logs.php" class="btn-secondary">Tout l'historique</a>
        </div>

        <div class="activity-list">
            <?php if (empty($recent_activities)): ?>
                <p class="activity-empty">Aucune activité récente</p>
            <?php else: ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php
                            $icons = [
                                'create' => '➕',
                                'update' => '✏️',
                                'delete' => '🗑️',
                                'login' => '🔐',
                                'logout' => '👋'
                            ];
                            echo $icons[$activity['action']] ?? '📝';
                            ?>
                        </div>
                        <div class="activity-details">
                            <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                            <span>
                                <?php
                                $actions = [
                                    'create' => 'a créé',
                                    'update' => 'a modifié',
                                    'delete' => 'a supprimé',
                                    'login' => 's\'est connecté',
                                    'logout' => 's\'est déconnecté'
                                ];
                                echo $actions[$activity['action']] ?? $activity['action'];
                                ?>
                            </span>
                            <?php if ($activity['entity_type']): ?>
                                <span class="entity-type"><?php echo htmlspecialchars($activity['entity_type']); ?></span>
                            <?php endif; ?>
                            <?php if ($activity['details']): ?>
                                <span class="activity-name">"<?php echo htmlspecialchars($activity['details']); ?>"</span>
                            <?php endif; ?>
                        </div>
                        <div class="activity-time">
                            <?php echo format_date($activity['created_at'], DATETIME_FORMAT); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php include 'includes/footer.php'; ?>

