<?php

// Set the appropriate headers for JSON response

// Allow cross-origin requests from a specific origin (replace * with your domain for security)
header('Access-Control-Allow-Origin: *'); // You can replace '*' with a specific domain like 'https://yourshopifydomain.com'
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // Allow specific HTTP methods
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow the Content-Type header and Authorization if needed
header("Access-Control-Allow-Headers: ngrok-skip-browser-warning");
header('Content-Type: application/json');


// Include the ShopifyService
require_once __DIR__ . '/../services/ShopifyService.php';

try {
    // Validate the required GET parameters
    if (!isset($_GET['shop']) || !isset($_GET['productId'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Missing required parameters: shop or productId']);
        exit;
    }

    // Get the shop and productId from the GET parameters
    $shop = $_GET['shop'];
    $productId = $_GET['productId'];

    // Initialize ShopifyService
    $shopifyService = new ShopifyService();

    // Call the fetchProduct function
    $result = $shopifyService->fetchProduct($shop, $productId);

    // Respond with the fetched product data
    http_response_code(200); // OK
    echo json_encode([
        'success' => true,
        'product' => $result
    ]);
} catch (Exception $e) {
    // Handle exceptions and respond with an error message
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ]);
}
