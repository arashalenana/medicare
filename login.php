<?php
session_start();
require_once 'config/db.php';
require_once 'config/paths.php';

// Set timezone to EAT
date_default_timezone_set('Africa/Nairobi');

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'Admin') {
                header("Location: welcome.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - Family Medicare POS</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $asset_path; ?>images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $asset_path; ?>images/favicon-16x16.png">
    <link rel="shortcut icon" href="<?php echo $asset_path; ?>images/favicon.png">

    <!-- Bootstrap CSS -->
    <link href="<?php echo $asset_path; ?>vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo $asset_path; ?>vendor/fontawesome/css/all.min.css">

    <!-- PWA: Manifest, meta tags, and service worker registration -->
    <?php include dirname(__FILE__) . '/includes/pwa.php'; ?>

    <style>
        :root {
            --brand-blue: #0d6efd;
            --brand-blue-dark: #0b5ed7;
            --muted-gray: #6c757d;
        }
        body, html {
            height: 100%;
            margin: 0;
            background-color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        .login-box {
            width: 100%;
            max-width: 420px;
            background: var(--brand-blue);
            padding: 36px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(13, 110, 253, 0.15);
            border: 1px solid rgba(11,93,215,0.12);
            color: #fff;
        }
        .login-logo {
            max-width: 120px;
            margin-bottom: 10px;
        }
        .btn-login {
            background-color: #ffffff;
            border: none;
            color: var(--brand-blue);
            padding: 12px;
            font-weight: 700;
            transition: 0.2s;
        }
        .btn-login:hover {
            background-color: #e9f2ff;
            color: var(--brand-blue-dark);
        }
        .form-control {
            background-color: #ffffff;
            border: 1px solid rgba(0,0,0,0.08);
            color: #111827;
        }
        .form-control:focus {
            background-color: #ffffff;
            border-color: rgba(13,110,253,0.4);
            color: #111827;
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.12);
        }
        .input-group-text {
            background-color: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.08);
            color: #fff;
        }
        .form-label {
            color: rgba(255,255,255,0.9);
        }
        .text-muted {
            color: rgba(255,255,255,0.85) !important;
        }
        .login-box .text-muted small { color: rgba(255,255,255,0.85); }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="text-center">
                <img src="<?php echo $asset_path; ?>images/favicon.png" alt="Family Medicare Logo" class="login-logo">
                <h4 style="color: var(--accent-red); font-weight: 800; letter-spacing: 1px;">FAMILY MEDICARE</h4>
                <p class="text-muted mb-4">Clinic & Laboratory POS</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small text-center"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required autocomplete="username">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" class="btn btn-login w-100 rounded-3">SIGN IN</button>
            </form>

            <div class="mt-4 text-center border-top border-secondary pt-3">
                <p class="text-muted small mb-0">Contact your system administrator for login credentials.</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="<?php echo $asset_path; ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>