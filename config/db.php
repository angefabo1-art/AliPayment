<?php
/**
 * Configuration de la Connexion à la Base de Données
 * Fichier centralisé pour toutes les connexions à la BD
 * Utilise PDO pour les opérations sécurisées
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'alipayement');
define('DB_CHARSET', 'utf8mb4');

// Configuration du port (optionnel, par défaut 3306)
define('DB_PORT', 3306);

// Options de PDO
$pdo_options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
);

// Initialisation de la connexion PDO
$GLOBALS['pdo'] = null;

try {
    $GLOBALS['pdo'] = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        $pdo_options
    );
    $pdo = &$GLOBALS['pdo'];
} catch (PDOException $e) {
    // Journaliser l'erreur (en production, ne pas afficher les détails)
    error_log("Erreur de connexion PDO: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

// Pour la compatibilité avec MySQLi (si nécessaire)
// Vous pouvez créer une connexion MySQLi en plus de PDO si vos scripts l'utilisent
$GLOBALS['conn'] = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$conn = &$GLOBALS['conn'];

// Vérifier la connexion MySQLi
if ($conn->connect_error) {
    error_log("Erreur de connexion MySQLi: " . $conn->connect_error);
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

// Définir le charset pour MySQLi
$conn->set_charset("utf8mb4");

/**
 * Fonction helper pour les requêtes préparées PDO
 * Utilisation: $stmt = getPreparedStatement($pdo, "SELECT * FROM users WHERE email = ?");
 * $stmt->execute([$email]);
 * $result = $stmt->fetchAll();
 */
function getPreparedStatement($pdo, $sql) {
    try {
        return $pdo->prepare($sql);
    } catch (PDOException $e) {
        error_log("Erreur de préparation de requête: " . $e->getMessage());
        return null;
    }
}

/**
 * Fonction helper pour exécuter une requête avec paramètres
 * Utilisation: $result = executeQuery($pdo, "INSERT INTO users VALUES (?, ?, ?)", [$nom, $email, $password]);
 */
function executeQuery($pdo, $sql, $params = array()) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erreur d'exécution de requête: " . $e->getMessage());
        return null;
    }
}

/**
 * Fonction pour obtenir un enregistrement unique
 * Utilisation: $user = getRow($pdo, "SELECT * FROM users WHERE id = ?", [$id]);
 */
function getRow($pdo, $sql, $params = array()) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur de lecture: " . $e->getMessage());
        return null;
    }
}

/**
 * Fonction pour obtenir plusieurs enregistrements
 * Utilisation: $users = getRows($pdo, "SELECT * FROM users WHERE status = ?", ['active']);
 */
function getRows($pdo, $sql, $params = array()) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur de lecture: " . $e->getMessage());
        return array();
    }
}

/**
 * Fonction pour obtenir le nombre de lignes affectées
 * Utilisation: $count = getRowCount($pdo, "SELECT * FROM users WHERE status = ?", ['pending']);
 */
function getRowCount($pdo, $sql, $params = array()) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Erreur de comptage: " . $e->getMessage());
        return 0;
    }
}

/**
 * Fonction de hachage de mot de passe sécurisé
 * Utilisation: $hashed = hashPassword($password);
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Fonction de vérification de mot de passe
 * Utilisation: if (verifyPassword($password, $hashed)) { ... }
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Fonction pour sécuriser les données affichées en HTML
 * Utilisation: echo safeOutput($user_input);
 */
function safeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

?>
