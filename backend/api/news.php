<?php
/**
 * TC BATILLY - API des actualités
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

    // GET - Récupérer les actualités
    if ($method === 'GET') {
        $active_only = isset($_GET['active']) ? (bool)$_GET['active'] : true;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        
        $sql = "SELECT * FROM news WHERE 1=1";
        
        if ($active_only) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY news_date DESC LIMIT ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$limit]);
        $news = $stmt->fetchAll();
        
        json_response(['success' => true, 'news' => $news]);
    }
    
    // POST - Créer une actualité
    elseif ($method === 'POST') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required = ['title', 'content', 'news_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                json_response(['success' => false, 'error' => "Le champ $field est requis"], 400);
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO news (title, content, news_date, emoji, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $emoji = $data['emoji'] ?? '📰';
        $is_active = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['news_date'],
            $emoji,
            $is_active
        ]);
        
        $news_id = $db->lastInsertId();
        log_activity($db, $_SESSION['user_id'], 'create', 'news', $news_id, $data['title']);
        
        json_response(['success' => true, 'id' => $news_id, 'message' => 'Actualité créée avec succès'], 201);
    }
    
    // PUT - Mettre à jour une actualité
    elseif ($method === 'PUT') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            json_response(['success' => false, 'error' => 'ID requis'], 400);
        }
        
        $fields = [];
        $values = [];
        
        $allowed_fields = ['title', 'content', 'news_date', 'emoji', 'is_active'];
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
        
        $sql = "UPDATE news SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        log_activity($db, $_SESSION['user_id'], 'update', 'news', $data['id'], $data['title'] ?? null);
        
        json_response(['success' => true, 'message' => 'Actualité mise à jour']);
    }
    
    // DELETE - Supprimer une actualité
    elseif ($method === 'DELETE') {
        require_login();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            json_response(['success' => false, 'error' => 'ID requis'], 400);
        }
        
        $stmt = $db->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        log_activity($db, $_SESSION['user_id'], 'delete', 'news', $data['id']);
        
        json_response(['success' => true, 'message' => 'Actualité supprimée']);
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

