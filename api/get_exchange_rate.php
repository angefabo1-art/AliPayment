<?php
/**
 * API endpoint for getting the current exchange rate
 * Accessed by dashboard.php and other pages
 * 
 * Returns: JSON with current exchange rate and update timestamp
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Include database connection
require_once __DIR__ . '/../config/db.php';
global $pdo;

// Verify connection
if (!isset($GLOBALS['pdo']) || $GLOBALS['pdo'] === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

try {
    // Get the latest exchange rate set by admin
    $exchange_rate_data = getRow(
        $pdo,
        "SELECT rate, updated_at FROM exchange_rates WHERE currency_from = 'RMB' AND currency_to = 'XAF' ORDER BY updated_at DESC LIMIT 1"
    );
    
    if ($exchange_rate_data) {
        $response = [
            'success' => true,
            'rate' => floatval($exchange_rate_data['rate']),
            'currency_from' => 'RMB',
            'currency_to' => 'XAF',
            'updated_at' => $exchange_rate_data['updated_at'],
            'message' => '1 RMB = ' . floatval($exchange_rate_data['rate']) . ' FCFA'
        ];
    } else {
        // Return default rate if none exists
        $response = [
            'success' => true,
            'rate' => 6.0,
            'currency_from' => 'RMB',
            'currency_to' => 'XAF',
            'updated_at' => null,
            'message' => 'Using default rate: 1 RMB = 6.0 FCFA'
        ];
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
