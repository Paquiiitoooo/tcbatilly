<?php
/**
 * Fichier de test pour diagnostiquer l'erreur 500
 * ⚠️ SUPPRIMEZ CE FICHIER APRÈS LE TEST (sécurité)
 */

// Test 1 : PHP fonctionne-t-il ?
echo "<h1>Test PHP - TC Batilly</h1>";
echo "<p>✅ PHP fonctionne !</p>";

// Test 2 : Affichage des informations PHP
echo "<h2>Version PHP : " . phpversion() . "</h2>";

// Test 3 : Extensions PHP
echo "<h2>Extensions PHP :</h2>";
echo "<ul>";
echo "<li>PDO : " . (extension_loaded('pdo') ? '✅ Activé' : '❌ Non activé') . "</li>";
echo "<li>pdo_mysql : " . (extension_loaded('pdo_mysql') ? '✅ Activé' : '❌ Non activé') . "</li>";
echo "</ul>";

// Test 4 : Fichier .env existe-t-il ?
$projectRoot = __DIR__;
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
echo "<h2>Fichier .env :</h2>";
if (file_exists($envPath)) {
    echo "<p>✅ Fichier .env trouvé : " . $envPath . "</p>";
    echo "<p>✅ Fichier .env lisible : " . (is_readable($envPath) ? 'Oui' : 'Non') . "</p>";
} else {
    echo "<p>❌ Fichier .env NON trouvé : " . $envPath . "</p>";
}

// Test 5 : Permissions
echo "<h2>Permissions :</h2>";
echo "<ul>";
echo "<li>Dossier actuel : " . substr(sprintf('%o', fileperms(__DIR__)), -4) . "</li>";
if (file_exists($envPath)) {
    echo "<li>Fichier .env : " . substr(sprintf('%o', fileperms($envPath)), -4) . "</li>";
}
echo "</ul>";

// Test 6 : Tentative de connexion à la base de données
echo "<h2>Test connexion base de données :</h2>";
try {
    // Charger la config
    if (file_exists($envPath)) {
        require_once 'backend/config/config.php';
        require_once 'backend/config/database.php';
        
        $db = get_db();
        echo "<p>✅ Connexion à la base de données réussie !</p>";
    } else {
        echo "<p>⚠️ Impossible de tester la DB : fichier .env manquant</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erreur de connexion DB : " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 7 : Chemin du projet
echo "<h2>Chemins :</h2>";
echo "<ul>";
echo "<li>Racine du projet : " . $projectRoot . "</li>";
echo "<li>Chemin .env attendu : " . $envPath . "</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>⚠️ IMPORTANT : Supprimez ce fichier après le test pour des raisons de sécurité !</strong></p>";
?>

