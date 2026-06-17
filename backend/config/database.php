<?php
/**
 * TC BATILLY - Classe de connexion à la base de données
 */

class Database {
    private static $instance = null;
    private $connection;

    /**
     * Constructeur privé pour empêcher l'instanciation directe
     */
    private function __construct() {
        try {
            $dsnFragments = [
                'host=' . DB_HOST,
                DB_PORT ? 'port=' . DB_PORT : null,
                'dbname=' . DB_NAME,
                'charset=' . DB_CHARSET
            ];
            $dsnFragments = array_filter($dsnFragments, static fn($fragment) => $fragment !== null && $fragment !== '');
            $dsn = 'mysql:' . implode(';', $dsnFragments);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Erreur de connexion à la base de données : " . $e->getMessage());
            } else {
                die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
            }
        }
    }

    /**
     * Empêcher le clonage de l'instance
     */
    private function __clone() {}

    /**
     * Empêcher la désérialisation de l'instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Obtenir l'instance unique de la connexion
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtenir la connexion PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Préparer une requête
     */
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    /**
     * Exécuter une requête simple
     */
    public function query($sql) {
        return $this->connection->query($sql);
    }

    /**
     * Obtenir le dernier ID inséré
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Commencer une transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Valider une transaction
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Annuler une transaction
     */
    public function rollBack() {
        return $this->connection->rollBack();
    }
}

// Fonction helper pour obtenir la connexion facilement
function get_db() {
    return Database::getInstance()->getConnection();
}

