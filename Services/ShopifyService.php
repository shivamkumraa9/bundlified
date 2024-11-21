<?php

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
        $envFile = __DIR__ . '/.env';
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
}

// Example usage
try {
    $shopifyService = new ShopifyService();
    $token = $shopifyService->getOfflineAccessToken('your_session_token', 'yourshop.myshopify.com');
    print_r($token);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
