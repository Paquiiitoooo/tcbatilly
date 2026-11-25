<?php
/**
 * TC BATILLY - API des événements
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

    // GET - Récupérer les événements
    if ($method === 'GET') {
        $active_only = isset($_GET['active']) ? (bool)$_GET['active'] : true;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        
        $sql = "SELECT * FROM events WHERE 1=1";
        
        if ($active_only) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY event_date ASC LIMIT ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$limit]);
        $events = $stmt->fetchAll();
        
        json_response(['success' => true, 'events' => $events]);
    }
    
    // POST - Créer un événement (nécessite authentification)
    elseif ($method === 'POST') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required = ['title', 'description', 'event_date', 'day', 'month', 'category'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                json_response(['success' => false, 'error' => "Le champ $field est requis"], 400);
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO events (title, description, event_date, event_end_date, event_time, day, month, category, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $is_active = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $event_time = $data['event_time'] ?? '(journée)';
        
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['event_date'],
            $data['event_end_date'] ?? null,
            $event_time,
            $data['day'],
            $data['month'],
            $data['category'],
            $is_active
        ]);
        
        $event_id = $db->lastInsertId();
        log_activity($db, $_SESSION['user_id'], 'create', 'event', $event_id, $data['title']);
        
        json_response(['success' => true, 'id' => $event_id, 'message' => 'Événement créé avec succès'], 201);
    }
    
    // PUT - Mettre à jour un événement
    elseif ($method === 'PUT') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            json_response(['success' => false, 'error' => 'ID requis'], 400);
        }
        
        $fields = [];
        $values = [];
        
        $allowed_fields = ['title', 'description', 'event_date', 'event_end_date', 'event_time', 'day', 'month', 'category', 'is_active'];
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            json_response(['success' => false, 'error' => 'Aucun champ à mettre à jour'], 400);
        }
        
        $values[] = $data['id'];
        
        $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        log_activity($db, $_SESSION['user_id'], 'update', 'event', $data['id'], $data['title'] ?? null);
        
        json_response(['success' => true, 'message' => 'Événement mis à jour']);
    }
    
    // DELETE - Supprimer un événement
    elseif ($method === 'DELETE') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            json_response(['success' => false, 'error' => 'ID requis'], 400);
        }
        
        $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        log_activity($db, $_SESSION['user_id'], 'delete', 'event', $data['id']);
        
        json_response(['success' => true, 'message' => 'Événement supprimé']);
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

