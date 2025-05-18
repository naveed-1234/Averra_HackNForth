<?php
session_start();
if (!isset($_SESSION['parent_email']) || ($_SESSION['role'] ?? '') !== 'parent') {
    header("Location: ../backend/auth_parent.php");
    exit();
}

$parent_email = $_SESSION['parent_email'];

// Connect to DB
$conn = new mysqli("localhost", "root", "", "childsafe_browser");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get linked child email for this parent
$child_query = $conn->prepare("SELECT linked_child_email FROM users WHERE email = ?");
$child_query->bind_param("s", $parent_email);
$child_query->execute();
$child_result = $child_query->get_result();

if ($child_result->num_rows === 0) {
    die("No linked child found for this parent.");
}

$child_email = $child_result->fetch_assoc()['linked_child_email'];

// Fetch URL logs for that child using prepared statement
$logs_stmt = $conn->prepare("SELECT url, timestamp, status, reason FROM url_logs WHERE child_email = ? ORDER BY timestamp DESC");
$logs_stmt->bind_param("s", $child_email);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();

// Collect logs and prepare data for chart
$logs = [];
$domain_counts = [];

while ($row = $logs_result->fetch_assoc()) {
    $logs[] = $row;
    $host = parse_url($row['url'], PHP_URL_HOST);
    if (!$host) continue;

    if (!isset($domain_counts[$host])) {
        $domain_counts[$host] = 1;
    } else {
        $domain_counts[$host]++;
    }
}

$chart_data = [];
foreach ($domain_counts as $domain => $count) {
    $chart_data[] = ['domain' => $domain, 'count' => $count];
}

$logs_stmt->close();
$child_query->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Parent Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial; padding: 20px; background: #f2f2f2; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #ddd; }
        h2 { margin-top: 40px; }
        canvas { background: #fff; padding: 10px; }
    </style>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($_SESSION['parent_name']) ?></h1>
    <form action="/childsafe_browser/backend/logout.php" method="post" style="float: right; margin-top: -50px;">
        <input type="hidden" name="role" value="parent" />
        <button type="submit" style="background-color: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">Logout</button>
    </form>

    <h2>Full Browsing History for <?= htmlspecialchars($child_email) ?></h2>
    <table>
        <thead>
            <tr>
                <th>URL</th>
                <th>Timestamp</th>
                <th>Status</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($logs) === 0): ?>
            <tr><td colspan="4" style="text-align:center;">No browsing history found.</td></tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['url']) ?></td>
                    <td><?= htmlspecialchars($log['timestamp']) ?></td>
                    <td><?= htmlspecialchars($log['status']) ?></td>
                    <td><?= htmlspecialchars($log['reason']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Browsing Summary Chart</h2>
    <canvas id="domainChart" width="600" height="300"></canvas>

    <script>
        const ctx = document.getElementById('domainChart').getContext('2d');
        let chartData = {
            labels: <?= json_encode(array_column($chart_data, 'domain')) ?>,
            datasets: [{
                label: 'Visits per Domain',
                data: <?= json_encode(array_column($chart_data, 'count')) ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.6)'
            }]
        };
        let domainChart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });

        // Function to update logs table and chart dynamically
        async function updateLogs() {
            try {
                const response = await fetch('/childsafe_browser/backend/get_url_logs.php');
                const data = await response.json();
                if (data.error) {
                    console.error('Error fetching logs:', data.error);
                    return;
                }
                const logs = data.logs;

                // Update table body
                const tbody = document.querySelector('table tbody');
                tbody.innerHTML = '';
                if (logs.length === 0) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 4;
                    td.style.textAlign = 'center';
                    td.textContent = 'No browsing history found.';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                } else {
                    logs.forEach(log => {
                        const tr = document.createElement('tr');
                        ['url', 'timestamp', 'status', 'reason'].forEach(field => {
                            const td = document.createElement('td');
                            td.textContent = log[field] || '';
                            tr.appendChild(td);
                        });
                        tbody.appendChild(tr);
                    });
                }

                // Update chart data
                const domainCounts = {};
                logs.forEach(log => {
                    try {
                        const urlObj = new URL(log.url);
                        const domain = urlObj.hostname;
                        domainCounts[domain] = (domainCounts[domain] || 0) + 1;
                    } catch {
                        // ignore invalid URLs
                    }
                });

                chartData.labels = Object.keys(domainCounts);
                chartData.datasets[0].data = Object.values(domainCounts);
                domainChart.update();

            } catch (error) {
                console.error('Failed to update logs:', error);
            }
        }

        // Initial update and periodic refresh every 10 seconds
        updateLogs();
        setInterval(updateLogs, 10000);
    </script>
</body>
</html>
