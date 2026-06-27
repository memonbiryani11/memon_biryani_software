<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// PHPMailer files ko sahi path se include karein (as per your screenshot)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. SIGNUP FUNCTION
function registerUser($name, $email, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return "Email pehle se register hai!";
    }
    
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    if ($stmt->execute([$name, $email, $hashedPassword])) {
        return "Registration Kamyab!";
    }
    return "Kuch masla hua, dobara koshish karein.";
}

// 2. LOGIN FUNCTION WITH 5-ATTEMPT LOCK
function loginUser($email, $password) {
    global $pdo;
    $currentTime = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return "Email ya password galat hai!";
    }
    
    // Check Lockout
    if ($user['lockout_time'] && strtotime($user['lockout_time']) > strtotime($currentTime)) {
        $remainingTime = strtotime($user['lockout_time']) - strtotime($currentTime);
        return "Aapka account locked hai. " . ceil($remainingTime / 60) . " minute baad koshish karein.";
    }
    
    // Password Verify
    if (password_verify($password, $user['password'])) {
        $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_time = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['login_time'] = time(); 
        
        return "SUCCESS";
    } else {
        $newAttempts = $user['failed_attempts'] + 1;
        
        if ($newAttempts >= 5) {
            $lockoutTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, lockout_time = ? WHERE id = ?");
            $stmt->execute([$newAttempts, $lockoutTime, $user['id']]);
            return "5 galat attempts! Aapka account 1 ghante ke liye lock kar diya gaya hai.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
            $stmt->execute([$newAttempts, $user['id']]);
            return "Password galat hai! Remaining attempts: " . (5 - $newAttempts);
        }
    }
}

// 3. SESSION SECURITY & CACHE DISABLE FUNCTION
function checkSession() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    if (time() - $_SESSION['login_time'] > 21600) {
        session_unset();
        session_destroy();
        header("Location: login.php?msg=Session Expired");
        exit();
    }
}

// 4. FORGOT PASSWORD - GENERATE & SEND CODE VIA SMTP
function requestPasswordReset($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        return "Yeh email system mein maujood nahi hai!";
    }
    
    $token = rand(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
    $stmt->execute([$email]);
    
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    if ($stmt->execute([$email, $token, $expiresAt])) {
        
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            // $mail->Host       = 'mail.memonbiryani.com'; // Aapke domain ka SMTP host (Aksar mail.domain.com hota hai)
            $mail->Host      = 'localhost'; // Agar Gmail use kar rahe hain to
            $mail->SMTPAuth   = true;
            $mail->Username   = 'info@memonbiryani.com'; // Aapka Email
            $mail->Password   = 'Memon@12345.';          // Aapka Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Agar 587 port hai to STARTTLS best hai
            $mail->Port       = 465; // Agar 587 port hai to SSL use karein, aur agar 587 hai to STARTTLS use karein

            // Recipients
            $mail->setFrom('info@memonbiryani.com', 'Memon Biryani Software');
            $mail->addAddress($email); 

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code - Memon Biryani';
            $mail->Body    = "Aapka password reset code yeh hai: <br><br><b style='font-size:20px; color:blue;'>$token</b><br><br>Yeh code 15 minutes tak valid hai.";

            $mail->send();
            
            $_SESSION['reset_email'] = $email;
            return "SUCCESS_LIVE"; 
        } catch (Exception $e) {
            return "Email nahi bheji ja saki. Mailer Error: {$mail->ErrorInfo}";
        }
    }
    return "Kuch masla hua, dobara koshish karein.";
}

// 5. VERIFY CODE AND UPDATE PASSWORD
function resetPassword($token, $newPassword) {
    global $pdo;
    $currentTime = date('Y-m-d H:i:s');
    
    if (!isset($_SESSION['reset_email'])) {
        return "Session expired! Dobara start karein.";
    }
    
    $email = $_SESSION['reset_email'];
    
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > ?");
    $stmt->execute([$email, $token, $currentTime]);
    $resetRequest = $stmt->fetch();
    
    if (!$resetRequest) {
        return "Invalid Code ya Code Expire ho chuka hai!";
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ?, failed_attempts = 0, lockout_time = NULL WHERE email = ?");
    if ($stmt->execute([$hashedPassword, $email])) {
        
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        unset($_SESSION['reset_email']); 
        return "PASSWORD_CHANGED";
    }
    return "Password update nahi ho saka.";
}
?>