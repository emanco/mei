<?php
/**
 * Email Subscription System - Test Email Page
 */

session_start();
require_once '../api/config.php';

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit();
}

// Check session timeout
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > ADMIN_SESSION_TIMEOUT) {
    session_destroy();
    header('Location: /admin/login.php?timeout=1');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = sanitizeInput($_POST['test_email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? 'Test Email');
    $body = sanitizeInput($_POST['body'] ?? '');

    if (empty($test_email)) {
        $message = 'Please enter an email address.';
        $message_type = 'error';
    } elseif (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } elseif (empty($body)) {
        $message = 'Please enter a message body.';
        $message_type = 'error';
    } else {
        // TODO: Implement email sending functionality
        // This would use PHPMailer or similar to send test emails
        // For now, we'll just show a placeholder message

        $message = "Test email functionality is not yet configured. To enable email sending:\n\n";
        $message .= "1. Configure SMTP settings in api/config.php\n";
        $message .= "2. Install PHPMailer: composer require phpmailer/phpmailer\n";
        $message .= "3. Update this page to use PHPMailer for sending\n\n";
        $message .= "Email would be sent to: " . htmlspecialchars($test_email) . "\n";
        $message .= "Subject: " . htmlspecialchars($subject) . "\n";
        $message .= "Body: " . htmlspecialchars($body);
        $message_type = 'info';
    }
}

try {
    $pdo = getDbConnection();

    // Get recent subscribers for testing
    $stmt = $pdo->query("
        SELECT email
        FROM subscribers
        WHERE status = 'active'
        ORDER BY subscribed_at DESC
        LIMIT 5
    ");
    $recent_subscribers = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Test email page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email - Email Subscription System</title>
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
            max-width: 800px;
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

        .card h2 {
            margin-bottom: 1rem;
            color: #374151;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-input,
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            margin-left: 0.5rem;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            white-space: pre-line;
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

        .recent-emails {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
        }

        .recent-emails h3 {
            margin-bottom: 0.5rem;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .email-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .email-tag {
            background: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            color: #374151;
            cursor: pointer;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .email-tag:hover {
            background: #f3f4f6;
            border-color: #2563eb;
        }

        .info-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .info-box h3 {
            color: #92400e;
            margin-bottom: 0.5rem;
        }

        .info-box p {
            color: #92400e;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .info-box ul {
            color: #92400e;
            font-size: 0.875rem;
            margin-left: 1rem;
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

            .container {
                padding: 0 0.5rem;
            }

            .card {
                padding: 1rem;
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
                <a href="/admin/test-email.php" class="active">Test Email</a>
                <a href="/admin/recover-emails.php">Recover Emails</a>
                <a href="/admin/logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="info-box">
            <h3>⚠️ Email Configuration Required</h3>
            <p>The email sending functionality is not yet configured. To enable email sending:</p>
            <ul>
                <li>Configure SMTP settings in <code>api/config.php</code></li>
                <li>Install PHPMailer: <code>composer require phpmailer/phpmailer</code></li>
                <li>Update this page to implement actual email sending</li>
            </ul>
            <p>For now, this page serves as a preview of the email testing interface.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Send Test Email</h2>
            <p style="color: #6b7280; margin-bottom: 1.5rem;">
                Send a test email to verify your email configuration and preview how your newsletter will look.
            </p>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="test_email" class="form-label">Recipient Email Address</label>
                    <input
                        type="email"
                        id="test_email"
                        name="test_email"
                        class="form-input"
                        placeholder="Enter email address to send test to"
                        value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>"
                        required
                    >

                    <?php if (!empty($recent_subscribers)): ?>
                        <div class="recent-emails">
                            <h3>Recent Subscribers (click to use)</h3>
                            <div class="email-list">
                                <?php foreach ($recent_subscribers as $subscriber): ?>
                                    <span class="email-tag" onclick="document.getElementById('test_email').value = '<?php echo htmlspecialchars($subscriber['email']); ?>'">
                                        <?php echo htmlspecialchars($subscriber['email']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="subject" class="form-label">Email Subject</label>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        class="form-input"
                        placeholder="Enter email subject"
                        value="<?php echo htmlspecialchars($_POST['subject'] ?? 'Test Email from Newsletter System'); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="body" class="form-label">Email Body</label>
                    <textarea
                        id="body"
                        name="body"
                        class="form-textarea"
                        placeholder="Enter your test email message here..."
                        required
                    ><?php echo htmlspecialchars($_POST['body'] ?? "This is a test email from your newsletter subscription system.\n\nIf you're receiving this, your email configuration is working correctly!\n\nBest regards,\nYour Newsletter Team"); ?></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Send Test Email</button>
                    <button type="button" class="btn btn-secondary" onclick="document.forms[0].reset();">Clear Form</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Email Configuration Status</h2>
            <div style="color: #6b7280;">
                <p><strong>SMTP Configuration:</strong> Not configured</p>
                <p><strong>PHPMailer:</strong> Not installed</p>
                <p><strong>Email Templates:</strong> Database ready</p>
                <p><strong>Unsubscribe Links:</strong> Ready</p>
            </div>

            <div style="margin-top: 1.5rem; padding: 1rem; background: #f3f4f6; border-radius: 6px;">
                <h3 style="color: #374151; margin-bottom: 0.5rem;">Next Steps:</h3>
                <ol style="color: #6b7280; margin-left: 1rem;">
                    <li>Configure SMTP settings in <code>api/config.php</code></li>
                    <li>Install PHPMailer: <code>composer require phpmailer/phpmailer</code></li>
                    <li>Update this page to implement PHPMailer integration</li>
                    <li>Create email templates in the database</li>
                    <li>Test email delivery and formatting</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>