<?php
/**
 * TC BATILLY - Configuration principale
 */

// --------------------------------------------
// Chargement des variables d'environnement (.env)
// --------------------------------------------
$projectRoot = dirname(__DIR__, 2);
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';

if (file_exists($envPath) && is_readable($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));

        if ($value !== '' && $value[0] === '"' && substr($value, -1) === '"') {
            $value = substr($value, 1, -1);
        } elseif ($value !== '' && $value[0] === "'" && substr($value, -1) === "'") {
            $value = substr($value, 1, -1);
        }

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
        if (!array_key_exists($name, $_SERVER)) {
            $_SERVER[$name] = $value;
        }
        if (getenv($name) === false) {
            putenv("$name=$value");
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        if (is_string($value)) {
            $lowerValue = strtolower($value);
            switch ($lowerValue) {
                case 'true':
                case '(true)':
                case '1':
                case 'on':
                case 'yes':
                    return true;
                case 'false':
                case '(false)':
                case '0':
                case 'off':
                case 'no':
                    return false;
                case 'null':
                case '(null)':
                    return null;
            }
        }

        return $value;
    }
}

// --------------------------------------------
// Session & fuseau horaire
// --------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set(env('APP_TIMEZONE', 'Europe/Paris'));

// --------------------------------------------
// Configuration de la base de données
// --------------------------------------------
$dbConfig = [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', 3306),
    'name' => env('DB_NAME', 'tcbatilly'),
    'user' => env('DB_USER', 'root'),
    'pass' => env('DB_PASS', 'toor'),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];

$databaseUrl = env('DATABASE_URL', null);
if (is_string($databaseUrl) && $databaseUrl !== '') {
    $normalizedUrl = strpos($databaseUrl, '://') === false ? "mysql://{$databaseUrl}" : $databaseUrl;
    $parsedUrl = parse_url($normalizedUrl);

    if ($parsedUrl !== false) {
        if (!empty($parsedUrl['host'])) {
            $dbConfig['host'] = $parsedUrl['host'];
        }
        if (isset($parsedUrl['port'])) {
            $dbConfig['port'] = (int) $parsedUrl['port'];
        }
        if (!empty($parsedUrl['user'])) {
            $dbConfig['user'] = $parsedUrl['user'];
        }
        if (array_key_exists('pass', $parsedUrl)) {
            $dbConfig['pass'] = $parsedUrl['pass'];
        }
        if (!empty($parsedUrl['path'])) {
            $dbConfig['name'] = ltrim($parsedUrl['path'], '/');
        }
        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            if (isset($queryParams['charset'])) {
                $dbConfig['charset'] = $queryParams['charset'];
            }
        }
    }
}

define('DB_HOST', $dbConfig['host']);
define('DB_PORT', $dbConfig['port'] !== null && $dbConfig['port'] !== '' ? (int) $dbConfig['port'] : null);
define('DB_NAME', $dbConfig['name']);
define('DB_USER', $dbConfig['user']);
define('DB_PASS', $dbConfig['pass']);
define('DB_CHARSET', $dbConfig['charset']);

// --------------------------------------------
// Configuration du site
// --------------------------------------------
define('SITE_NAME', env('SITE_NAME', 'TC Batilly - Administration'));

$detectedScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$detectedHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$defaultSiteUrl = rtrim($detectedScheme . $detectedHost, '/');

define('SITE_URL', rtrim(env('SITE_URL', $defaultSiteUrl), '/'));
define('ADMIN_URL', rtrim(env('ADMIN_URL', SITE_URL . '/admin'), '/'));

// --------------------------------------------
// Configuration de sécurité
// --------------------------------------------
define('PASSWORD_SALT', env('PASSWORD_SALT', 'TCBatilly_2024_SecureKey'));
define(
    'SESSION_LIFETIME',
    (int) env('SESSION_LIFETIME', 3600 * 2) // 2 heures par défaut
);

// --------------------------------------------
// Configuration des uploads
// --------------------------------------------
$defaultUploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
define('UPLOAD_DIR', rtrim(env('UPLOAD_DIR', $defaultUploadDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', rtrim(env('UPLOAD_URL', SITE_URL . '/assets/uploads'), '/'));
define('MAX_UPLOAD_SIZE', (int) env('MAX_UPLOAD_SIZE', 5 * 1024 * 1024));
$allowedExtensions = env('ALLOWED_EXTENSIONS', null);
if (is_string($allowedExtensions)) {
    $allowedExtensions = array_filter(array_map('trim', explode(',', $allowedExtensions)));
}
define('ALLOWED_EXTENSIONS', is_array($allowedExtensions) && !empty($allowedExtensions)
    ? $allowedExtensions
    : ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif']
);

// --------------------------------------------
// Environnement & debug
// --------------------------------------------
define('ENVIRONMENT', env('ENVIRONMENT', 'development'));
$debugConfigured = env('DEBUG_MODE', null);
$debugMode = $debugConfigured !== null ? (bool) $debugConfigured : ENVIRONMENT === 'development';
define('DEBUG_MODE', $debugMode);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// --------------------------------------------
// Constantes diverses
// --------------------------------------------
define('ITEMS_PER_PAGE', (int) env('ITEMS_PER_PAGE', 20));
define('DATE_FORMAT', env('DATE_FORMAT', 'd/m/Y'));
define('DATETIME_FORMAT', env('DATETIME_FORMAT', 'd/m/Y H:i'));

// --------------------------------------------
// Headers de sécurité
// --------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// --------------------------------------------
// Fonctions utilitaires globales
// --------------------------------------------
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string) $data), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect(ADMIN_URL . '/login.php');
    }
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        redirect(ADMIN_URL . '/index.php?error=unauthorized');
    }
}

function format_date($date, $format = DATE_FORMAT) {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? (int) $date : strtotime((string) $date);
    return date($format, $timestamp);
}

function get_user_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($forwardedIps[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function log_activity($db, $user_id, $action, $entity_type = null, $entity_id = null, $details = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ip = get_user_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->execute([$user_id, $action, $entity_type, $entity_id, $details, $ip, $user_agent]);
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            error_log("Log activity error: " . $e->getMessage());
        }
    }
}

