<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/DatabaseService.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ShopifyService
{
    private $shopifyClientId;
    private $shopifyClientSecret;
    private $shopifyHost;
    private $shopifyApiVersion;
    private $databaseService;

    public function __construct()
    {
        $this->loadEnv();

        $this->shopifyClientId = getenv('SHOPIFY_CLIENT_ID');
        $this->shopifyClientSecret = getenv('SHOPIFY_CLIENT_SECRET');
        $this->shopifyHost = getenv('SHOPIFY_HOST');
        $this->shopifyApiVersion = getenv('SHOPIFY_API_VERSION');

        $this->databaseService = new DatabaseService();
    }

    /**
     * Get the Admin URL for a shop.
     *
     * @param string $shop
     * @param string $suffix
     * @return string
     */
    public static function getAdminURL($shop, $suffix)
    {
        return "https://{$shop}/admin/{$suffix}";
    }

    /**
     * Load environment variables from a .env file.
     */
    private function loadEnv()
    {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                list($name, $value) = explode('=', $line, 2);
                putenv(trim($name) . '=' . trim($value));
            }
        }
    }

    /**
     * Get offline access token for the given session token and shop.
     *
     * @param string $sessionToken
     * @param string $shop
     * @return array
     * @throws Exception
     */
    public function getOfflineAccessToken($sessionToken, $shop)
    {
        $url = self::getAdminURL($shop, 'oauth/access_token');

        $data = [
            'client_id' => $this->shopifyClientId,
            'client_secret' => $this->shopifyClientSecret,
            'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
            'subject_token' => $sessionToken,
            'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token',
        ];

        $response = $this->makePostRequest($url, $data);

        if (!$response || $response['http_code'] !== 200) {
            throw new Exception('Failed to retrieve access token: ' . ($response['body'] ?? 'Unknown error'));
        }

        return json_decode($response['body'], true);
    }

    /**
     * Helper function to make POST requests with cURL.
     *
     * @param string $url
     * @param array $data
     * @return array
     */
    private function makePostRequest($url, $data, $headers = ['Content-Type: application/json'])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return [
            'body' => $responseBody,
            'http_code' => $httpCode,
            'error' => $error,
        ];
    }

    public function getToken()
    {
        $requestHeaders = getallheaders();
        $queryParams = $_GET;

        $token = $requestHeaders['Authorization'] ?? $queryParams['id_token'] ?? null;

        return $token;
    }

    public function handleInstall()
    {
        $shop = $_GET['shop'] ?? null;

        if (!$shop) {
            return [
                'status' => 400,
                'error' => 'Shop parameter is missing'
            ];
        }

        $existingShop = $this->databaseService->getData("SELECT * FROM shop WHERE shop = '{$shop}'");

        if (count($existingShop) > 0) {
            return [
                'status' => 200,
                'message' => 'Shop is already installed.'
            ];
        } else {
            try {
                $sessionToken = $this->getToken();
                $accessTokenResponse = $this->getOfflineAccessToken($sessionToken, $shop);

                $accessToken = $accessTokenResponse['access_token'] ?? '';
                $status = 'INSTALLED';
                $createdAt = date('Y-m-d H:i:s');

                $query = "INSERT INTO shop (shop, accessToken, status, created_at) 
                          VALUES ('{$shop}', '{$accessToken}', '{$status}', '{$createdAt}')";

                $this->databaseService->executeQuery($query);

                return [
                    'status' => 200,
                    'message' => 'Shop installed successfully.'
                ];
            } catch (Exception $e) {
                return [
                    'status' => 500,
                    'error' => 'Failed to install the shop: ' . $e->getMessage()
                ];
            }
        }
    }

    public function fetchProduct($shop, $productId)
    {
        $accessToken = $this->databaseService->getAccessTokenForShop($shop);

        if (!$accessToken) {
            throw new Exception("Access token for shop {$shop} not found.");
        }
        $url = self::getAdminURL($shop, "api/{$this->shopifyApiVersion}/graphql.json");

        $query = [
            'query' => '
                query ($productId: ID!) {
                    product(id: $productId) {
                        metafields(first: 250) {
                            edges {
                                node {
                                    id
                                    key
                                    value
                                    type
                                }
                            }
                        }
                    }
                }
            ',
            'variables' => [
                'productId' => $productId
            ]
        ];

        // Make the request
        $response = $this->makePostRequest($url, $query, [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $accessToken,
        ]);

        // Handle response
        if ($response['http_code'] !== 200) {
            throw new Exception('Failed to fetch products: ' . ($response['body'] ?? 'Unknown error'));
        }
        $data = json_decode($response['body'], true);

        if (isset($data['errors'])) {
            throw new Exception('GraphQL errors: ' . json_encode($data['errors']));
        }

        foreach ($data['data']['product']['metafields']['edges'] as $edge) {
            if (isset($edge['node']['key']) && $edge['node']['key'] === "bundlified_free_product_id") {
                return json_decode($edge['node']['value'], true);
            }
        }
        return (object) [];
        return $data['data']['product']['metafields']['edges'];
    }

    /**
     * Fetch products from Shopify using GraphQL.
     *
     * @param string $shop
     * @param string $accessToken
     * @param string|null $afterCursor
     * @return array
     * @throws Exception
     */
    public function fetchProducts($shop)
    {
        $accessToken = $this->databaseService->getAccessTokenForShop($shop);

        if (!$accessToken) {
            throw new Exception("Access token for shop {$shop} not found.");
        }
        // Shopify GraphQL endpoint
        $url = self::getAdminURL($shop, "api/{$this->shopifyApiVersion}/graphql.json");

        // GraphQL query
        $query = [
            'query' => '
                query {
                    products(first: 250) {
                        edges {
                            node {
                            id
                            tags
                            title
                            description
                            metafields(first: 250) {
                                edges {
                                    node {
                                        id
                                        key
                                        value
                                        type
                                    }
                                }
                            }
                            images(first: 3) {
                                edges {
                                node {
                                    src
                                }
                                }
                            }
                            variants(first: 250) {
                                edges {
                                node {
                                    id
                                    price
                                }
                                }
                            }
                            }
                        }
                        pageInfo {
                            hasNextPage
                            endCursor
                        }
                    }
                }
            ',
        ];

        // Make the request
        $response = $this->makePostRequest($url, $query, [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $accessToken,
        ]);

        // Handle response
        if ($response['http_code'] !== 200) {
            throw new Exception('Failed to fetch products: ' . ($response['body'] ?? 'Unknown error'));
        }
        $data = json_decode($response['body'], true);

        if (isset($data['errors'])) {
            throw new Exception('GraphQL errors: ' . json_encode($data['errors']));
        }

        return $this->filterProducts($data['data']['products']['edges']);
    }

    public function filterProducts($products)
    {
        // Initialize two arrays to hold the filtered products
        $filteredProducts = [];
        $excludedProducts = [];

        // Loop through the products and apply the filtering logic
        foreach ($products as $product) {
            $metafields = $product['node']['metafields']['edges'] ?? [];

            // Check if the product has the 'bundlified_free_product_id' metafield
            $excludeProduct = false;
            foreach ($metafields as $metafieldEdge) {
                $metafield = $metafieldEdge['node'];
                if (
                    $metafield['key'] === 'bundlified_free_product_id' &&
                    isset($metafield['value']) &&
                    strlen($metafield['value']) > 0
                ) {
                    // If it has the metafield and its value is greater than 0, mark for exclusion
                    $excludeProduct = true;
                    break;
                }
            }

            // Depending on the condition, either include or exclude the product
            if ($excludeProduct) {
                $excludedProducts[] = $product;
            } else {
                $filteredProducts[] = $product;
            }
        }

        // Return both the filtered and excluded products
        return [
            'filtered' => $filteredProducts,
            'excluded' => $excludedProducts
        ];
    }

    public function verifyToken()
    {
        $token = $this->getToken();
        if ($token) {
            try {
                $decoded = JWT::decode($token, new Key($this->shopifyClientSecret, 'HS256'));

                return [
                    'status' => 200,
                    'data' => [
                        'shop_data' => (array)$decoded,
                        'sessionToken' => $token,
                    ],
                ];
            } catch (Exception $e) {
                return [
                    'status' => 401,
                    'error' => 'Token verification failed: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'status' => 401,
            'error' => 'Token not found',
        ];
    }

    /**
     * Set a metafield for a Shopify product.
     *
     * @param string $shop
     * @param string $productId
     * @param string $freeProductId
     * @return array
     * @throws Exception
     */
    public function setProductMetafield($shop, $productId, $freeProductId)
    {
        $accessToken = $this->databaseService->getAccessTokenForShop($shop);

        if (!$accessToken) {
            throw new Exception("Access token for shop {$shop} not found.");
        }

        // Shopify GraphQL endpoint
        $url = self::getAdminURL($shop, "api/{$this->shopifyApiVersion}/graphql.json");

        // GraphQL mutation for updating the metafield
        $mutation = [
            'query' => '
                mutation UpdateProductMetafield($input: ProductInput!) {
                    productUpdate(input: $input) {
                        product {
                            id
                        }
                        userErrors {
                            field
                            message
                        }
                    }
                }
            ',
            'variables' => [
                'input' => [
                    'id' => $productId,
                    'metafields' => [
                        [
                            'namespace' => 'custom',
                            'key' => 'bundlified_free_product_id',
                            'type' => 'single_line_text_field',
                            'value' => $freeProductId,
                        ],
                    ],
                ],
            ],
        ];

        // Make the request
        $response = $this->makePostRequest($url, $mutation, [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $accessToken,
        ]);

        // Handle response
        if ($response['http_code'] !== 200) {
            throw new Exception('Failed to set metafield: ' . ($response['body'] ?? 'Unknown error'));
        }

        $data = json_decode($response['body'], true);

        if (isset($data['errors'])) {
            throw new Exception('GraphQL errors: ' . json_encode($data['errors']));
        }

        $userErrors = $data['data']['productUpdate']['userErrors'] ?? [];
        if (!empty($userErrors)) {
            return [
                'status' => 400,
                'errors' => $userErrors,
            ];
        }

        return [
            'status' => 200,
            'message' => 'Metafield updated successfully',
            'product' => $data['data']['productUpdate']['product'],
        ];
    }
}
