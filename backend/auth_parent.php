<?php
session_start();

$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "childsafe_browser";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$activeTab = "login"; // default active tab

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        // Parent registration logic
        $activeTab = "register";

        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $linked_child_email = trim($_POST['linked_child_email']);

        if (!$name || !$email || !$password || !$confirm_password || !$linked_child_email) {
            $message = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($linked_child_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match.";
        } else {
            // Check if child email exists
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
            $stmt->bind_param("s", $linked_child_email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $message = "Linked child email does not exist.";
            } else {
                // Check if parent email already registered
                $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $message = "Email already registered.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, linked_child_email, role) VALUES (?, ?, ?, ?, 'parent')");
                    $stmt->bind_param("ssss", $name, $email, $hashed_password, $linked_child_email);
                    if ($stmt->execute()) {
                        $message = "Registration successful! Please log in.";
                        $activeTab = "login";
                    } else {
                        $message = "Error during registration: " . $conn->error;
                    }
                }
            }
            $stmt->close();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'login') {
        // Parent login logic
        $activeTab = "login";

        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (!$email || !$password) {
            $message = "Please enter email and password.";
        } else {
            $stmt = $conn->prepare("SELECT  name, email, password, linked_child_email, role FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if ($user['role'] === 'parent' && password_verify($password, $user['password'])) {
                    $_SESSION['parent_logged_in'] = true;
                    $_SESSION['parent_name'] = $user['name'];
                    $_SESSION['parent_email'] = $user['email'];
                    $_SESSION['linked_child_email'] = $user['linked_child_email'];
                    $_SESSION['role'] = $user['role'];

                    header("Location: ../parent/dashboard.php"); // Updated redirect path
                    exit();
                } else {
                    $message = "Incorrect password.";
                }
            } else {
                $message = "No account found with that email.";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Parent Login / Register</title>
<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 400px;
        margin: 40px auto;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 0 10px #ccc;
    }
    h1 {
        text-align: center;
    }
    .tab-buttons {
        display: flex;
        margin-bottom: 20px;
        justify-content: center;
    }
    .tab-buttons button {
        flex: 1;
        padding: 10px;
        cursor: pointer;
        background: #f2f2f2;
        border: none;
        border-bottom: 3px solid transparent;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }
    .tab-buttons button.active {
        border-bottom: 3px solid #4285F4;
        background: white;
        font-weight: bold;
    }
    form {
        display: none;
    }
    form.active {
        display: block;
    }
    label {
        display: block;
        margin-bottom: 6px;
        margin-top: 12px;
    }
    input[type="text"], input[type="email"], input[type="password"] {
        width: 100%;
        padding: 8px;
        box-sizing: border-box;
        border: 1px solid #aaa;
        border-radius: 4px;
    }
    input[type="submit"] {
        margin-top: 20px;
        background-color: #4285F4;
        color: white;
        border: none;
        padding: 10px;
        width: 100%;
        font-size: 16px;
        border-radius: 4px;
        cursor: pointer;
    }
    input[type="submit"]:hover {
        background-color: #357AE8;
    }
    .message {
        margin-top: 10px;
        color: red;
        text-align: center;
    }
    .message.success {
        color: green;
    }
</style>
<script>
function switchTab(tabName) {
    document.getElementById('loginForm').classList.remove('active');
    document.getElementById('registerForm').classList.remove('active');
    document.getElementById('loginBtn').classList.remove('active');
    document.getElementById('registerBtn').classList.remove('active');

    if (tabName === 'login') {
        document.getElementById('loginForm').classList.add('active');
        document.getElementById('loginBtn').classList.add('active');
    } else {
        document.getElementById('registerForm').classList.add('active');
        document.getElementById('registerBtn').classList.add('active');
    }
}

window.onload = function() {
    // Set active tab based on PHP variable
    const activeTab = '<?= $activeTab ?>';
    switchTab(activeTab);
};
</script>
</head>
<body>

<h1>Parent Account</h1>

<div class="tab-buttons">
    <button type="button" id="loginBtn" onclick="switchTab('login')">Login</button>
    <button type="button" id="registerBtn" onclick="switchTab('register')">Register</button>
</div>

<?php if ($message): ?>
    <p class="message <?= strpos($message, 'successful') !== false ? 'success' : '' ?>">
        <?= htmlspecialchars($message) ?>
    </p>
<?php endif; ?>

<!-- Login Form -->
<form id="loginForm" method="POST" action="" class="">
    <input type="hidden" name="action" value="login" />
    <label for="login_email">Email:</label>
    <input type="email" id="login_email" name="email" required />

    <label for="login_password">Password:</label>
    <input type="password" id="login_password" name="password" required />

    <input type="submit" value="Login" />
</form>

<!-- Register Form -->
<form id="registerForm" method="POST" action="">
    <input type="hidden" name="action" value="register" />
    <label for="reg_name">Name:</label>
    <input type="text" id="reg_name" name="name" required />

    <label for="reg_email">Email:</label>
    <input type="email" id="reg_email" name="email" required />

    <label for="linked_child_email">Linked Child Email:</label>
    <input type="email" id="linked_child_email" name="linked_child_email" required />

    <label for="reg_password">Password:</label>
    <input type="password" id="reg_password" name="password" required />

    <label for="reg_confirm_password">Confirm Password:</label>
    <input type="password" id="reg_confirm_password" name="confirm_password" required />

    <input type="submit" value="Register" />
</form>

</body>
</html>
