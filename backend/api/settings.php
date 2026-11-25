<?php
/**
 * TC BATILLY - Réglages publics du site (lecture seule)
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

require_once '../config/config.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'error' => 'Méthode non autorisée'], 405);
}

$public_keys = [
    'site_name',
    'contact_email',
    'contact_phone',
    'address',
    'facebook_url',
    'instagram_url',
    'reservation_url',
    'offers_url',
];

try {
    $db = get_db();
    $placeholders = implode(',', array_fill(0, count($public_keys), '?'));
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($public_keys);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    json_response(['success' => true, 'settings' => $settings]);
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => 'Erreur serveur'], 500);
}
