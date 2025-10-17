<?php
/**
 * Email Subscription System - Unsubscribe API Endpoint
 */

require_once 'config.php';

// Set headers
setCorsHeaders();
header('Content-Type: application/json');

// Allow both GET and POST for unsubscribe
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $email = '';
    $token = '';

    // Handle different request methods
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $token = $input['token'] ?? '';
    } else {
        $email = $_GET['email'] ?? '';
        $token = $_GET['token'] ?? '';
    }

    // Validate input
    if (empty($email)) {
        jsonResponse(['success' => false, 'message' => 'Email is required'], 400);
    }

    $email = strtolower(trim($email));

    // Get database connection
    $pdo = getDbConnection();

    // If token is provided, use it for direct unsubscribe
    if (!empty($token)) {
        $stmt = $pdo->prepare("
            SELECT id, email, status
            FROM subscribers
            WHERE email = ? AND unsubscribe_token = ? AND status = 'active'
        ");
        $stmt->execute([$email, $token]);
        $subscriber = $stmt->fetch();

        if (!$subscriber) {
            jsonResponse(['success' => false, 'message' => 'Invalid unsubscribe link'], 400);
        }

        // Update status to unsubscribed
        $stmt = $pdo->prepare("
            UPDATE subscribers
            SET status = 'unsubscribed'
            WHERE id = ?
        ");
        $stmt->execute([$subscriber['id']]);

        jsonResponse([
            'success' => true,
            'message' => 'You have been successfully unsubscribed from our newsletter.'
        ]);
    }

    // Email-only unsubscribe (for the simple unsubscribe page)
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("
            UPDATE subscribers
            SET status = 'unsubscribed'
            WHERE email = ? AND status = 'active'
        ");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            jsonResponse([
                'success' => true,
                'message' => 'You have been successfully unsubscribed from our newsletter.'
            ]);
        } else {
            jsonResponse([
                'success' => true,
                'message' => 'Email not found or already unsubscribed.'
            ]);
        }
    } else {
        jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
    }

} catch (PDOException $e) {
    error_log("Database error in unsubscribe.php: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
} catch (Exception $e) {
    error_log("Error in unsubscribe.php: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
}
?>