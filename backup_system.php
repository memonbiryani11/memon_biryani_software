<?php
// 1. GLOBAL PRODUCTION ERROR HANDLER: Screen par raw warnings block hongi par backend log ho jayengi
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) { 
    @session_start(); 
}

require_once 'auth_functions.php';
require_once 'db.php';
checkSession();

$msg = "";
$msg_type = "";
$debug_db_info = "";

try {
    $current_db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $debug_db_info = htmlspecialchars($current_db_name);
} catch (Exception $e) {
    error_log("DB Connection Info Fault: " . $e->getMessage());
    $debug_db_info = "Connection Error";
}

// 1. EXPORT HANDLER WITH STREAM PROTECTION (Bypasses 500 Server Interruption)
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    // Kisi bhi pehle se chal rahe output buffer ko clear karna taake download interrupt na ho
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $backupData = [];
        
        foreach ($all_tables as $table) {
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $backupData[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($jsonContent === false) {
            throw new Exception("JSON Encoding Failed: " . json_last_error_msg());
        }
        
        $fileName = "Memon_Biryani_Backup_" . date('Y-m-d_H-i-s') . ".json";
        
        // Strict Headers for Safe Binary Streaming
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($jsonContent));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $jsonContent;
        exit();
    } catch (Exception $e) {
        error_log("Database JSON Export Crash: " . $e->getMessage());
        // Agar headers send nahi hue to alert show karenge
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            echo "<script>alert('Export Failed: Server Internal Stream Error.'); window.location.href='backup_system.php';</script>";
            exit();
        }
    }
}

