<?php
/**
 * Email Subscription System - Admin Dashboard
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

try {
    $pdo = getDbConnection();

    // Get statistics
    $stats = [];

    // Total subscribers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM subscribers WHERE status = 'active'");
    $stats['total_subscribers'] = $stmt->fetchColumn();

    // Today's signups
    $stmt = $pdo->query("SELECT COUNT(*) as today FROM subscribers WHERE DATE(subscribed_at) = CURDATE()");
    $stats['today_signups'] = $stmt->fetchColumn();

    // This week's signups
    $stmt = $pdo->query("SELECT COUNT(*) as week FROM subscribers WHERE subscribed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['week_signups'] = $stmt->fetchColumn();

    // Total rejected
    $stmt = $pdo->query("SELECT COUNT(*) as rejected FROM rejected_emails");
    $stats['total_rejected'] = $stmt->fetchColumn();

    // Recent subscribers
    $stmt = $pdo->query("
        SELECT email, subscribed_at, validation_score, ip_address
        FROM subscribers
        WHERE status = 'active'
        ORDER BY subscribed_at DESC
        LIMIT 10
    ");
    $recent_subscribers = $stmt->fetchAll();

    // Recent rejected
    $stmt = $pdo->query("
        SELECT email, rejection_reason, submitted_at
        FROM rejected_emails
        ORDER BY submitted_at DESC
        LIMIT 10
    ");
    $recent_rejected = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "Database error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Email Subscription System</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .panel {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .panel-header {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }

        .panel-content {
            padding: 1.5rem;
        }

        .subscriber-list,
        .rejected-list {
            list-style: none;
        }

        .subscriber-item,
        .rejected-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .subscriber-item:last-child,
        .rejected-item:last-child {
            border-bottom: none;
        }

        .subscriber-email {
            font-weight: 500;
            color: #1f2937;
        }

        .subscriber-meta {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .rejection-reason {
            font-size: 0.875rem;
            color: #ef4444;
            font-weight: 500;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
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
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>Email Subscription Dashboard</h1>
            <nav class="nav-links">
                <a href="/admin/index.php" class="active">Dashboard</a>
                <a href="/admin/export.php">Export CSV</a>
                <a href="/admin/rejected.php">Rejected Emails</a>
                <a href="/admin/test-email.php">Test Email</a>
                <a href="/admin/recover-emails.php">Recover Emails</a>
                <a href="/admin/logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_subscribers']); ?></div>
                <div class="stat-label">Total Subscribers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['today_signups']); ?></div>
                <div class="stat-label">Today's Signups</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['week_signups']); ?></div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_rejected']); ?></div>
                <div class="stat-label">Rejected Emails</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions">
            <a href="/admin/export.php" class="btn btn-primary">Export All Subscribers</a>
            <a href="/admin/test-email.php" class="btn btn-secondary">Send Test Email</a>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Subscribers -->
            <div class="panel">
                <div class="panel-header">Recent Subscribers</div>
                <div class="panel-content">
                    <?php if (empty($recent_subscribers)): ?>
                        <div class="empty-state">
                            No subscribers yet.
                        </div>
                    <?php else: ?>
                        <ul class="subscriber-list">
                            <?php foreach ($recent_subscribers as $subscriber): ?>
                                <li class="subscriber-item">
                                    <div>
                                        <div class="subscriber-email"><?php echo htmlspecialchars($subscriber['email']); ?></div>
                                        <div class="subscriber-meta">
                                            <?php echo date('M j, Y g:i A', strtotime($subscriber['subscribed_at'])); ?>
                                            • Score: <?php echo $subscriber['validation_score']; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Rejected -->
            <div class="panel">
                <div class="panel-header">Recent Rejected Emails</div>
                <div class="panel-content">
                    <?php if (empty($recent_rejected)): ?>
                        <div class="empty-state">
                            No rejected emails.
                        </div>
                    <?php else: ?>
                        <ul class="rejected-list">
                            <?php foreach ($recent_rejected as $rejected): ?>
                                <li class="rejected-item">
                                    <div>
                                        <div class="subscriber-email"><?php echo htmlspecialchars($rejected['email']); ?></div>
                                        <div class="rejection-reason"><?php echo htmlspecialchars($rejected['rejection_reason']); ?></div>
                                        <div class="subscriber-meta">
                                            <?php echo date('M j, Y g:i A', strtotime($rejected['submitted_at'])); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // Update stat values
                    const statsGrid = document.querySelector('.stats-grid');
                    const newStatsGrid = doc.querySelector('.stats-grid');
                    if (newStatsGrid) {
                        statsGrid.innerHTML = newStatsGrid.innerHTML;
                    }
                })
                .catch(error => console.log('Auto-refresh error:', error));
        }, 30000);
    </script>
</body>
</html>