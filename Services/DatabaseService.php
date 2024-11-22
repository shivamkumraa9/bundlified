<?php

class DatabaseService {

    private $dbHost = 'localhost'; // Database host
    private $dbUsername = 'root'; // Database username
    private $dbPassword = 'root'; // Database password
    private $dbName = 'bundlified'; // Database name

    private $connection;

    // Constructor to initialize the connection
    public function __construct() {
        $this->connectDatabase();
    }

    // Establish database connection using mysqli
    private function connectDatabase() {
        // Create connection
        $this->connection = new mysqli($this->dbHost, $this->dbUsername, $this->dbPassword);

        // Check connection
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }

        $this->connection->select_db($this->dbName);
    }

    // Create the database if it doesn't exist
    private function createDatabase() {
        $sql = "CREATE DATABASE IF NOT EXISTS {$this->dbName}";
        if ($this->connection->query($sql) === TRUE) {
            echo "Database '{$this->dbName}' is ready.";
        } else {
            echo "Error creating database: " . $this->connection->error;
        }

        // Select the database to work with
        $this->connection->select_db($this->dbName);
    }

    // Create the shop table if it doesn't exist (based on the schema you provided)
    public function createShopTable() {
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

    // Function to fetch data from a table
    public function getData($query) {
        $result = $this->connection->query($query);

        if ($result === FALSE) {
            die("Error executing query: " . $this->connection->error);
        }

        // Fetch all rows as an associative array
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Function to execute an INSERT, UPDATE, or DELETE query
    public function executeQuery($query) {
        if ($this->connection->query($query) === TRUE) {
            return true;
        } else {
            die("Error executing query: " . $this->connection->error);
        }
    }

    public function closeConnection() {
        $this->connection->close();
    }

    public function getAccessTokenForShop($shop) {
        $query = "SELECT accessToken FROM shop WHERE shop = '$shop' LIMIT 1";
        $result = $this->getData($query);
        return isset($result[0]['accessToken']) ? $result[0]['accessToken'] : null;
    }
}

?>
