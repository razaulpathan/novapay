<?php
// upimenger/login.php - Secure Login Portal for UPI Manager
require_once '../db.php';

// Redirect if already logged in as admin
if (isAdmin()) {
    header("Location: addupi.php");
    exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($phone) || empty($password)) {
        $error_msg = "Please enter both phone number and password.";
    } else {
        // Authenticate using config file variables if defined, bypassing DB lookup
        if (defined('ADMIN_PHONE') && defined('ADMIN_PASSWORD')) {
            if ($phone === ADMIN_PHONE && $password === ADMIN_PASSWORD) {
                // Login success
                $_SESSION['user_id'] = 0;
                $_SESSION['phone'] = ADMIN_PHONE;
                $_SESSION['role'] = 'admin';

                header("Location: addupi.php");
                exit;
            } else {
                $error_msg = "Invalid phone number or password.";
            }
        } else {
            // Fallback to Database authentication
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
                $stmt->execute([$phone]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['role'] === 'admin') {
                        // Login success for admin
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['phone'] = $user['phone'];
                        $_SESSION['role'] = $user['role'];

                        header("Location: addupi.php");
                        exit;
                    } else {
                        $error_msg = "Access Denied: You do not have administrative privileges.";
                    }
                } else {
                    $error_msg = "Invalid phone number or password.";
                }
            } catch (PDOException $e) {
                $error_msg = "Database error occurred. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UPI Manager - Secure Sign In</title>
    <!-- Import Outfit Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: #111827;
            --border-color: #1f2937;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --accent-blue: #2395ee;
            --accent-blue-hover: #1980d4;
            --error-red: #ef4444;
            --input-bg: #1f2937;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            width: 100%;
            max-width: 420px;
            border-radius: 20px;
            padding: 40px 32px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .logo-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .logo-header svg {
            width: 32px;
            height: 32px;
            color: var(--accent-blue);
        }

        .brand-title {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 0.5px;
            color: var(--text-primary);
        }

        .panel-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 32px;
        }

        .alert {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error-red);
            font-size: 14px;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 24px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper svg {
            position: absolute;
            left: 14px;
            width: 20px;
            height: 20px;
            color: var(--text-secondary);
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            height: 48px;
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0 16px 0 46px;
            font-size: 15px;
            color: var(--text-primary);
            font-family: inherit;
            outline: none;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 8px rgba(35, 149, 238, 0.2);
        }

        .submit-btn {
            width: 100%;
            height: 48px;
            background-color: var(--accent-blue);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
            font-family: inherit;
        }

        .submit-btn:hover {
            background-color: var(--accent-blue-hover);
        }

        .back-to-site {
            display: inline-block;
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-to-site:hover {
            color: var(--accent-blue);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <header class="logo-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
            <span class="brand-title">UPI Manager</span>
        </header>
        <p class="panel-subtitle">Sign in to manage rotating UPI IDs</p>

        <?php if (!empty($error_msg)): ?>
            <div class="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= sanitize($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <input type="tel" name="phone" class="form-control" placeholder="Enter phone" required autofocus value="<?= isset($_POST['phone']) ? sanitize($_POST['phone']) : '' ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>

            <button type="submit" class="submit-btn">Sign In</button>
        </form>

        <a href="../home.php" class="back-to-site">&larr; Return to main application</a>
    </div>
</body>
</html>
