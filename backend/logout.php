<?php
session_start();

$role = $_POST['role'] ?? $_SESSION['role'] ?? null;

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect based on role if known, else generic redirect
if ($role === 'child') {
    header("Location: ../backend/auth_child.php");
    exit;
} elseif ($role === 'parent') {
    header("Location: ../backend/auth_parent.php");
    exit;
}
// If role is unknown, just redirect to a default login page (say child login)
header("Location: ../backend/auth_child.php");
exit;
?>
