<?php
/**
 * Email Subscription System - Subscribe API Endpoint
 */

require_once 'config.php';
require_once 'EmailValidator.php';

// Set headers
setCorsHeaders();
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['email'])) {
        jsonResponse(['success' => false, 'message' => 'Email is required'], 400);
    }

    $email = strtolower(trim($input['email']));
    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Get database connection
    $pdo = getDbConnection();

    // Initialize validator
    global $disposable_domains;
    $validator = new EmailValidator($disposable_domains);

    // Rate limiting check
    if (!$validator->checkRateLimit($ip_address, $pdo)) {
        // Log rejected email
        $stmt = $pdo->prepare("
            INSERT INTO rejected_emails (email, rejection_reason, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$email, 'Rate limit exceeded', $ip_address, $user_agent]);

        jsonResponse([
            'success' => true, // Still return success to user (UX decision)
            'message' => 'Thank you for your interest! You will receive updates soon.'
        ]);
    }

    // Validate email
    $validation_result = $validator->validate($email);

    if (!$validation_result['valid']) {
        // Log rejected email
        $stmt = $pdo->prepare("
            INSERT INTO rejected_emails (email, rejection_reason, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$email, $validation_result['reason'], $ip_address, $user_agent]);

        // Return success to user (frictionless UX)
        jsonResponse([
            'success' => true,
            'message' => 'Thank you for subscribing! Please check your email for confirmation.'
        ]);
    }

    // Use corrected email if validator fixed typos
    $final_email = $validation_result['email'];

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id, status FROM subscribers WHERE email = ?");
    $stmt->execute([$final_email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'unsubscribed') {
            // Reactivate unsubscribed user
            $stmt = $pdo->prepare("
                UPDATE subscribers
                SET status = 'active', subscribed_at = CURRENT_TIMESTAMP, ip_address = ?, user_agent = ?
                WHERE email = ?
            ");
            $stmt->execute([$ip_address, $user_agent, $final_email]);

            jsonResponse([
                'success' => true,
                'message' => 'Welcome back! Your subscription has been reactivated.'
            ]);
        } else {
            // Already subscribed
            jsonResponse([
                'success' => true,
                'message' => 'You are already subscribed to our newsletter.'
            ]);
        }
    }

    // Generate unsubscribe token
    $unsubscribe_token = generateSecureToken();

    // Insert new subscriber
    $stmt = $pdo->prepare("
        INSERT INTO subscribers (email, ip_address, user_agent, unsubscribe_token, validation_score)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $final_email,
        $ip_address,
        $user_agent,
        $unsubscribe_token,
        $validation_result['score']
    ]);

    // Log success
    error_log("New subscription: {$final_email} from IP: {$ip_address}");

    jsonResponse([
        'success' => true,
        'message' => 'Thank you for subscribing! You will receive updates from us soon.',
        'corrected' => ($final_email !== $email) ? $final_email : null
    ]);

} catch (PDOException $e) {
    error_log("Database error in subscribe.php: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
} catch (Exception $e) {
    error_log("Error in subscribe.php: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
}
?>