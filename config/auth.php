<?php
// config/auth.php
session_start();
require_once __DIR__ . '/database.php';

class AuthManager {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, email, password, full_name, role, is_active FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Create session
            $sessionToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $stmt = $this->db->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $sessionToken, $expiresAt]);
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['session_token'] = $sessionToken;
            
            // Log activity
            $this->logActivity($user['id'], 'login', 'User logged in');
            
            return ['success' => true, 'user' => $user];
        }
        
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            // Remove session from database
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
            
            // Log activity
            if (isset($_SESSION['user_id'])) {
                $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
            }
        }
        
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Check if session is still valid
        $stmt = $this->db->prepare("SELECT user_id FROM user_sessions WHERE session_token = ? AND expires_at > NOW()");
        $stmt->execute([$_SESSION['session_token']]);
        
        return $stmt->fetch() !== false;
    }
    
    public function requireAuth($allowedRoles = []) {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
        
        if (!empty($allowedRoles) && !in_array($_SESSION['user_role'], $allowedRoles)) {
            header('Location: unauthorized.php');
            exit;
        }
        
        return true;
    }
    
    public function getUserBusiness($userId) {
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    public function logActivity($userId, $action, $description, $businessId = null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'localhost';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $this->db->prepare("INSERT INTO activity_logs (user_id, business_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $businessId, $action, $description, $ipAddress, $userAgent]);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role']
        ];
    }
}

// Global auth helper functions
function auth() {
    static $auth = null;
    if ($auth === null) {
        $auth = new AuthManager();
    }
    return $auth;
}

function requireAuth($allowedRoles = []) {
    return auth()->requireAuth($allowedRoles);
}

function getCurrentUser() {
    return auth()->getCurrentUser();
}

function isLoggedIn() {
    return auth()->isLoggedIn();
}
?>
