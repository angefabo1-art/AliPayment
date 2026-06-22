<?php
/**
 * API Endpoint pour récupérer le taux de change actuel
 * Utilisé par le dashboard pour les mises à jour en temps réel
 * 
 * Réponse JSON:
 * {
 *   "success": true,
 *   "rate": 6.75,
 *   "updated_at": "2026-03-29 14:30:00",
 *   "currency": "RMB-XAF"
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Include database connection
require_once __DIR__ . '/../config/db.php';
global $pdo;

// Verify PDO connection
if (!isset($GLOBALS['pdo']) || $GLOBALS['pdo'] === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de connexion à la base de données'
    ]);
    exit();
}

try {
    // Récupérer le taux de change le plus récent
    $exchange_rate_data = getRow(
        $pdo, 
        "SELECT rate, updated_at FROM exchange_rates WHERE currency_from = 'RMB' AND currency_to = 'XAF' ORDER BY updated_at DESC LIMIT 1"
    );
    
    if ($exchange_rate_data) {
        $rate = floatval($exchange_rate_data['rate']);
        $updated_at = htmlspecialchars($exchange_rate_data['updated_at']);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'rate' => $rate,
            'updated_at' => $updated_at,
            'currency' => 'RMB-XAF',
            'formatted_rate' => number_format($rate, 2, ',', ',')
        ]);
    } else {
        // Retourner le taux par défaut si aucun n'existe
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'rate' => 6.0,
            'updated_at' => null,
            'currency' => 'RMB-XAF',
            'formatted_rate' => '6,00',
            'is_default' => true
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération du taux: ' . $e->getMessage()
    ]);
}
?>
