<?php
// Example: rename to config/database.php and set real credentials
class Database {
    private $host = 'localhost';
    private $db_name = 'smart_marketing_rfm';
    private $username = 'root';
    private $password = ''; // set your MySQL password (XAMPP default is empty)
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';port=3306;dbname=' . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            $this->conn->exec('set names utf8');
        } catch (PDOException $exception) {
            echo 'Connection error: ' . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Global database connection helper
function getDB() {
    $database = new Database();
    return $database->getConnection();
}
?>

