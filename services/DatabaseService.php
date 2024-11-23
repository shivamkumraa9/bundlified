<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/AppService.php';

class DatabaseService
{
    private $dbHost;
    private $dbUsername;
    private $dbPassword;
    private $dbName;

    private $connection;

    public function __construct()
    {
        AppService::loadEnv();

        $this->dbHost = getenv('DB_HOST');
        $this->dbUsername = getenv('DB_USERNAME');
        $this->dbPassword = getenv('DB_PASSWORD');
        $this->dbName = getenv('DB_NAME');

        $this->connectDatabase();
    }

    private function connectDatabase()
    {
        $this->connection = new mysqli($this->dbHost, $this->dbUsername, $this->dbPassword);

        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }

        $this->connection->select_db($this->dbName);
    }

    private function createDatabase()
    {
        $sql = "CREATE DATABASE IF NOT EXISTS {$this->dbName}";
        if ($this->connection->query($sql) === TRUE) {
            echo "Database '{$this->dbName}' is ready.";
        } else {
            echo "Error creating database: " . $this->connection->error;
        }

        $this->connection->select_db($this->dbName);
    }

    public function createShopTable()
    {
        $this->createDatabase();
        $sql = "CREATE TABLE IF NOT EXISTS shop (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            shop VARCHAR(255) NOT NULL UNIQUE,
            accessToken TEXT,
            status ENUM('INSTALLED', 'UNINSTALLED') DEFAULT 'INSTALLED',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        if ($this->connection->query($sql) === TRUE) {
            echo "Table 'shop' created successfully.";
        } else {
            echo "Error creating table: " . $this->connection->error;
        }
    }

    public function getData($query)
    {
        $result = $this->connection->query($query);

        if ($result === FALSE) {
            die("Error executing query: " . $this->connection->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function executeQuery($query)
    {
        if ($this->connection->query($query) === TRUE) {
            return true;
        } else {
            die("Error executing query: " . $this->connection->error);
        }
    }

    public function closeConnection()
    {
        $this->connection->close();
    }

    public function getAccessTokenForShop($shop)
    {
        $query = "SELECT accessToken FROM shop WHERE shop = '$shop' LIMIT 1";
        $result = $this->getData($query);
        return isset($result[0]['accessToken']) ? $result[0]['accessToken'] : null;
    }
}
