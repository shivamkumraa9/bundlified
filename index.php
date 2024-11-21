<?php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}
echo "Hello, World!";

$host = getenv('SHOPIFY_HOST');
if ($host) {
    echo "HOST: $host";
} else {
    echo "HOST environment variable is not set.";
}
