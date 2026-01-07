<?php
class Auth
{
    /**
     * Check of gebruiker is ingelogd
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    /**
     * Forceer login - redirect naar login pagina als niet ingelogd
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'admin.php');
            header('Location: admin_login.php?redirect=' . $redirect);
            exit;
        }
    }
    
    /**
     * Get huidige gebruiker info
     */
    public static function getCurrentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['admin_id'] ?? null,
            'username' => $_SESSION['admin_username'] ?? 'admin',
            'role' => $_SESSION['admin_role'] ?? 'admin'
        ];
    }
    
    /**
     * Login gebruiker
     */
    public static function login(string $username, string $password): bool
    {
        require_once __DIR__ . '/Database.php';
        $pdo = Database::getInstance()->getPdo();
        
        try {
            // FIX: gebruik password_hash kolom, niet password!
            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // FIX: verify tegen password_hash!
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = 'admin';
                
                return true;
            }
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log('Login error: ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Logout gebruiker
     */
    public static function logout(): void
    {
        $_SESSION = array();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        
        session_destroy();
    }
}