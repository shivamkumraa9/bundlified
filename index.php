<?php

// Include the ShopifyService
require_once __DIR__ . '/Services/ShopifyService.php';
require_once __DIR__ . '/Services/DatabaseService.php';

// Instantiate the DatabaseService
$databaseService = new DatabaseService();

// Instantiate the ShopifyService
$shopifyService = new ShopifyService();

// Verify the token using the service
$decodedToken = $shopifyService->verifyToken();

header('Content-Type: application/json');

if ($decodedToken) {
    $shopifyService->handleInstall();
    http_response_code(200); // Set HTTP status to 200 OK
    echo json_encode([
        'message' => 'Hello, World!',
        'decoded_token' => $decodedToken
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'error' => 'Token verification failed.'
    ]);
}

?>
