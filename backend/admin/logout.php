<?php
/**
 * TC BATILLY - Déconnexion
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Log de l'activité avant de détruire la session
if (isset($_SESSION['user_id'])) {
    try {
        $db = get_db();
        log_activity($db, $_SESSION['user_id'], 'logout');
    } catch (Exception $e) {
        // Ignorer les erreurs lors de la déconnexion
    }
}

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire le cookie de session si existant
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
redirect(ADMIN_URL . '/login.php');

