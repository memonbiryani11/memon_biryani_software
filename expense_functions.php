<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// 1. Category Create Karna
function createCategory($name) {
    global $pdo;
    $name = trim($name);
    if(empty($name)) return "Name khali nahi ho sakta!";
    
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $stmt->execute([$name]);
        return "SUCCESS";
    } catch (PDOException $e) {
        return "Yeh category pehle se maujood hai!";
    }
}

// 2. Saari Categories Get Karna
function getAllCategories() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
    return $stmt->fetchAll();
}

// 3. Multiple Expenses Insert aur Date Split Processing
function insertExpenses($amounts, $userId, $selectedDate) {
    global $pdo;
    $userName = $_SESSION['user_name'];
    
    // PHP level par date structure se day, month, year nikalen
    $timestamp = strtotime($selectedDate);
    $day = date('l', $timestamp);      // e.g. Monday
    $month = date('F', $timestamp);    // e.g. June
    $year = date('Y', $timestamp);     // e.g. 2026
    
    try {
        $pdo->beginTransaction();
        
        // Query modified with day, month, year
        $stmt = $pdo->prepare("
            INSERT INTO expenses (category_id, amount, user_id, date, expense_day, expense_month, expense_year) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $hasData = false;
        foreach ($amounts as $catId => $amount) {
            if ($amount !== '' && $amount >= 0) {
                $stmt->execute([$catId, $amount, $userId, $selectedDate, $day, $month, $year]);
                $hasData = true;
            }
        }
        
        if ($hasData) {
            $msg = "User " . $userName . " ne data insert kiya hai.";
            
            // Notification query me bhi day, month, year add kiye hain
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_name, expense_date, expense_day, expense_month, expense_year, message) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $notifStmt->execute([$userName, $selectedDate, $day, $month, $year, $msg]);
            
            $notifId = $pdo->lastInsertId();
            $pdo->commit();
            return "SUCCESS:" . $notifId . ":" . $selectedDate;
        }
        
        $pdo->rollBack();
        return "Kam se kam ek amount daalna lazmi hai.";
    } catch (Exception $e) {
        $pdo->rollBack();
        return "Data save nahi ho saka: " . $e->getMessage();
    }
}

// 4. Active Notifications nikalna
function getActiveNotificationsForUser($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT n.* FROM notifications n
        LEFT JOIN user_notifications un ON n.id = un.notification_id AND un.user_id = ?
        WHERE un.notification_id IS NULL
        ORDER BY n.id DESC LIMIT 10
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// 5. User notification clear karna
function clearNotificationForUser($notificationId, $userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_notifications (user_id, notification_id) VALUES (?, ?)");
        $stmt->execute([$userId, $notificationId]);
        return "SUCCESS";
    } catch (Exception $e) {
        return "Error";
    }
}
?>