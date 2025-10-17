<?php
/**
 * Email Subscription System - CSV Export
 */

session_start();
require_once '../api/config.php';

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = getDbConnection();

    // Get all active subscribers
    $stmt = $pdo->query("
        SELECT email, subscribed_at, validation_score, ip_address, status
        FROM subscribers
        WHERE status = 'active'
        ORDER BY subscribed_at DESC
    ");
    $subscribers = $stmt->fetchAll();

    // Set headers for CSV download
    $filename = 'subscribers_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add CSV header
    fputcsv($output, ['Email', 'Subscribed Date', 'Validation Score', 'IP Address', 'Status']);

    // Add data rows
    foreach ($subscribers as $subscriber) {
        fputcsv($output, [
            $subscriber['email'],
            $subscriber['subscribed_at'],
            $subscriber['validation_score'],
            $subscriber['ip_address'],
            $subscriber['status']
        ]);
    }

    fclose($output);

} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    echo "Export failed. Please try again.";
}
?>