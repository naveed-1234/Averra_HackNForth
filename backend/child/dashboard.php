<?php
session_start();
if (!isset($_SESSION['child_email'])) {
    header("Location: /childsafe_browsing/backend/auth_child.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Child Dashboard - Safe Browser</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 30px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        iframe {
            width: 100%;
            height: 400px;
            border: 1px solid #ccc;
            margin-top: 20px;
        }
        input[type=url], button {
            padding: 10px;
            width: 80%;
            margin-top: 10px;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            width: 18%;
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        #status {
            margin-top: 10px;
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['child_email']); ?>!</h2>
        <form id="browseForm" style="display: inline-block; vertical-align: middle;">
            <input type="url" name="url" id="url" placeholder="Enter a website URL" required style="height: 36px;"/>
            <button type="submit" style="height: 36px; background-color: #28a745; color: white; border: none; padding: 0 16px; border-radius: 4px; cursor: pointer;">Go</button>
        </form>
        <form action="/childsafe_browser/backend/logout.php" method="post" style="display: inline-block; vertical-align: middle; margin-left: 10px;">
            <input type="hidden" name="role" value="child" />
            <button type="submit" style="height: 36px; background-color: #007bff; color: black; border: none; padding: 0 16px; border-radius: 4px; cursor: pointer; width: auto; min-width: 80px;">Logout</button>
        </form>
        <p id="status"></p>
        <iframe id="webview" src=""></iframe>
    </div>

    <script>
        document.getElementById('browseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const url = document.getElementById('url').value;
            const status = document.getElementById('status');

fetch('/childsafe_browser/child/browse.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'url=' + encodeURIComponent(url)
            })
            .then(res => res.json())
            .then(data => {
                if (data.allowed) {
                    document.getElementById('webview').src = url;
                    status.textContent = "";
                } else {
                    document.getElementById('webview').src = "";
                    status.textContent = "Access is denied: " + url;
                }
            })
            .catch(() => {
                status.textContent = "Access is denied: " + url;
                document.getElementById('webview').src = "";
            });
        });
    </script>
</body>
</html>
