<?php
/**
 * TC BATILLY - API des services
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

    // GET - Récupérer les services
    if ($method === 'GET') {
        $active_only = isset($_GET['active']) ? (bool)$_GET['active'] : true;
        
        $sql = "SELECT * FROM services WHERE 1=1";
        
        if ($active_only) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY display_order ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $services = $stmt->fetchAll();
        
        json_response(['success' => true, 'services' => $services]);
    }
    
    // POST - Créer un service
    elseif ($method === 'POST') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required = ['title', 'description'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                json_response(['success' => false, 'error' => "Le champ $field est requis"], 400);
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO services (title, description, icon, display_order, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['icon'] ?? null,
            $data['display_order'] ?? 0,
            $data['is_active'] ?? true
        ]);
        
        $service_id = $db->lastInsertId();
        log_activity($db, $_SESSION['user_id'], 'create', 'service', $service_id, $data['title']);
        
        json_response(['success' => true, 'id' => $service_id, 'message' => 'Service créé avec succès'], 201);
    }
    
    // PUT - Mettre à jour un service
    elseif ($method === 'PUT') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            json_response(['success' => false, 'error' => 'ID requis'], 400);
        }
        
        $fields = [];
        $values = [];
        
        $allowed_fields = ['title', 'description', 'icon', 'display_order', 'is_active'];
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
        
        $sql = "UPDATE services SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        log_activity($db, $_SESSION['user_id'], 'update', 'service', $data['id'], $data['title'] ?? null);
        
        json_response(['success' => true, 'message' => 'Service mis à jour']);
    }
    
    // DELETE - Supprimer un service
    elseif ($method === 'DELETE') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            json_response(['success' => false, 'error' => 'ID requis'], 400);
        }
        
        $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        log_activity($db, $_SESSION['user_id'], 'delete', 'service', $data['id']);
        
        json_response(['success' => true, 'message' => 'Service supprimé']);
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