// 2. IMPORT / RESTORE HANDLER WITH TRANSACTION SAFETY
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['backup_file'])) {
    if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        try {
            $fileContent = file_get_contents($_FILES['backup_file']['tmp_name']);
            if ($fileContent === false) {
                throw new Exception("Unable to read uploaded file.");
            }
            
            $data = json_decode($fileContent, true);
            if ($data === null) {
                $msg = "Invalid backup format. Please upload a valid JSON backup snapshot.";
                $msg_type = "err";
            } else {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                
                foreach ($data as $table => $rows) {
                    $pdo->exec("TRUNCATE TABLE `$table`;");
                    if (!empty($rows) && is_array($rows)) {
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
                $msg = "Database restored successfully from backup data snapshot!";
                $msg_type = "ok";
            }
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            error_log("Database JSON Import Fault: " . $e->getMessage());
            $msg = "Import failed: Server internal parsing issue.";
            $msg_type = "err";
        }
    } else {
        $msg = "File upload error code: " . $_FILES['backup_file']['error'];
        $msg_type = "err";
    }
}

// 3. WIPE DATA INTEGRITY PIPELINE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_wipe'])) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $stmt = $pdo->query("SHOW TABLES");
        $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($all_tables as $table) { 
            $pdo->exec("DROP TABLE IF EXISTS `$table`;"); 
        }
        
        $schemaQueries = "
        CREATE TABLE `categories` (`id` int(11) NOT NULL AUTO_INCREMENT,`name` varchar(255) NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `expenses` (`id` int(11) NOT NULL AUTO_INCREMENT,`title` varchar(255) NOT NULL,`amount` decimal(10,2) NOT NULL,`date` date NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `notifications` (`id` int(11) NOT NULL AUTO_INCREMENT,`message` text NOT NULL,`created_at` timestamp NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `password_resets` (`id` int(11) NOT NULL AUTO_INCREMENT,`email` varchar(255) NOT NULL,`token` varchar(255) NOT NULL,`expires_at` datetime NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `pos_categories` (`id` int(11) NOT NULL AUTO_INCREMENT,`name` varchar(255) NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `pos_products` (`id` int(11) NOT NULL AUTO_INCREMENT,`category_id` int(11) NOT NULL,`name` varchar(255) NOT NULL,`price` decimal(10,2) NOT NULL,`image` varchar(255) DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `sell_items` (`id` int(11) NOT NULL AUTO_INCREMENT,`sell_record_id` int(11) NOT NULL,`product_name` varchar(255) NOT NULL,`price` decimal(10,2) NOT NULL,`quantity` int(11) NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `sell_records` (`id` int(11) NOT NULL AUTO_INCREMENT,`customer_name` varchar(255) DEFAULT NULL,`total_amount` decimal(10,2) NOT NULL,`discount` decimal(10,2) DEFAULT 0.00,`payable_amount` decimal(10,2) NOT NULL,`received_amount` decimal(10,2) NOT NULL,`change_amount` decimal(10,2) NOT NULL,`payment_method` varchar(50) NOT NULL,`date` timestamp NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `users` (`id` int(11) NOT NULL AUTO_INCREMENT,`name` varchar(255) NOT NULL,`email` varchar(255) NOT NULL,`password` varchar(255) NOT NULL,`failed_attempts` int(11) DEFAULT 0,`lockout_time` datetime DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        CREATE TABLE `user_notifications` (`id` int(11) NOT NULL AUTO_INCREMENT,`user_id` int(11) NOT NULL,`notification_id` int(11) NOT NULL,`is_read` tinyint(1) DEFAULT 0,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        $pdo->exec($schemaQueries);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $msg = "Database has been completely wiped and rebuilt. All data reset to zero!";
        $msg_type = "ok";
    } catch (Exception $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        error_log("Critical Wipe Error: " . $e->getMessage());
        $msg = "Critical wipe error occurred on server infrastructure.";
        $msg_type = "err";
    }
}

// Shared Meta Logic
$user_name     = htmlspecialchars($_SESSION['user_name'] ?? 'Muhammad Hamza');
$user_initials = strtoupper(substr($user_name, 0, 1));
$notif_count   = 0; 
$notifications = [];

if (function_exists('getActiveNotificationsForUser')) {
    $notifications = getActiveNotificationsForUser($_SESSION['user_id']);
    $notif_count   = count($notifications);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Backup &amp; Restore – Memon Biryani</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<style>
:root,[data-theme="light"]{
  --brand:#78450C;--brand-d:#5c3308;--brand-g:rgba(120,69,12,.18);
  --bg:#f2ebe1;--bg2:#e8ddd0;
  --surface:rgba(255,255,255,.78);--surface-s:rgba(255,255,255,.96);
  --border:rgba(120,69,12,.13);--border-s:rgba(120,69,12,.22);
  --text:#18100a;--text-m:#8a7060;
  --danger:#d94040;--danger-bg:rgba(217,64,64,.08);
  --success:#1a7a3f;--success-bg:rgba(26,122,63,.08);
  --blur:20px;--radius:12px;
}
[data-theme="dark"]{
  --brand:#c47a2a;--brand-d:#a05e18;--brand-g:rgba(196,122,42,.22);
  --bg:#0f0b08;--bg2:#1a1208;
  --surface:rgba(30,20,10,.86);--surface-s:rgba(40,28,14,.97);
  --border:rgba(196,122,42,.18);--border-s:rgba(196,122,42,.30);
  --text:#f0e6d8;--text-m:#9a8a78;
  --danger:#e07070;--danger-bg:rgba(224,112,112,.10);
  --success:#4caf7d;--success-bg:rgba(76,175,125,.10);
  --blur:18px;--radius:12px;
}
[data-theme="custom"]{
  --brand:#1a6b5c;--brand-d:#145248;--brand-g:rgba(26,107,92,.20);
  --bg:#eef6f4;--bg2:#daeee9;
  --surface:rgba(255,255,255,.76);--surface-s:rgba(255,255,255,.97);
  --border:rgba(26,107,92,.15);--border-s:rgba(26,107,92,.25);
  --text:#0d2e28;--text-m:#4a7a70;
  --danger:#c0392b;--danger-bg:rgba(192,57,43,.08);
  --success:#1a6b5c;--success-bg:rgba(26,107,92,.10);
  --blur:20px;--radius:12px;
}

*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;transition:background .35s,color .35s;}
#trail{position:fixed;inset:0;pointer-events:none;z-index:0;}
#overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(3px);z-index:990;}
#overlay.on{display:block;}

/* ══ SIDEBAR ══ */
#sb{position:fixed;top:0;left:0;width:260px;height:100vh;background:var(--surface);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));border-right:1px solid var(--border);box-shadow:4px 0 32px rgba(0,0,0,.10);z-index:1000;display:flex;flex-direction:column;transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1);}
#sb.on{transform:translateX(0);}
.sb-head{display:flex;align-items:center;gap:10px;padding:16px 14px;border-bottom:1px solid var(--border);flex-shrink:0;}
.sb-ico{width:38px;height:38px;border-radius:10px;background:var(--brand);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px var(--brand-g);flex-shrink:0;}
.sb-ico svg{width:20px;height:20px;fill:#fff;}
.sb-title h3{font-size:13.5px;font-weight:700;color:var(--brand);line-height:1.2;}
.sb-title span{font-size:10px;color:var(--text-m);text-transform:uppercase;letter-spacing:.7px;}
.sb-x{margin-left:auto;width:28px;height:28px;border-radius:7px;background:none;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-m);transition:background .15s,color .15s;}
.sb-x:hover{background:var(--brand-g);color:var(--brand);}
.sb-x svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}
.sb-nav{flex:1;overflow-y:auto;padding:8px 6px;}
.sb-nav::-webkit-scrollbar{width:3px;}
.sb-nav::-webkit-scrollbar-thumb{background:var(--brand-g);border-radius:3px;}
.nl{font-size:10px;font-weight:700;color:var(--text-m);text-transform:uppercase;letter-spacing:.9px;padding:10px 10px 3px;}
.ni{display:flex;align-items:center;gap:9px;padding:8px 11px;border-radius:8px;text-decoration:none;color:var(--text);font-size:13px;font-weight:500;transition:background .14s,color .14s;margin-bottom:1px;}
.ni:hover{background:var(--brand-g);color:var(--brand);}
.ni.act{background:var(--brand);color:#fff;box-shadow:0 2px 10px var(--brand-g);}
.ni.act svg{stroke:#fff!important;}
.ni svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.ni.dng{color:var(--danger);}
.ni.dng svg{stroke:var(--danger)!important;}
.ni.dng:hover{background:var(--danger-bg);}
.nt{display:flex;align-items:center;gap:9px;padding:8px 11px;border-radius:8px;color:var(--text);font-size:13px;font-weight:500;cursor:pointer;user-select:none;margin-bottom:1px;transition:background .14s,color .14s;}
.nt:hover{background:var(--brand-g);color:var(--brand);}
.nt svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.nt .cv{margin-left:auto;width:13px;height:13px;transition:transform .22s;}
.nt.open .cv{transform:rotate(180deg);}
.nsub{display:none;padding-left:24px;}
.nsub.on{display:block;}
.nsub .ni{font-size:12.5px;font-weight:400;padding:7px 11px;}
.sb-foot{padding:10px 6px;border-top:1px solid var(--border);flex-shrink:0;}
.sb-usr{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:8px;background:var(--brand-g);}
.av{border-radius:50%;background:var(--brand);color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px var(--brand-g);}
.sb-usr .av{width:32px;height:32px;font-size:12px;}
.sb-usr-info p{font-size:12.5px;font-weight:600;color:var(--text);line-height:1.2;}
.sb-usr-info span{font-size:11px;color:var(--text-m);}

/* ══ NAVBAR ══ */
#nb{position:fixed;top:0;left:0;right:0;height:60px;background:var(--surface);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));border-bottom:1px solid var(--border);box-shadow:0 2px 16px rgba(0,0,0,.07);display:flex;align-items:center;padding:0 18px;gap:10px;z-index:900;transition:background .35s;}
.nb-menu{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,border-color .15s;}
.nb-menu:hover{background:var(--brand-g);border-color:var(--brand);}
.nb-menu svg{width:17px;height:17px;stroke:var(--text);fill:none;stroke-width:2;stroke-linecap:round;}
.nb-logo{display:flex;align-items:center;gap:9px;text-decoration:none;}
.nb-logo-ic{width:30px;height:30px;border-radius:7px;background:var(--brand);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px var(--brand-g);}
.nb-logo-ic svg{width:15px;height:15px;fill:#fff;}
.nb-logo-txt{font-size:14.5px;font-weight:700;color:var(--brand);}
.nb-sp{flex:1;}
.nb-dt{font-size:11.5px;color:var(--text-m);display:none;}
@media(min-width:640px){.nb-dt{display:block;}}
.theme-sw{display:flex;align-items:center;gap:3px;background:var(--bg2);border-radius:8px;padding:3px;border:1px solid var(--border);}
.th-btn{width:30px;height:28px;border-radius:6px;border:none;background:none;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .15s;color:var(--text-m);}
.th-btn.act{background:var(--brand);color:#fff;}
.th-btn:hover:not(.act){background:var(--brand-g);}
.nb-bell{position:relative;width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s,border-color .15s;}
.nb-bell:hover{background:var(--brand-g);border-color:var(--brand);}
.nb-bell svg{width:17px;height:17px;stroke:var(--text);fill:none;stroke-width:1.8;stroke-linecap:round;}
.nb-bdg{position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;font-size:10px;font-weight:700;min-width:17px;height:17px;border-radius:9px;padding:0 3px;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);}
.nb-usr{display:flex;align-items:center;gap:7px;cursor:pointer;position:relative;}
.nb-usr .av{width:32px;height:32px;font-size:12px;}
.nb-un{font-size:12.5px;font-weight:600;color:var(--text);display:none;}
@media(min-width:520px){.nb-un{display:block;}}
.udd{display:none;position:absolute;top:calc(100% + 10px);right:0;background:var(--surface-s);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--border-s);border-radius:var(--radius);box-shadow:0 8px 28px rgba(0,0,0,.14);min-width:175px;z-index:9999;overflow:hidden;}
.udd.on{display:block;}
.udd-h{padding:11px 13px;border-bottom:1px solid var(--border);background:var(--brand-g);}
.udd-h p{font-size:12.5px;font-weight:600;color:var(--brand);}
.udd-h span{font-size:11px;color:var(--text-m);}
.udd a{display:flex;align-items:center;gap:9px;padding:9px 13px;font-size:12.5px;color:var(--text);text-decoration:none;transition:background .14s;}
.udd a:hover{background:var(--brand-g);color:var(--brand);}
.udd a.lg{color:var(--danger);}
.udd a.lg:hover{background:var(--danger-bg);}
.udd a svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
#bm{display:none;position:fixed;top:68px;right:14px;background:var(--surface-s);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--border-s);border-radius:var(--radius);box-shadow:0 10px 36px rgba(0,0,0,.14);width:310px;z-index:9998;overflow:hidden;}
#bm.on{display:block;}
.bm-h{padding:12px 15px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.bm-h h4{font-size:13.5px;font-weight:600;color:var(--text);}
.bm-x{background:none;border:none;cursor:pointer;color:var(--text-m);padding:2px;}
.bm-x svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}
.nm-list{max-height:280px;overflow-y:auto;}
.nm-it{display:flex;align-items:flex-start;gap:9px;padding:11px 15px;border-bottom:1px solid var(--border);}
.nm-dot{width:7px;height:7px;border-radius:50%;background:var(--brand);margin-top:4px;flex-shrink:0;}
.nm-it p{font-size:12.5px;color:var(--text);flex:1;line-height:1.4;}
.nm-clr{background:none;border:1px solid var(--border);border-radius:5px;padding:2px 7px;font-size:11px;color:var(--text-m);cursor:pointer;transition:background .14s;}
.nm-clr:hover{background:var(--brand-g);color:var(--brand);}
.nm-mt{padding:20px 15px;text-align:center;font-size:12.5px;color:var(--text-m);}

/* ══ MAIN ══ */
#main{margin-top:60px;padding:24px 18px;max-width:680px;margin-left:auto;margin-right:auto;position:relative;z-index:1;}

.pg-hdr{margin-bottom:20px;animation:fadeSlideDown .4s ease both;}
.pg-hdr h2{font-size:20px;font-weight:700;color:var(--text);}
.pg-hdr p{font-size:12.5px;color:var(--text-m);margin-top:2px;}

/* DB STATUS PILL */
.db-pill{
  display:inline-flex;align-items:center;gap:8px;
  background:var(--brand-g);border:1px solid var(--border);
  border-radius:30px;padding:6px 14px;
  font-size:12.5px;font-weight:600;color:var(--brand);
  margin-bottom:20px;animation:fadeSlideDown .4s ease both;
}
.db-pill svg{width:14px;height:14px;stroke:var(--brand);fill:none;stroke-width:2;stroke-linecap:round;}
.db-dot{width:8px;height:8px;border-radius:50%;background:var(--success);flex-shrink:0;}

/* ALERT */
.alert{padding:11px 14px;border-radius:9px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:9px;animation:alertIn .3s ease both;}
.alert svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0;}
.a-ok{background:var(--success-bg);color:var(--success);border:1px solid currentColor;}
.a-err{background:var(--danger-bg);color:var(--danger);border:1px solid currentColor;}

/* GLASS CARD */
.card{
  background:var(--surface);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  border:1px solid var(--border);border-radius:var(--radius);
  padding:22px;box-shadow:0 4px 20px rgba(0,0,0,.07);
  transition:background .35s;margin-bottom:16px;
  animation:fadeSlideUp .4s ease both;
}
.card:nth-child(2){animation-delay:.06s;}
.card:nth-child(3){animation-delay:.10s;}

.card-icon{
  width:46px;height:46px;border-radius:11px;
  background:var(--brand-g);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:14px;
}
.card-icon svg{width:22px;height:22px;stroke:var(--brand);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.card.danger-card .card-icon{background:var(--danger-bg);}
.card.danger-card .card-icon svg{stroke:var(--danger);}

.card h3{font-size:15px;font-weight:700;color:var(--text);margin-bottom:5px;}
.card p{font-size:12.5px;color:var(--text-m);line-height:1.5;margin-bottom:16px;}

/* EXPORT BUTTON */
.btn-export{
  display:flex;align-items:center;justify-content:center;gap:9px;
  width:100%;height:44px;
  background:var(--brand);color:#fff;
  border:none;border-radius:9px;cursor:pointer;
  font-size:13.5px;font-weight:700;font-family:inherit;
  text-decoration:none;
  box-shadow:0 2px 10px var(--brand-g);
  transition:background .17s,transform .1s;
}
.btn-export svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.btn-export:hover{background:var(--brand-d);}
.btn-export:active{transform:scale(.97);}

/* DROP ZONE — no visible file input button */
.drop-zone{
  border:2px dashed var(--border);
  border-radius:10px;
  padding:28px 20px;
  text-align:center;
  cursor:pointer;
  transition:border-color .2s,background .2s;
  margin-bottom:14px;
  position:relative;
}
.drop-zone:hover,.drop-zone.drag-over{
  border-color:var(--brand);
  background:var(--brand-g);
}
.drop-zone input[type="file"]{
  position:absolute;inset:0;
  width:100%;height:100%;
  opacity:0;cursor:pointer;
}
.drop-zone svg{width:32px;height:32px;stroke:var(--text-m);fill:none;stroke-width:1.5;stroke-linecap:round;margin-bottom:8px;}
.drop-zone .dz-title{font-size:13.5px;font-weight:600;color:var(--text);margin-bottom:4px;}
.drop-zone .dz-sub{font-size:12px;color:var(--text-m);}
.dz-file-name{font-size:12px;font-weight:600;color:var(--brand);margin-top:8px;display:none;}

.btn-import{
  display:flex;align-items:center;justify-content:center;gap:9px;
  width:100%;height:44px;
  background:var(--success);color:#fff;
  border:none;border-radius:9px;cursor:pointer;
  font-size:13.5px;font-weight:700;font-family:inherit;
  box-shadow:0 2px 10px rgba(26,122,63,.25);
  transition:background .17s,transform .1s;
}
.btn-import svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.btn-import:hover{background:#145430;}
.btn-import:active{transform:scale(.97);}

/* DANGER CARD */
.card.danger-card{border-color:rgba(217,64,64,.2);}
.card.danger-card h3{color:var(--danger);}

.btn-wipe{
  display:flex;align-items:center;justify-content:center;gap:9px;
  width:100%;height:44px;
  background:var(--danger);color:#fff;
  border:none;border-radius:9px;cursor:pointer;
  font-size:13.5px;font-weight:700;font-family:inherit;
  box-shadow:0 2px 10px var(--danger-bg);
  transition:background .17s,transform .1s;
}
.btn-wipe svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.btn-wipe:hover{background:#b03030;}
.btn-wipe:active{transform:scale(.97);}

.back-link{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:6px;font-size:12.5px;color:var(--text-m);text-decoration:none;transition:color .14s;}
.back-link:hover{color:var(--brand);}
.back-link svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}

@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeSlideUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes alertIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}

@media(max-width:600px){#main{padding:14px 10px;}}
</style>
</head>
<body>

<canvas id="trail"></canvas>
<div id="overlay" onclick="closeSb()"></div>

<aside id="sb">
  <div class="sb-head">
    <div class="sb-ico"><svg viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/></svg></div>
    <div class="sb-title"><h3>Memon Biryani</h3><span>Enterprise CRM</span></div>
    <button class="sb-x" onclick="closeSb()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
  </div>
  <nav class="sb-nav">
    <p class="nl">Main</p>
    <a href="dashboard.php" class="ni"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a>
    <!-- <a href="insert_data.php" class="ni"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Insert Data</a> -->
    <!-- <a href="records.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Records</a> -->
    <p class="nl">POS Modules</p>
    <div class="nt" onclick="tog('ps',this)"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>POS Modules<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub" id="ps">
      <a href="pos_screen.php" class="ni"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>POS Counter</a>
      <a href="sell_records.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>POS Reports</a>
      <a href="view_pos_entries.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>View Entries Log</a>
      <a href="cancel_order.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>Order Cancellation</a>
      <a href="pos_manage_products.php" class="ni"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Manage Items</a>
    </div>
    <p class="nl">Expenses</p>
    <div class="nt" onclick="tog('es',this)"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Expense Module<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub" id="es">
      <a href="insert_data.php" class="ni"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add New Expense</a>
      <a href="records.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Expense History</a>
      <a href="settings.php" class="ni"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>Category Setup</a>
    </div>
    <p class="nl">Settings</p>
    <div class="nt open" onclick="tog('ss',this)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Settings<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub on" id="ss">
      <!-- <a href="settings.php" class="ni"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>General Settings</a> -->
      <a href="manage_users.php" class="ni"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Staff Management</a>
      <a href="backup_system.php" class="ni act"><svg viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>Backup &amp; Restore</a>
    </div>
    <p class="nl">Account</p>
    <a href="logout.php" class="ni dng"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
  </nav>
  <div class="sb-foot">
    <div class="sb-usr">
      <div class="av"><?php echo $user_initials; ?></div>
      <div class="sb-usr-info"><p><?php echo $user_name; ?></p><span>Active Session</span></div>
    </div>
  </div>
</aside>

<nav id="nb">
  <button class="nb-menu" onclick="openSb()"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
  <a href="dashboard.php" class="nb-logo">
    <div class="nb-logo-ic"><svg viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/></svg></div>
    <span class="nb-logo-txt">Memon Biryani</span>
  </a>
  <div class="nb-sp"></div>
  <span class="nb-dt" id="nbDate"></span>
  <div class="theme-sw">
    <button class="th-btn act" id="th-l" onclick="setTheme('light')" title="Light">☀️</button>
    <button class="th-btn" id="th-d" onclick="setTheme('dark')" title="Dark">🌙</button>
    <button class="th-btn" id="th-c" onclick="setTheme('custom')" title="Custom">🎨</button>
  </div>
  <button class="nb-bell" onclick="togBell(event)">
    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <?php if($notif_count>0): ?><span class="nb-bdg"><?php echo $notif_count; ?></span><?php endif; ?>
  </button>
  <div class="nb-usr" onclick="togUdd(event)">
    <div class="av"><?php echo $user_initials; ?></div>
    <span class="nb-un"><?php echo $user_name; ?></span>
    <div class="udd" id="udd">
      <div class="udd-h"><p><?php echo $user_name; ?></p><span>Active User</span></div>
      <a href="settings.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Settings</a>
      <a href="logout.php" class="lg"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
    </div>
  </div>
</nav>

<div id="bm">
  <div class="bm-h"><h4>Notifications<?php if($notif_count>0) echo " ($notif_count)"; ?></h4><button class="bm-x" onclick="togBell(event)"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
  <div class="nm-list">
    <?php if(empty($notifications)): ?><p class="nm-mt">No active notifications ✓</p>
    <?php else: foreach($notifications as $n): ?>
      <div class="nm-it"><div class="nm-dot"></div><p><?php echo htmlspecialchars($n['message']??$n['title']??'Notification'); ?></p><form method="POST" action="dashboard.php" style="margin:0"><input type="hidden" name="clear_notif_id" value="<?php echo $n['id']; ?>"><button type="submit" class="nm-clr">Clear</button></form></div>
    <?php endforeach; endif; ?>
  </div>
</div>

<main id="main">

  <div class="pg-hdr">
    <h2>Backup &amp; Restore</h2>
    <p>Export, import or wipe your database</p>
  </div>

  <div class="db-pill">
    <div class="db-dot"></div>
    <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
    Connected: <strong><?php echo $debug_db_info; ?></strong>
  </div>

  <?php if(!empty($msg)): ?>
    <div class="alert a-<?php echo $msg_type; ?>">
      <?php if($msg_type==='ok'): ?><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      <?php else: ?><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php endif; ?>
      <?php echo $msg; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-icon">
      <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    </div>
    <h3>Export Database Backup</h3>
    <p>Download a complete JSON backup of all your data — categories, expenses, POS records, users, and more.</p>
    <a href="backup_system.php?action=export" class="btn-export">
      <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Generate &amp; Download Backup
    </a>
  </div>

  <div class="card">
    <div class="card-icon">
      <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    </div>
    <h3>Import &amp; Restore Backup</h3>
    <p>Select or drag a <strong>.json</strong> backup file to restore. This will clear current tables before restoring.</p>
    <form action="backup_system.php" method="POST" enctype="multipart/form-data" id="importForm">
      <div class="drop-zone" id="dropZone">
        <input type="file" name="backup_file" id="backupFile" accept=".json" required onchange="showFileName(this)">
        <svg viewBox="0 0 24 24"><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>
        <div class="dz-title">Click or drag &amp; drop file here</div>
        <div class="dz-sub">Supports .json backup files only</div>
        <div class="dz-file-name" id="dzFileName"></div>
      </div>
      <button type="submit" class="btn-import" onclick="return confirm('This will clear current tables and restore from backup. Continue?')">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload &amp; Restore Database
      </button>
    </form>
  </div>

  <div class="card danger-card">
    <div class="card-icon">
      <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
    </div>
    <h3>⚠ Danger Zone: Wipe Database</h3>
    <p>This will permanently <strong>drop all tables</strong> and rebuild them empty. All KPIs, amounts, and records will be reset to zero. This cannot be undone.</p>
    <form action="backup_system.php" method="POST" onsubmit="return confirm('Are you absolutely sure? ALL data will be permanently deleted!')">
      <input type="hidden" name="action_wipe" value="1">
      <button type="submit" class="btn-wipe">
        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        Wipe All Database Records
      </button>
    </form>
  </div>

  <a href="dashboard.php" class="back-link">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    Return to Dashboard
  </a>

</main>

<script>
/* MOUSE TRAIL */
var tc=document.getElementById('trail'),tx=tc.getContext('2d'),TW,TH,pts=[];
function rsz(){TW=tc.width=window.innerWidth;TH=tc.height=window.innerHeight;}
rsz();window.addEventListener('resize',rsz);
document.addEventListener('mousemove',function(e){for(var i=0;i<3;i++) pts.push({x:e.clientX+(Math.random()-.5)*16,y:e.clientY+(Math.random()-.5)*16,r:Math.random()*24+8,a:.16,vx:(Math.random()-.5)*.5,vy:(Math.random()-.5)*.5,c:Math.random()>.5?'120,69,12':'190,130,60'});});
function animTr(){tx.clearRect(0,0,TW,TH);pts=pts.filter(function(p){return p.a>.003;});pts.forEach(function(p){tx.beginPath();var g=tx.createRadialGradient(p.x,p.y,0,p.x,p.y,p.r);g.addColorStop(0,'rgba('+p.c+','+p.a+')');g.addColorStop(1,'rgba('+p.c+',0)');tx.fillStyle=g;tx.arc(p.x,p.y,p.r,0,Math.PI*2);tx.fill();p.x+=p.vx;p.y+=p.vy;p.a*=.91;p.r*=.97;});requestAnimationFrame(animTr);}
animTr();

/* THEME */
function setTheme(t){document.documentElement.setAttribute('data-theme',t);localStorage.setItem('mbTheme',t);['l','d','c'].forEach(function(x){document.getElementById('th-'+x).classList.remove('act');});document.getElementById('th-'+{light:'l',dark:'d',custom:'c'}[t]).classList.add('act');}
(function(){setTheme(localStorage.getItem('mbTheme')||'light');})();

/* SIDEBAR */
function openSb(){document.getElementById('sb').classList.add('on');document.getElementById('overlay').classList.add('on');}
function closeSb(){document.getElementById('sb').classList.remove('on');document.getElementById('overlay').classList.remove('on');}
function tog(id,el){document.getElementById(id).classList.toggle('on');el.classList.toggle('open');}

/* BELL */
function togBell(e){e.stopPropagation();document.getElementById('bm').classList.toggle('on');}

/* USER DD */
function togUdd(e){e.stopPropagation();document.getElementById('udd').classList.toggle('on');}
document.addEventListener('click',function(e){
  if(!e.target.closest('.nb-usr'))document.getElementById('udd').classList.remove('on');
  if(!e.target.closest('.nb-bell')&&!e.target.closest('#bm'))document.getElementById('bm').classList.remove('on');
});

/* DROP ZONE */
function showFileName(input){
  var fn=document.getElementById('dzFileName');
  if(input.files&&input.files[0]){
    fn.textContent='✓ '+input.files[0].name;
    fn.style.display='block';
    document.getElementById('dropZone').style.borderColor='var(--brand)';
    document.getElementById('dropZone').style.background='var(--brand-g)';
  }
}
var dz=document.getElementById('dropZone');
dz.addEventListener('dragover',function(e){e.preventDefault();dz.classList.add('drag-over');});
dz.addEventListener('dragleave',function(){dz.classList.remove('drag-over');});
dz.addEventListener('drop',function(e){
  e.preventDefault();dz.classList.remove('drag-over');
  var files=e.dataTransfer.files;
  if(files.length){document.getElementById('backupFile').files=files;showFileName(document.getElementById('backupFile'));}
});

/* DATE */
document.getElementById('nbDate').textContent=new Date().toLocaleDateString('en-PK',{weekday:'short',year:'numeric',month:'short',day:'numeric'});
</script>
</body>
</html>