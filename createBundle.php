<?php

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Include the ShopifyService
require_once __DIR__ . '/Services/ShopifyService.php';

try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['error' => 'Only POST method is allowed']);
        exit;
    }

    // Get the raw POST data
    $inputData = file_get_contents('php://input');

    // Decode JSON data into an associative array
    $postData = json_decode($inputData, true);

    // Validate input data
    if (!isset($postData['product']) || !isset($postData['datastring'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Missing required fields: product or datastring']);
        exit;
    }

    $product = $postData['product'];
    $datastring = $postData['datastring'];

    // Initialize ShopifyService
    $shopifyService = new ShopifyService();

    // Verify the token using the service
    $decodedToken = $shopifyService->verifyToken();

    if (!$decodedToken) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }

    // Extract shop information from the decoded token
    $shopData = $decodedToken['data']['shop_data'];
    $shop = parse_url($shopData['iss'], PHP_URL_HOST);

    // Call the setProductMetafield function
    $result = $shopifyService->setProductMetafield($shop, $product, $datastring);

    // Respond with a success message and result
    http_response_code(200); // OK
    echo json_encode([
        'success' => true,
        'message' => 'Product metafield set successfully',
        'result' => $result
    ]);
} catch (Exception $e) {
    // Handle exceptions and respond with an error message
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ]);
}
