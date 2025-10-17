<?php
/**
 * Email Subscription System - Email Recovery Tool
 */

session_start();
require_once '../api/config.php';
require_once '../api/EmailValidator.php';

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit();
}

$message = '';
$message_type = '';
$recovery_results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $pdo = getDbConnection();
            $validator = new EmailValidator($disposable_domains);

            if ($_POST['action'] === 'revalidate_all') {
                // Re-validate all rejected emails
                $stmt = $pdo->query("SELECT id, email, rejection_reason FROM rejected_emails ORDER BY submitted_at DESC");
                $rejected_emails = $stmt->fetchAll();

                foreach ($rejected_emails as $rejected) {
                    $validation = $validator->validate($rejected['email']);

                    $recovery_results[] = [
                        'original_email' => $rejected['email'],
                        'corrected_email' => $validation['email'],
                        'old_reason' => $rejected['rejection_reason'],
                        'now_valid' => $validation['valid'],
                        'new_reason' => $validation['reason'],
                        'score' => $validation['score'],
                        'id' => $rejected['id']
                    ];
                }

                $message = "Re-validation complete. Review results below.";
                $message_type = 'info';

            } elseif ($_POST['action'] === 'recover_selected') {
                $selected_ids = $_POST['selected_emails'] ?? [];
                $recovered_count = 0;
                $failed_count = 0;

                foreach ($selected_ids as $rejected_id) {
                    // Get the rejected email
                    $stmt = $pdo->prepare("SELECT email FROM rejected_emails WHERE id = ?");
                    $stmt->execute([$rejected_id]);
                    $rejected_email = $stmt->fetchColumn();

                    if ($rejected_email) {
                        // Re-validate
                        $validation = $validator->validate($rejected_email);

                        if ($validation['valid']) {
                            // Check if already subscribed
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE email = ?");
                            $stmt->execute([$validation['email']]);

                            if ($stmt->fetchColumn() == 0) {
                                // Add to subscribers
                                $stmt = $pdo->prepare("
                                    INSERT INTO subscribers (email, subscribed_at, validation_score, ip_address, status, unsubscribe_token)
                                    VALUES (?, NOW(), ?, '127.0.0.1', 'active', ?)
                                ");
                                $stmt->execute([
                                    $validation['email'],
                                    $validation['score'],
                                    generateSecureToken()
                                ]);

                                // Remove from rejected
                                $stmt = $pdo->prepare("DELETE FROM rejected_emails WHERE id = ?");
                                $stmt->execute([$rejected_id]);

                                $recovered_count++;
                            } else {
                                // Just remove from rejected (already subscribed)
                                $stmt = $pdo->prepare("DELETE FROM rejected_emails WHERE id = ?");
                                $stmt->execute([$rejected_id]);
                                $recovered_count++;
                            }
                        } else {
                            $failed_count++;
                        }
                    }
                }

                $message = "Recovery complete: {$recovered_count} emails recovered, {$failed_count} still invalid.";
                $message_type = $recovered_count > 0 ? 'success' : 'error';
            }

        } catch (PDOException $e) {
            error_log("Email recovery error: " . $e->getMessage());
            $message = "Recovery failed. Please try again.";
            $message_type = 'error';
        }
    }
}

