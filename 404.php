<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - Error 404</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; color: #334155; text-align: center; padding: 10% 5%; margin: 0; }
        .error-container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h1 { font-size: 72px; color: #ef4444; margin: 0; }
        h2 { font-size: 24px; margin: 10px 0; color: #1e293b; }
        p { color: #64748b; font-size: 16px; margin-bottom: 30px; }
        .btn-home { display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: 500; transition: background 0.2s; }
        .btn-home:hover { background-color: #2563eb; }
    </style>
</head>
<body>

<div class="error-container">
    <h1>404</h1>
    <h2>Requested Page Not Found</h2>
    <p>The link you followed may be broken, or the page may have been removed permanently.</p>
    <a href="dashboard" class="btn-home">Back to Dashboard</a>
</div>

</body>
</html>