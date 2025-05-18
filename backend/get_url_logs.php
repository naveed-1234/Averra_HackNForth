<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_email']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo json_encode(['error' => 'Not authenticated or unauthorized']);
    exit();
}

$parent_email = $_SESSION['parent_email'];

$conn = new mysqli("localhost", "root", "", "childsafe_browser");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

$child_query = $conn->prepare("SELECT linked_child_email FROM users WHERE email = ?");
$child_query->bind_param("s", $parent_email);
$child_query->execute();
$child_result = $child_query->get_result();

if ($child_result->num_rows === 0) {
    echo json_encode(['error' => 'No linked child found']);
    $conn->close();
    exit();
}

$child_email = $child_result->fetch_assoc()['linked_child_email'];

$logs_stmt = $conn->prepare("SELECT url, timestamp, status, reason FROM url_logs WHERE child_email = ? ORDER BY timestamp DESC");
$logs_stmt->bind_param("s", $child_email);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();

$logs = [];
while ($row = $logs_result->fetch_assoc()) {
    $logs[] = $row;
}

$logs_stmt->close();
$child_query->close();
$conn->close();

echo json_encode(['logs' => $logs]);
exit();
