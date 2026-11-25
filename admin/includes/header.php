<?php
require_once __DIR__ . '/icons.php';

$current_page = basename($_SERVER['PHP_SELF']);

$nav_groups = [
    'Pilotage' => [
        ['index.php', 'Tableau de bord'],
    ],
    'Site du club' => [
        ['events.php', 'Événements'],
        ['calendar.php', 'Calendrier'],
        ['news.php', 'Actualités'],
        ['pricing.php', 'Tarifs'],
        ['coaches.php', 'Entraîneurs'],
        ['services.php', 'Services'],
        ['sponsors.php', 'Sponsors'],
        ['polls.php', 'Sondages'],
    ],
];

if (is_admin()) {
    $nav_groups['Administration'] = [
        ['settings.php', 'Réglages et comptes'],
        ['logs.php', 'Historique'],
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <img class="brand-logo" src="../assets/images/logo.jpg" alt="" width="64" height="64">
                    <div class="brand-text">
                        <strong>Administration</strong>
                        <span>Tennis Club de Batilly</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Navigation principale">
                <?php foreach ($nav_groups as $group_title => $items): ?>
                    <p class="nav-section-title"><?php echo $group_title; ?></p>
                    <?php foreach ($items as [$file, $label]): ?>
                        <a href="<?php echo $file; ?>" class="nav-item<?php echo $current_page === $file ? ' active' : ''; ?>"<?php echo $current_page === $file ? ' aria-current="page"' : ''; ?>><?php echo $label; ?></a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-details">
                        <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                        <span><?php echo $_SESSION['user_role'] === 'admin' ? 'Administrateur' : 'Éditeur'; ?></span>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">Se déconnecter</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir le menu" type="button">Menu</button>

                <div class="header-left">
                    <h1><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Tableau de bord'; ?></h1>
                </div>

                <a href="../index.html" target="_blank" rel="noopener" class="btn-view-site">Voir le site</a>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" role="status"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error" role="alert"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <div class="content-body">
