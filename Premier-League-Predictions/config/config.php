<?php
/**
 * Configuration File
 * Premier League Predictions - Naive Bayes
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'epl_naivebayes');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Configuration
define('APP_NAME', 'Premier League Predictions');
define('APP_URL', 'http://localhost/Premier-League-Predictions');

// Session Configuration
define('SESSION_NAME', 'epl_predictions_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Enforce session lifetime
if (!empty($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity'] > SESSION_LIFETIME)) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    header('Location: ' . APP_URL . '/login.php');
    exit;
}
$_SESSION['_last_activity'] = time();

/**
 * Database Connection
 */
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database Connection Error: " . $e->getMessage());
    }
}

/**
 * Check if Admin is Logged In
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

/**
 * Require Admin Login
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Get Current Admin Info
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'full_name' => $_SESSION['admin_full_name'] ?? '',
        'email' => $_SESSION['admin_email'] ?? ''
    ];
}

/**
 * Sanitize Input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function generateCsrfToken() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Validate CSRF Token
 */
function validateCsrfToken($token) {
    return isset($_SESSION['_csrf_token']) && !empty($token) && hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Get CSRF Hidden Input HTML
 */
function csrfField() {
    return '<input type="hidden" name="_csrf_token" value="' . generateCsrfToken() . '">';
}

/**
 * Redirect
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Set Flash Message (survives one redirect)
 */
function setFlash($type, $message) {
    $_SESSION['_flash_type'] = $type;
    $_SESSION['_flash_message'] = $message;
}

/**
 * Get and clear Flash Message
 */
function getFlash() {
    $type = $_SESSION['_flash_type'] ?? '';
    $message = $_SESSION['_flash_message'] ?? '';
    unset($_SESSION['_flash_type'], $_SESSION['_flash_message']);
    return ['type' => $type, 'message' => $message];
}

/**
 * User-safe database error message (hides internals, logs detail)
 */
function dbErrorMessage() {
    return 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.';
}
