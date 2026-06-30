<?php
// fapshi_helper.php
require_once 'api/config.php';

function createFapshiPaymentLink($amount, $email, $userId, $externalId, $message) {
    $url = FAPSHI_BASE_URL . '/initiate-pay';

    $data = array(
        "amount" => (int)$amount, // Le montant doit être un entier (minimum 100 XAF)
        "email" => $email,
        "redirectUrl" => FAPSHI_REDIRECT_URL,
        "userId" => (string)$userId,
        "externalId" => (string)$externalId, // ID de la commande dans votre base de données
        "message" => $message
    );

    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'apiuser: ' . FAPSHI_API_USER,
        'apikey: ' . FAPSHI_API_KEY
    ));

    // Désactiver la vérification SSL en local (XAMPP) si vous avez des erreurs cURL
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE );
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Erreur cURL: ' . $error];
    }

    $responseData = json_decode($response, true);

    if ($httpCode == 200 && isset($responseData['link'] )) {
        return [
            'success' => true,
            'link' => $responseData['link'],
            'transId' => $responseData['transId']
        ];
    } else {
        return [
            'success' => false,
            'message' => isset($responseData['message']) ? $responseData['message'] : 'Erreur inconnue',
            'details' => $responseData
        ];
    }
}
?>