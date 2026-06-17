<?php
/**
 * TC BATILLY - Dashboard Admin
 */
require_once '../config/config.php';
require_once '../config/database.php';

require_login();

$db = get_db();

// Récupérer les statistiques
$stats = [];

// Événements
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM events");
$stats['events'] = $stmt->fetch();

// Actualités
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM news");
$stats['news'] = $stmt->fetch();

// Tarifs
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM pricing");
$stats['pricing'] = $stmt->fetch();

// Entraîneurs
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM coaches");
$stats['coaches'] = $stmt->fetch();

// Services
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM services");
$stats['services'] = $stmt->fetch();

// Activités récentes
$stmt = $db->prepare("
    SELECT al.*, u.username 
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="dashboard-grid">
    <!-- Carte Événements -->
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
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
    </div>

    <!-- Carte Actualités -->
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
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
    </div>

    <!-- Carte Tarifs -->
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
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
    </div>

    <!-- Carte Entraîneurs -->
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['coaches']['active']; ?> / <?php echo $stats['coaches']['total']; ?></h3>
            <p>Entraîneurs</p>
        </div>
        <a href="coaches.php" class="stat-link">Gérer →</a>
    </div>

    <!-- Carte Services -->
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['services']['active']; ?> / <?php echo $stats['services']['total']; ?></h3>
            <p>Services proposés</p>
        </div>
        <a href="services.php" class="stat-link">Gérer →</a>
    </div>

    <!-- Carte Admin -->
    <?php if (is_admin()): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3>Administration</h3>
            <p>Paramètres & utilisateurs</p>
        </div>
        <a href="settings.php" class="stat-link">Gérer →</a>
    </div>
    <?php endif; ?>
</div>

<!-- Activité récente -->
<div class="content-section">
    <h2>
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
        </svg>
        Activité récente
    </h2>
    
    <div class="activity-list">
        <?php if (empty($recent_activities)): ?>
            <p style="text-align: center; color: #999; padding: 40px;">Aucune activité récente</p>
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
</div>

<?php include 'includes/footer.php'; ?>

