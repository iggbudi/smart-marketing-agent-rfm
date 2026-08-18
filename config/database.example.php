<?php
// Example: rename to config/database.php (atau set env var) untuk kredensial asli.
// Kredensial dibaca dengan prioritas:
//   env var (SetEnv Apache / systemd Environment / export shell)
//   > file .env di root project (lihat .env.example)
//   > default di bawah (aman untuk dev lokal XAMPP: user root tanpa password).
require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct()
    {
        $this->host = env('DB_HOST', 'localhost');
        $this->port = env('DB_PORT', '3306');
        $this->db_name = env('DB_NAME', 'smart_marketing_rfm');
        $this->username = env('DB_USER', 'root');
        $this->password = env('DB_PASSWORD', '');
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            $this->conn->exec('set names utf8');
        } catch (PDOException $exception) {
            // Jangan bocorkan detail koneksi ke output; log detail, lalu lempar exception netral
            error_log('DB connection error: ' . $exception->getMessage());
            throw new RuntimeException('Terjadi gangguan koneksi database. Silakan coba lagi atau hubungi administrator.');
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
