<?php
/**
 * Email Subscription System - Rejected Emails Page
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

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = getDbConnection();

        if ($_POST['action'] === 'clear_all') {
            $stmt = $pdo->prepare("DELETE FROM rejected_emails");
            $stmt->execute();
            $success_message = "All rejected emails have been cleared.";
        }

    } catch (PDOException $e) {
        error_log("Rejected emails action error: " . $e->getMessage());
        $error_message = "Action failed. Please try again.";
    }
}

try {
    $pdo = getDbConnection();

    // Get pagination parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // Get filter parameters
    $reason_filter = $_GET['reason'] ?? '';
    $date_filter = $_GET['date'] ?? '';

    // Build WHERE clause
    $where_conditions = [];
    $params = [];

    if (!empty($reason_filter)) {
        $where_conditions[] = "rejection_reason LIKE ?";
        $params[] = "%{$reason_filter}%";
    }

    if (!empty($date_filter)) {
        $where_conditions[] = "DATE(submitted_at) = ?";
        $params[] = $date_filter;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM rejected_emails {$where_clause}";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_count = $stmt->fetchColumn();

    // Get rejected emails with pagination
    $sql = "
        SELECT email, rejection_reason, submitted_at, ip_address
        FROM rejected_emails
        {$where_clause}
        ORDER BY submitted_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rejected_emails = $stmt->fetchAll();

    // Calculate pagination
    $total_pages = ceil($total_count / $per_page);

    // Get rejection reason statistics
    $stats_sql = "
        SELECT rejection_reason, COUNT(*) as count
        FROM rejected_emails
        GROUP BY rejection_reason
        ORDER BY count DESC
    ";
    $stmt = $pdo->query($stats_sql);
    $rejection_stats = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Rejected emails page error: " . $e->getMessage());
    $error = "Database error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejected Emails - Email Subscription System</title>
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

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters h2 {
            margin-bottom: 1rem;
            color: #374151;
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .filter-input {
            width: 100%;
            padding: 0.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .filter-input:focus {
            outline: none;
            border-color: #2563eb;
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
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
            color: #ef4444;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-weight: 600;
            color: #374151;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
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

        .rejection-reason {
            font-weight: 500;
            color: #ef4444;
        }

        .email-address {
            font-family: monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .date-time {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .ip-address {
            font-family: monospace;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            color: #374151;
        }

        .pagination a:hover {
            background: #f3f4f6;
        }

        .pagination .current {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
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

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                min-width: auto;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
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
                <a href="/admin/index.php">Dashboard</a>
                <a href="/admin/export.php">Export CSV</a>
                <a href="/admin/rejected.php" class="active">Rejected Emails</a>
                <a href="/admin/test-email.php">Test Email</a>
                <a href="/admin/recover-emails.php">Recover Emails</a>
                <a href="/admin/logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_count); ?></div>
                <div class="stat-label">Total Rejected</div>
            </div>
            <?php if (!empty($rejection_stats)): ?>
                <?php foreach (array_slice($rejection_stats, 0, 3) as $stat): ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stat['count']); ?></div>
                        <div class="stat-label"><?php echo htmlspecialchars($stat['rejection_reason']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h2>Filter Rejected Emails</h2>
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="reason" class="filter-label">Rejection Reason</label>
                        <input
                            type="text"
                            id="reason"
                            name="reason"
                            class="filter-input"
                            placeholder="e.g., invalid format, disposable"
                            value="<?php echo htmlspecialchars($reason_filter); ?>"
                        >
                    </div>
                    <div class="filter-group">
                        <label for="date" class="filter-label">Date</label>
                        <input
                            type="date"
                            id="date"
                            name="date"
                            class="filter-input"
                            value="<?php echo htmlspecialchars($date_filter); ?>"
                        >
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="/admin/rejected.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Rejected Emails Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    Rejected Emails
                    <?php if ($total_count > 0): ?>
                        (<?php echo number_format($total_count); ?> total)
                    <?php endif; ?>
                </div>
                <?php if ($total_count > 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all rejected emails? This cannot be undone.');">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="btn btn-danger">Clear All</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($rejected_emails)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No rejected emails found</h3>
                    <p>
                        <?php if (!empty($reason_filter) || !empty($date_filter)): ?>
                            No emails match your current filters.
                        <?php else: ?>
                            All submitted emails have been successfully validated and accepted.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Email Address</th>
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
                                    <span class="rejection-reason"><?php echo htmlspecialchars($email['rejection_reason']); ?></span>
                                </td>
                                <td>
                                    <span class="date-time"><?php echo date('M j, Y g:i A', strtotime($email['submitted_at'])); ?></span>
                                </td>
                                <td>
                                    <span class="ip-address"><?php echo htmlspecialchars($email['ip_address']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($reason_filter) ? '&reason=' . urlencode($reason_filter) : ''; ?><?php echo !empty($date_filter) ? '&date=' . urlencode($date_filter) : ''; ?>">← Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($reason_filter) ? '&reason=' . urlencode($reason_filter) : ''; ?><?php echo !empty($date_filter) ? '&date=' . urlencode($date_filter) : ''; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($reason_filter) ? '&reason=' . urlencode($reason_filter) : ''; ?><?php echo !empty($date_filter) ? '&date=' . urlencode($date_filter) : ''; ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>