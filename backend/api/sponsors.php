<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(['success' => false, 'error' => 'Méthode non autorisée'], 405);
    }

    $db = get_db();
    $active_only = isset($_GET['active']) ? (bool)$_GET['active'] : true;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;

    $sql = 'SELECT id, name, website, logo_path, display_order FROM sponsors WHERE 1=1';
    $params = [];

    if ($active_only) {
        $sql .= ' AND is_active = 1';
    }

    $sql .= ' ORDER BY display_order ASC, id ASC LIMIT ?';
    $params[] = $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $sponsors = $stmt->fetchAll();

    json_response(['success' => true, 'sponsors' => $sponsors]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ], 500);
}

