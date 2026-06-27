<?php
require_once 'config/config.php';

// Clear session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Destroy session
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to login
redirect('login.php');
