<?php
session_start();
require_once 'db.php'; // Aapka PDO database connection file

header('Content-Type: application/json');

$current_user_id = $_SESSION['user_id'] ?? 0;
if ($current_user_id == 0) {
    echo json_encode(['count' => 0, 'new_alerts' => []]);
    exit();
}

// 1. Pehle Total Unread Count nikalen (Badge ke liye)
// Taake purani aur nayi total counters add hokar plus hoti rahen
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND user_id = ?");
$count_stmt->execute([$current_user_id]);
$total_unread = $count_stmt->fetchColumn();

// 2. Sirf wo notifications uthayen jo abhi abhi aayi hain aur jinka alert nahi dikhaya gaya (is_alerted = 0)
$alert_stmt = $pdo->prepare("SELECT id, message FROM notifications WHERE is_read = 0 AND is_alerted = 0 AND user_id = ? ORDER BY id ASC");
$alert_stmt->execute([$current_user_id]);
$new_alerts = $alert_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Agar koi naye alerts mile hain, to unhe mark kar dein taake dobara popup na aaye
if (count($new_alerts) > 0) {
    $update_stmt = $pdo->prepare("UPDATE notifications SET is_alerted = 1 WHERE is_read = 0 AND is_alerted = 0 AND user_id = ?");
    $update_stmt->execute([$current_user_id]);
}

echo json_encode([
    'count' => (int)$total_unread,
    'new_alerts' => $new_alerts
]);
exit();
?>