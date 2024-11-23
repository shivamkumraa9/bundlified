<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/DatabaseService.php';
require_once __DIR__ . '/AppService.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ShopifyService
{
    private $shopifyClientId;
    private $shopifyClientSecret;
    private $shopifyApiVersion;
    private $databaseService;

    public function __construct()
    {
        AppService::loadEnv();

        $this->shopifyClientId = getenv('SHOPIFY_CLIENT_ID');
        $this->shopifyClientSecret = getenv('SHOPIFY_CLIENT_SECRET');
        $this->shopifyApiVersion = getenv('SHOPIFY_API_VERSION');

        $this->databaseService = new DatabaseService();
    }

    public static function getAdminURL($shop, $suffix)
    {
        return "https://{$shop}/admin/{$suffix}";
    }

    public function runDiscountAppMutation($shop, $accessToken)
    {
        $url = self::getAdminURL($shop, "api/{$this->shopifyApiVersion}/graphql.json");

        $query = [
            'query' => '
                mutation {
                discountAutomaticAppCreate(
                    automaticAppDiscount: {
                    title: "Volume discount"
                    functionId: "8bc689c2-9a04-4d5c-8798-6b158186c89d"
                    startsAt: "2022-06-22T00:00:00"
                    }
                ) {
                    automaticAppDiscount {
                        discountId
                    }
                    userErrors {
                        field
                        message
                    }
                }
                }
            '
        ];

        $this->makePostRequest($url, $query, [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $accessToken,
        ]);
    }

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
                $this->runDiscountAppMutation($shop, $accessToken);
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

        $response = $this->makePostRequest($url, $query, [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $accessToken,
        ]);

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

    public function fetchProducts($shop)
    {
        $accessToken = $this->databaseService->getAccessTokenForShop($shop);

        if (!$accessToken) {
            throw new Exception("Access token for shop {$shop} not found.");
        }
        $url = self::getAdminURL($shop, "api/{$this->shopifyApiVersion}/graphql.json");

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

        $response = $this->makePostRequest($url, $query, [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $accessToken,
        ]);

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
        $filteredProducts = [];
        $excludedProducts = [];

        foreach ($products as $product) {
            $metafields = $product['node']['metafields']['edges'] ?? [];

            $excludeProduct = false;
            foreach ($metafields as $metafieldEdge) {
                $metafield = $metafieldEdge['node'];
                if (
                    $metafield['key'] === 'bundlified_free_product_id' &&
                    isset($metafield['value']) &&
                    strlen($metafield['value']) > 0
                ) {
                    $excludeProduct = true;
                    break;
                }
            }

            if ($excludeProduct) {
                $excludedProducts[] = $product;
            } else {
                $filteredProducts[] = $product;
            }
        }

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

    public function setProductMetafield($shop, $productId, $freeProductId)
    {
        $accessToken = $this->databaseService->getAccessTokenForShop($shop);

        if (!$accessToken) {
            throw new Exception("Access token for shop {$shop} not found.");
        }

        $url = self::getAdminURL($shop, "api/{$this->shopifyApiVersion}/graphql.json");

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

        $response = $this->makePostRequest($url, $mutation, [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $accessToken,
        ]);

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
