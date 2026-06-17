<?php
/**
 * TC BATILLY - API des entraîneurs
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';

try {
    $db = get_db();
    $method = $_SERVER['REQUEST_METHOD'];

    // GET - Récupérer les entraîneurs
    if ($method === 'GET') {
        $active_only = isset($_GET['active']) ? (bool)$_GET['active'] : true;

        $sql = "SELECT id, name, role, image_url, bio, display_order, is_active FROM coaches WHERE 1=1";

        if ($active_only) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY display_order ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $coaches = $stmt->fetchAll();

        $coaches = array_map(function ($coach) {
            $coach['diploma'] = $coach['bio'];
            return $coach;
        }, $coaches);

        json_response(['success' => true, 'coaches' => $coaches]);
    }

    // POST - Créer un entraîneur
    elseif ($method === 'POST') {
        require_login();

        $data = json_decode(file_get_contents('php://input'), true);

        $required = ['name', 'role', 'image_url'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                json_response(['success' => false, 'error' => "Le champ $field est requis"], 400);
            }
        }

        $stmt = $db->prepare("INSERT INTO coaches (name, role, image_url, bio, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $data['name'],
            $data['role'],
            $data['image_url'],
            $data['diploma'] ?? ($data['bio'] ?? null),
            $data['display_order'] ?? 0,
            $data['is_active'] ?? true
        ]);

        $coach_id = $db->lastInsertId();
        log_activity($db, $_SESSION['user_id'], 'create', 'coach', $coach_id, $data['name']);

        json_response(['success' => true, 'id' => $coach_id, 'message' => 'Entraîneur créé avec succès'], 201);
    }

    // PUT - Mettre à jour un entraîneur
    elseif ($method === 'PUT') {
        require_login();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            json_response(['success' => false, 'error' => 'ID requis'], 400);
        }

        $fields = [];
        $values = [];

        $allowed_fields = ['name', 'role', 'image_url', 'bio', 'display_order', 'is_active'];
        if (isset($data['diploma'])) {
            $data['bio'] = $data['diploma'];
        }
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            json_response(['success' => false, 'error' => 'Aucun champ à mettre à jour'], 400);
        }

        $values[] = $data['id'];

        $sql = "UPDATE coaches SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        log_activity($db, $_SESSION['user_id'], 'update', 'coach', $data['id'], $data['name'] ?? null);

        json_response(['success' => true, 'message' => 'Entraîneur mis à jour']);
    }

    // DELETE - Supprimer un entraîneur
    elseif ($method === 'DELETE') {
        require_login();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            json_response(['success' => false, 'error' => 'ID requis'], 400);
        }

        $stmt = $db->prepare("DELETE FROM coaches WHERE id = ?");
        $stmt->execute([$data['id']]);

        log_activity($db, $_SESSION['user_id'], 'delete', 'coach', $data['id']);

        json_response(['success' => true, 'message' => 'Entraîneur supprimé']);
    }

    else {
        json_response(['success' => false, 'error' => 'Méthode non autorisée'], 405);
    }

} catch (Exception $e) {
    json_response([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ], 500);
}

