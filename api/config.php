<?php
// api/config.php

// Vos identifiants Fapshi (Remplacez par vos vraies valeurs)
// Utilisez l'URL sandbox pour les tests : https://sandbox.fapshi.com/initiate-pay
// Utilisez l'URL live pour la production : https://live.fapshi.com/initiate-pay (Vérifiez l'URL exacte dans la doc )

define('FAPSHI_API_USER', 'ddfd8ef1-eaaf-44e7-8038-395d49ab51e8');
define('FAPSHI_API_KEY', 'd3e5f1c0-4b6e-4f8a-9c2b-1a2b3c4d5e6f');
define('FAPSHI_BASE_URL', 'https://sandbox.fapshi.com' ); // Utilisez sandbox pour tester

// URL de redirection après paiement (doit être une URL absolue accessible)
// Exemple pour XAMPP : http://localhost/alipayment/payment_confirmation.php
define('FAPSHI_REDIRECT_URL', 'http://localhost/alipayment/payment_confirmation.php' ); 
?>