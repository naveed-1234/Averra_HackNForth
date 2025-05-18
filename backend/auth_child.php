<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$message = "";
$activeTab = "login";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $activeTab = "register";

        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (!$name || !$email || !$password || !$confirm_password) {
            $message = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT email FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $message = "Email already registered.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'child')");
                    if ($stmt->execute([$name, $email, $hashed_password])) {
                        $message = "Registration successful! Please log in.";
                        $activeTab = "login";
                    } else {
                        $message = "Error during registration.";
                    }
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'login') {
        $activeTab = "login";

        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (!$email || !$password) {
            $message = "Please enter email and password.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT name, email, password, role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user && $user['role'] === 'child' && password_verify($password, $user['password'])) {
                    $_SESSION['child_logged_in'] = true;
                    $_SESSION['child_name'] = $user['name'];
                    $_SESSION['child_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
header("Location: /childsafe_browser/child/dashboard.php");
                    exit();
                } else {
                    $message = "Incorrect email or password.";
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Child Login/Register</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f9f9f9; }
        .container { max-width: 400px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px #ccc; }
        h2 { text-align: center; }
        .tabs { display: flex; margin-bottom: 20px; cursor: pointer; }
        .tab { flex: 1; padding: 10px; text-align: center; background: #eee; border-radius: 5px 5px 0 0; }
        .tab.active { background: #4285f4; color: white; font-weight: bold; }
        form { display: none; }
        form.active { display: block; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 8px; margin: 8px 0 16px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;
        }
        button {
            background-color: #4285f4; color: white; border: none; padding: 10px; width: 100%; border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #357ae8;
        }
        .message {
            background-color: #f8d7da; color: #842029; padding: 10px; margin-bottom: 15px; border-radius: 4px;
            border: 1px solid #f5c2c7;
        }
        .success {
            background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Child Login/Register</h2>

    <div class="tabs">
        <div class="tab <?= $activeTab === 'login' ? 'active' : '' ?>" id="login-tab">Login</div>
        <div class="tab <?= $activeTab === 'register' ? 'active' : '' ?>" id="register-tab">Register</div>
    </div>

    <?php if ($message): ?>
        <div class="message <?= strpos($message, 'successful') !== false ? 'success' : '' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="post" id="login-form" class="<?= $activeTab === 'login' ? 'active' : '' ?>">
        <input type="hidden" name="action" value="login">
        <label>Email:</label>
        <input type="email" name="email" required>
        <label>Password:</label>
        <input type="password" name="password" required>
        <button type="submit">Login</button>
    </form>

    <form method="post" id="register-form" class="<?= $activeTab === 'register' ? 'active' : '' ?>">
        <input type="hidden" name="action" value="register">
        <label>Name:</label>
        <input type="text" name="name" required>
        <label>Email:</label>
        <input type="email" name="email" required>
        <label>Password:</label>
        <input type="password" name="password" required>
        <label>Confirm Password:</label>
        <input type="password" name="confirm_password" required>
        <button type="submit">Register</button>
    </form>
</div>

<script>
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');

    loginTab.addEventListener('click', () => {
        loginTab.classList.add('active');
        registerTab.classList.remove('active');
        loginForm.classList.add('active');
        registerForm.classList.remove('active');
    });

    registerTab.addEventListener('click', () => {
        registerTab.classList.add('active');
        loginTab.classList.remove('active');
        registerForm.classList.add('active');
        loginForm.classList.remove('active');
    });
</script>

</body>
</html>
