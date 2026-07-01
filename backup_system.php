<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php'; // Aapka existing PDO connection object ($pdo)

$msg = "";
$msg_type = "";
$debug_db_info = "";

try {
    $current_db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $debug_db_info = "Connected to Database: <strong style='color:#0d6efd;'>" . htmlspecialchars($current_db_name) . "</strong>";
} catch (Exception $e) {
    $debug_db_info = "Connection Error: " . $e->getMessage();
}

// ==========================================
// 1. ONE-CLICK EXPORT LOGIC
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $backupData = [];
        foreach ($all_tables as $table) {
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $backupData[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $fileName = "Memon_Biryani_Backup_" . date('Y-m-d_H-i-s') . ".json";
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $jsonContent;
        exit();
    } catch (PDOException $e) {
        die("Export Operation Failed: " . $e->getMessage());
    }
}

// ==========================================
// 2. ONE-CLICK IMPORT LOGIC
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['backup_file'])) {
    if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['backup_file']['tmp_name'];
        $fileContent = file_get_contents($fileTmpPath);
        $data = json_decode($fileContent, true);
        
        if ($data === null) {
            $msg = "Invalid Backup File Format.";
            $msg_type = "danger";
        } else {
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                foreach ($data as $table => $rows) {
                    $pdo->exec("TRUNCATE TABLE `$table`;");
                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
                        $colString = implode('`, `', $columns);
                        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                        $sql = "INSERT INTO `$table` (`$colString`) VALUES ($placeholders)";
                        $stmt = $pdo->prepare($sql);
                        foreach ($rows as $row) {
                            $stmt->execute(array_values($row));
                        }
                    }
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                $msg = "Database restored successfully!";
                $msg_type = "success";
            } catch (PDOException $e) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                $msg = "Database import failure: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

// ==========================================
// 3. ULTRA FORCE-WIPE SYSTEM (DROP & REBUILD)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_wipe'])) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Database ke saare tables ko jad se khatam karein
        $stmt = $pdo->query("SHOW TABLES");
        $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($all_tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`;");
        }
        
        // Ab exact fresh schema zero records ke sath wapas create karein
        $schemaQueries = "
        CREATE TABLE `categories` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `expenses` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `title` varchar(255) NOT NULL,
          `amount` decimal(10,2) NOT NULL,
          `date` date NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `notifications` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `message` text NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `password_resets` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(255) NOT NULL,
          `token` varchar(255) NOT NULL,
          `expires_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `pos_categories` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `pos_products` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `category_id` int(11) NOT NULL,
          `name` varchar(255) NOT NULL,
          `price` decimal(10,2) NOT NULL,
          `image` varchar(255) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `sell_items` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `sell_record_id` int(11) NOT NULL,
          `product_name` varchar(255) NOT NULL,
          `price` decimal(10,2) NOT NULL,
          `quantity` int(11) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `sell_records` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `customer_name` varchar(255) DEFAULT NULL,
          `total_amount` decimal(10,2) NOT NULL,
          `discount` decimal(10,2) DEFAULT 0.00,
          `payable_amount` decimal(10,2) NOT NULL,
          `received_amount` decimal(10,2) NOT NULL,
          `change_amount` decimal(10,2) NOT NULL,
          `payment_method` varchar(50) NOT NULL,
          `date` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `email` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `failed_attempts` int(11) DEFAULT 0,
          `lockout_time` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `user_notifications` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `notification_id` int(11) NOT NULL,
          `is_read` tinyint(1) DEFAULT 0,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        $pdo->exec($schemaQueries);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        
        $msg = "SUCCESS: Database has been 100% force-wiped. All counters, KPIs, and amounts are now set to zero!";
        $msg_type = "success";
        
    } catch (PDOException $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $msg = "Critical Wipe Error: " . $e->getMessage();
        $msg_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Maintenance Portal - Memon Biryani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .portal-card { max-width: 600px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .brand-header { color: #7c2d12; font-weight: bold; text-align: center; margin-bottom: 20px; }
        .btn-export { background-color: #0d6efd; color: white; font-weight: 600; }
        .btn-import { background-color: #198754; color: white; font-weight: 600; }
        .btn-wipe { background-color: #dc3545; color: white; font-weight: 600; }
        .debug-box { background-color: #eec; padding: 10px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; border-left: 5px solid #0d6efd; }
    </style>
</head>
<body>

<div class="container">
    <div class="portal-card">
        <h3 class="brand-header">Memon Biryani Software Backup Utility</h3>
        
        <div class="debug-box text-center shadow-sm">
            Status: <?php echo $debug_db_info; ?>
        </div>
        
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                <strong>System Response:</strong> <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4 border-0 bg-light">
            <div class="card-body text-center p-4">
                <h5 class="card-title mb-2">Export Live Registry Log Data</h5>
                <p class="text-muted small">One-click data archiving mapping across all schema structures.</p>
                <a href="backup_system.php?action=export" class="btn btn-export w-100 p-2">Generate & Download System Backup</a>
            </div>
        </div>

        <div class="card mb-4 border-0 bg-light">
            <div class="card-body p-4">
                <h5 class="card-title text-center mb-2">Import System Backup Data</h5>
                <p class="text-muted small text-center">Warning: Executing this pipeline clears current tables before rewriting records.</p>
                
                <form action="backup_system.php" method="POST" enctype="multipart/form-data" class="mt-3">
                    <div class="mb-3">
                        <input class="form-control" type="file" id="backup_file" name="backup_file" accept=".json" required>
                    </div>
                    <button type="submit" class="btn btn-import w-100 p-2" onclick="return confirm('Are you sure you want to completely clear current tables and restore this backup?');">Upload & Synchronize Database</button>
                </form>
            </div>
        </div>

        <div class="card border-0" style="background-color: #fff5f5; border: 1px solid #fed7d7 !important;">
            <div class="card-body p-4">
                <h5 class="card-title text-center mb-2" style="color: #9b2c2c;">Danger Zone: Wipe Database</h5>
                <p class="text-muted small text-center">This utility clears ALL tables and sets metric KPI/amounts counter entries to zero.</p>
                
                <form action="backup_system.php" method="POST" class="mt-3">
                    <input type="hidden" name="action_wipe" value="1">
                    <button type="submit" class="btn btn-wipe w-100 p-2" onclick="return confirm('Are you sure you delete all data in your database?');">Wipe Database Records</button>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="dashboard" class="text-muted small text-decoration-none">← Return to Main Management Console</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>