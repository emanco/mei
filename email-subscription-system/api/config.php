<?php
/**
 * Email Subscription System - Database Configuration
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'email_subscription');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Email configuration (for future use)
define('SMTP_HOST', 'smtp.example.com'); // Replace with your SMTP server
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@example.com'); // Replace with your email
define('SMTP_PASS', 'your-password'); // Replace with your password
define('FROM_EMAIL', 'noreply@example.com'); // Replace with your from email
define('FROM_NAME', 'Your Newsletter'); // Replace with your name

// Security settings
define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);

// Validation settings
define('MAX_DAILY_SUBSCRIPTIONS_PER_IP', 10);
define('ENABLE_DISPOSABLE_EMAIL_CHECK', true);

// Common disposable email domains
$disposable_domains = [
    '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
    'yopmail.com', 'tempmail.org', 'throwaway.email'
];

/**
 * Get database connection
 */
function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed']));
        }
    }

    return $pdo;
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Generate secure token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Set CORS headers
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Return JSON response
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>