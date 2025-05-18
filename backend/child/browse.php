<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['child_email']) || ($_SESSION['role'] ?? '') !== 'child') {
    echo json_encode(['allowed' => false, 'error' => 'Not authenticated or unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['url'])) {
    echo json_encode(['allowed' => false, 'error' => 'Invalid request']);
    exit();
}

$url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
$child_email = $_SESSION['child_email'];

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['allowed' => false, 'error' => 'Invalid URL']);
    exit();
}

$conn = new mysqli("localhost", "root", "", "childsafe_browser");
if ($conn->connect_error) {
    echo json_encode(['allowed' => false, 'error' => 'DB connection failed']);
    exit();
}

$blocked = false;
$reason = '';
$domain = parse_url($url, PHP_URL_HOST);

// Use correct column from blacklist table
$stmt = $conn->prepare("SELECT keyword_or_domain FROM blacklist");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $blacklist_entry = $row['keyword_or_domain'];
    if (stripos($url, $blacklist_entry) !== false) {
        $blocked = true;
        $reason = $blacklist_entry;
        break;
    }
}

$timestamp = date('Y-m-d H:i:s');
$status = $blocked ? 'blocked' : 'allowed';

$log_stmt = $conn->prepare("INSERT INTO url_logs (child_email, url, timestamp, status, reason) VALUES (?, ?, ?, ?, ?)");
$log_stmt->bind_param("sssss", $child_email, $url, $timestamp, $status, $reason);
$log_stmt->execute();
$log_stmt->close();

// Send alert if blocked
if ($blocked) {
    $parent_stmt = $conn->prepare("SELECT email FROM users WHERE linked_child_email = ? LIMIT 1");
    $parent_stmt->bind_param("s", $child_email);
    $parent_stmt->execute();
    $parent_result = $parent_stmt->get_result();

    if ($parent = $parent_result->fetch_assoc()) {
        $parent_email = $parent['email'];
        $subject = "Alert: Blocked Website Attempt";
        $message = "Child ($child_email) attempted to access blocked URL: $url";
        $headers = "From: alert@childsafe.local\r\n";
        @mail($parent_email, $subject, $message, $headers);
    }
    $parent_stmt->close();
}

$stmt->close();
$conn->close();

echo json_encode(['allowed' => !$blocked]);
