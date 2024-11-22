<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/DatabaseService.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ShopifyService {
    private $shopifyClientId;
    private $shopifyClientSecret;
    private $shopifyHost;
    private $shopifyApiVersion;

    public function __construct() {
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
    public static function getAdminURL($shop, $suffix) {
        return "https://{$shop}/admin/{$suffix}";
    }

    /**
     * Load environment variables from a .env file.
     */
    private function loadEnv() {
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
    public function getOfflineAccessToken($sessionToken, $shop) {
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
    private function makePostRequest($url, $data) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
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

    public function getToken() {
        $requestHeaders = getallheaders(); 
        $queryParams = $_GET;
    
        $token = $requestHeaders['Authorization'] ?? $queryParams['id_token'] ?? null;
    
        return $token;
    }

    public function handleInstall() {
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

    public function verifyToken() {
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
}