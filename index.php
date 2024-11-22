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

header('Content-Type: text/html');

if ($decodedToken) {
    $shopifyService->handleInstall();

    $shopData = $decodedToken['data']['shop_data'];
    $shop = parse_url($shopData['iss'], PHP_URL_HOST);

    try {
        $productsResponse = $shopifyService->fetchProducts($shop);
        // $productsResponse = $shopifyService->setProductMetafield($shop, "gid://shopify/Product/9781892874556", "hello");
        
        // If the products are fetched successfully, return a success HTML page
        http_response_code(200);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Products Fetched Successfully</title>
        
            <script src="https://cdn.jsdelivr.net/npm/@shopify/polaris@13.9.1/build/esm/index.js"></script>
            <link href="https://cdn.jsdelivr.net/npm/@shopify/polaris@13.9.1/build/esm/styles.css" rel="stylesheet">            </head>
        <body>
            <h1>Products Fetched Successfully</h1>
            <ul>
                <?php
                foreach ($productsResponse as $product) {
                    echo "<li>" . htmlspecialchars($product['title']) . "</li>";
                }
                ?>
            </ul>
            <h2>Hello World</h2>
            <form method="POST">
                <label for="userInput">Enter something:</label>
                <input type="text" id="userInput" name="userInput" placeholder="Type here...">
                <button type="submit">Submit</button>
            </form>
            <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $userInput = htmlspecialchars($_POST['userInput']);
                    echo "<p>You entered: $userInput</p>";
                }
            ?>
<button class="Polaris-Button Polaris-Button--pressable Polaris-Button--variantPrimary Polaris-Button--sizeMedium Polaris-Button--textAlignCenter" type="button">
  <span class="Polaris-Text--root Polaris-Text--bodySm Polaris-Text--medium">Save theme</span>
</button>
        </body>
        </html>
        <?php
    } catch (Exception $e) {
        // If there is an error fetching the products, return an error page
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error</title>
        </head>
        <body>
            <h1>Error Fetching Products</h1>
            <p>Failed to fetch products: <?php echo htmlspecialchars($e->getMessage()); ?></p>
            <a href="/">Go back</a>
        </body>
        </html>
        <?php
    }
} else {
    // If token verification fails, return an error page
    http_response_code(401);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error: Token Verification Failed</title>
    </head>
    <body>
        <h1>Error: Token Verification Failed</h1>
        <p>The token verification failed. Please check your token and try again.</p>
        <a href="/">Go back</a>
    </body>
    </html>
    <?php
}
?>
