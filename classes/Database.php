<?php
// DB connection and schema initialization
class Database {
    private $pdo;

    public function __construct() {
        // Local connection settings
        $host = '127.0.0.1';
        $dbName = 'csv_analytics';
        $username = 'root';
        $password = '';
        
        try {
            // Connect to server to check/create database
            $this->pdo = new PDO("mysql:host=$host", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            
            // Connect to specific database instance
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Disable emulated prepares to ensure proper data types
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            $this->setupDatabase();
            
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    private function setupDatabase() {
        // Table to track uploaded files
        $query1 = "
            CREATE TABLE IF NOT EXISTS uploads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                total_rows INT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'pending',
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ";
        $this->pdo->exec($query1);

        // Table to store imported sales data
        $query2 = "
            CREATE TABLE IF NOT EXISTS sales_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                upload_id INT,
                order_id VARCHAR(50),
                order_date DATE,
                customer_name VARCHAR(255),
                product_name VARCHAR(255),
                category VARCHAR(100),
                price DECIMAL(10,2),
                quantity INT,
                region VARCHAR(100),
                profit DECIMAL(10,2),
                FOREIGN KEY(upload_id) REFERENCES uploads(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ";
        $this->pdo->exec($query2);

        // Create indexes to optimize query performance
        try { $this->pdo->exec("CREATE INDEX idx_category ON sales_data(category)"); } catch (PDOException $e) {}
        try { $this->pdo->exec("CREATE INDEX idx_region ON sales_data(region)"); } catch (PDOException $e) {}
        try { $this->pdo->exec("CREATE INDEX idx_date ON sales_data(order_date)"); } catch (PDOException $e) {}
    }

    public function getConnection() {
        return $this->pdo;
    }
}
