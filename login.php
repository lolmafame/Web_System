<?php
// Start session
session_start();

// Database configuration (adjust if needed)
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "event_management";

// Connect to DB
$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        // Fetch user by email
        $stmt = $conn->prepare("SELECT id, role, first_name, last_name, password_hash FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $role, $first_name, $last_name, $password_hash);
            $stmt->fetch();

            if (password_verify($password, $password_hash)) {
                // Successful login
                $_SESSION['user_id'] = $id;
                $_SESSION['role'] = $role;
                $_SESSION['username'] = trim($first_name . ' ' . $last_name);

                if ($role === 'organizer') {
                    header("Location: organizer_home.php");
                    exit();
                } else {
                    header("Location: attendee_home.php");
                    exit();
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Event Management System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            overflow: hidden;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .login-header h1 { font-size: 1.75rem; margin-bottom: 0.3rem; }
        .login-header p { opacity: 0.9; font-size: 0.9rem; }
        .login-body { padding: 1.5rem; overflow-y: auto; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label {
            display: block; margin-bottom: 0.4rem;
            color: #333; font-weight: 500; font-size: 0.95rem;
        }
        .form-group input {
            width: 100%; padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .remember-forgot {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 1.2rem; font-size: 0.85rem;
        }
        .remember-me { display: flex; align-items: center; gap: 0.4rem; }
        .forgot-password {
            color: #667eea; text-decoration: none; transition: opacity 0.3s;
        }
        .forgot-password:hover { opacity: 0.7; }
        .btn-login {
            width: 100%; padding: 0.875rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 8px;
            font-size: 0.95rem; font-weight: 600;
            cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .divider {
            text-align: center; margin: 1.2rem 0; position: relative;
        }
        .divider::before {
            content: ''; position: absolute; top: 50%;
            left: 0; right: 0; height: 1px; background: #e0e0e0;
        }
        .divider span {
            background: white; padding: 0 1rem;
            position: relative; color: #666; font-size: 0.85rem;
        }
        .signup-link {
            text-align: center; color: #666; font-size: 0.9rem;
        }
        .signup-link a {
            color: #667eea; text-decoration: none;
            font-weight: 600; transition: opacity 0.3s;
        }
        .signup-link a:hover { opacity: 0.7; }
        .back-home { text-align: center; margin-top: 1rem; }
        .back-home a {
            color: #667eea; text-decoration: none; font-size: 0.85rem;
            transition: opacity 0.3s;
        }
        .back-home a:hover { opacity: 0.7; }
        .alert {
            padding: 0.75rem; border-radius: 8px;
            margin-bottom: 1.2rem; font-size: 0.9rem;
        }
        .alert-error {
            background: #fee; color: #c33; border: 1px solid #fcc;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Login to access your account</p>
        </div>

        <div class="login-body">
            <?php if (isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>

            <form id="loginForm" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login">Login</button>
            </form>

            <div class="divider"><span>OR</span></div>

            <div class="signup-link">
                Don't have an account? <a href="register.php">Sign up now</a>
            </div>

            <div class="back-home">
                <a href="index.php">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
