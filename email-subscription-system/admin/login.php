<?php
/**
 * Email Subscription System - Admin Login
 */

session_start();
require_once '../api/config.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        try {
            $pdo = getDbConnection();

            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Update last login
                $stmt = $pdo->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$admin['id']]);

                // Set session
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['login_time'] = time();

                header('Location: index.php');
                exit();

            } else {
                $error_message = 'Invalid username or password.';
            }

        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Email Subscription System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: #1f2937;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .submit-btn {
            width: 100%;
            padding: 0.75rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .submit-btn:hover {
            background: #1d4ed8;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .back-link {
            text-align: center;
            margin-top: 1rem;
        }

        .back-link a {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .default-credentials {
            background: #fef3c7;
            color: #92400e;
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Admin Panel</h1>
            <p>Email Subscription System</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="default-credentials">
            <strong>Default credentials:</strong><br>
            Username: admin<br>
            Password: admin123<br>
            <small>Please change these in production!</small>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-input"
                    required
                    autocomplete="username"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="submit-btn">Login</button>
        </form>

        <div class="back-link">
            <a href="../index.html">← Back to Newsletter</a>
        </div>
    </div>
</body>
</html>