try {
    $pdo = getDbConnection();

    // Get rejected emails
    $stmt = $pdo->query("
        SELECT id, email, rejection_reason, submitted_at, ip_address
        FROM rejected_emails
        ORDER BY submitted_at DESC
    ");
    $rejected_emails = $stmt->fetchAll();

    // Get recovery statistics
    $total_rejected = count($rejected_emails);

    // Count by reason
    $stmt = $pdo->query("
        SELECT rejection_reason, COUNT(*) as count
        FROM rejected_emails
        GROUP BY rejection_reason
        ORDER BY count DESC
    ");
    $reason_stats = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Email recovery page error: " . $e->getMessage());
    $error = "Database error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Recovery - Email Subscription System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f9fafb;
            color: #1f2937;
            line-height: 1.6;
        }

        .header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #1f2937;
            font-size: 1.5rem;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-links a {
            color: #6b7280;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: #f3f4f6;
            color: #1f2937;
        }

        .logout-btn {
            background: #ef4444 !important;
            color: white !important;
        }

        .logout-btn:hover {
            background: #dc2626 !important;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #f59e0b;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-success {
            background: #059669;
            color: white;
        }

        .btn-success:hover {
            background: #047857;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }

        .table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        .table tbody tr:hover {
            background: #f9fafb;
        }

        .recovery-table {
            margin-top: 2rem;
        }

        .recovery-valid {
            background: #d1fae5;
            color: #065f46;
        }

        .recovery-invalid {
            background: #fee2e2;
            color: #991b1b;
        }

        .email-address {
            font-family: monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .reason {
            font-size: 0.875rem;
            color: #ef4444;
        }

        .score {
            font-weight: 600;
            color: #059669;
        }

        .checkbox-cell {
            text-align: center;
            width: 50px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .recovery-actions {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>Email Subscription Dashboard</h1>
            <nav class="nav-links">
                <a href="/admin/index.php">Dashboard</a>
                <a href="/admin/export.php">Export CSV</a>
                <a href="/admin/rejected.php">Rejected Emails</a>
                <a href="/admin/test-email.php">Test Email</a>
                <a href="/admin/recover-emails.php" class="active">Recover Emails</a>
                <a href="/admin/logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_rejected); ?></div>
                <div class="stat-label">Total Rejected</div>
            </div>
            <?php foreach (array_slice($reason_stats, 0, 3) as $stat): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stat['count']); ?></div>
                    <div class="stat-label"><?php echo htmlspecialchars($stat['rejection_reason']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recovery Tools -->
        <div class="card">
            <h2>🔄 Email Recovery Tools</h2>
            <p style="color: #6b7280; margin-bottom: 2rem;">
                Use these tools to recover emails that were incorrectly rejected due to temporary issues or validation improvements.
            </p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="revalidate_all">
                <button type="submit" class="btn btn-primary">
                    🔍 Re-validate All Rejected Emails
                </button>
            </form>

            <p style="margin-top: 1rem; font-size: 0.875rem; color: #6b7280;">
                This will re-run validation on all rejected emails using the current validation rules.
            </p>
        </div>

        <!-- Recovery Results -->
        <?php if (!empty($recovery_results)): ?>
            <div class="card">
                <h3>📊 Recovery Analysis Results</h3>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="recover_selected">

                    <div class="table-container">
                        <table class="table recovery-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-cell">
                                        <input type="checkbox" id="select-all" onchange="toggleAll(this)">
                                    </th>
                                    <th>Original Email</th>
                                    <th>Corrected Email</th>
                                    <th>Old Reason</th>
                                    <th>Status Now</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recovery_results as $result): ?>
                                    <tr class="<?php echo $result['now_valid'] ? 'recovery-valid' : 'recovery-invalid'; ?>">
                                        <td class="checkbox-cell">
                                            <?php if ($result['now_valid']): ?>
                                                <input type="checkbox" name="selected_emails[]" value="<?php echo $result['id']; ?>" class="email-checkbox">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="email-address"><?php echo htmlspecialchars($result['original_email']); ?></span>
                                        </td>
                                        <td>
                                            <span class="email-address"><?php echo htmlspecialchars($result['corrected_email']); ?></span>
                                            <?php if ($result['original_email'] !== $result['corrected_email']): ?>
                                                <small style="color: #f59e0b;"> (corrected)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="reason"><?php echo htmlspecialchars($result['old_reason']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($result['now_valid']): ?>
                                                <strong style="color: #059669;">✅ Valid</strong>
                                            <?php else: ?>
                                                <span style="color: #ef4444;">❌ <?php echo htmlspecialchars($result['new_reason']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($result['now_valid']): ?>
                                                <span class="score"><?php echo $result['score']; ?></span>
                                            <?php else: ?>
                                                <span style="color: #6b7280;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="recovery-actions">
                        <button type="submit" class="btn btn-success">
                            ✅ Recover Selected Valid Emails
                        </button>
                        <p style="margin-top: 1rem; font-size: 0.875rem; color: #6b7280;">
                            Selected valid emails will be moved to the subscribers list and removed from rejected emails.
                        </p>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Current Rejected Emails -->
        <?php if (!empty($rejected_emails)): ?>
            <div class="card">
                <h3>📋 Current Rejected Emails</h3>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Rejection Reason</th>
                                <th>Submitted</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rejected_emails as $email): ?>
                                <tr>
                                    <td>
                                        <span class="email-address"><?php echo htmlspecialchars($email['email']); ?></span>
                                    </td>
                                    <td>
                                        <span class="reason"><?php echo htmlspecialchars($email['rejection_reason']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', strtotime($email['submitted_at'])); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($email['ip_address']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>🎉 No Rejected Emails!</h3>
                <p>All submitted emails have been successfully validated and accepted.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.email-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
    </script>
</body>
</html>