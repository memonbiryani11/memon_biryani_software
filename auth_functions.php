<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// PHPMailer Core Source Dependency Mapping
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. SIGNUP TRANSACTION MANAGEMENT
function registerUser($name, $email, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return "Registration conflict: This email address is already registered.";
    }
    
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    if ($stmt->execute([$name, $email, $hashedPassword])) {
        return "Registration successful!";
    }
    return "An error occurred during registration. Please try again.";
}

// 2. LOGIN VERIFICATION SYSTEM WITH SECURITY BRUTE-FORCE LOCKOUT
function loginUser($email, $password) {
    global $pdo;
    $currentTime = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return "Invalid configuration: Email or password parameters do not match.";
    }
    
    // Check Brute-Force Lockout Constraints
    if ($user['lockout_time'] && strtotime($user['lockout_time']) > strtotime($currentTime)) {
        $remainingTime = strtotime($user['lockout_time']) - strtotime($currentTime);
        return "Account temporarily suspended due to security protocols. Please retry in " . ceil($remainingTime / 60) . " minute(s).";
    }
    
    // Password Cryptographic Verification
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
            return "Security alert: Maximum authentication attempts reached. Account locked for 1 hour.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
            $stmt->execute([$newAttempts, $user['id']]);
            return "Invalid security credentials. Remaining validation attempts: " . (5 - $newAttempts);
        }
    }
}

// 3. SECURE STATE AUTHENTICATION & CACHE ISOLATION INTERFACE
function checkSession() {
    // Aggressive browser session cache termination policy
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

    if (!isset($_SESSION['user_id'])) {
        header("Location: login");
        exit();
    }
    
    // Auto session expiration window constraint (6 Hours validation)
    if (time() - $_SESSION['login_time'] > 21600) {
        session_unset();
        session_destroy();
        header("Location: login?msg=Session Expired");
        exit();
    }
}

// 4. PASSWORD RESET REQUEST PROCESS (Dynamic Local / Live SMTP Engine)
function requestPasswordReset($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        return "The requested email registry was not found within our system archives.";
    }
    
    $token = rand(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
    $stmt->execute([$email]);
    
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    if ($stmt->execute([$email, $token, $expiresAt])) {
        
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Username = 'info@memonbiryani.com';
            $mail->Password = 'Memon@12345.'; 

            // ENVIRONMENT INTERCEPTOR: Automation mapping for Local vs Production networks
            if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
                // Local Infrastructure (XAMPP SMTP Relay Environment Settings)
                $mail->Host       = 'localhost'; 
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
                $mail->Port       = 25; 
            } else {
                // Live Production Infrastructure Configuration
                $mail->Host       = 'smtp.hostinger.com'; // Resolved Hostinger Gateway Endpoint
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                $mail->Port       = 587; 
                $mail->Timeout    = 25;
            }

            // Client Payload Packet Routing
            $mail->setFrom('info@memonbiryani.com', 'Memon Biryani Software');
            $mail->addAddress($email); 

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Security Verification Code';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Security Verification Request</h2>
                    <p>A password authorization override token has been generated for your CRM identity profile.</p>
                    <p>Verification Code:</p>
                    <div style='background: #f4f4f4; padding: 15px; font-size: 24px; font-weight: bold; color: #0056b3; letter-spacing: 2px; text-align: center; border-radius: 4px; width: 150px;'>
                        $token
                    </div>
                    <p><small>This security sequence will terminate automatically in 15 minutes.</small></p>
                </div>";

            $mail->send();
            
            $_SESSION['reset_email'] = $email;
            return "SUCCESS_LIVE"; 
        } catch (Exception $e) {
            return "Critical dispatch breakdown. Mailer Diagnosis: {$mail->ErrorInfo}";
        }
    }
    return "System execution error. Processing queue aborted, retry.";
}

// 5. SECURE CRYPTOGRAPHIC UPDATE ENTITY CONTROL
function resetPassword($token, $newPassword) {
    global $pdo;
    $currentTime = date('Y-m-d H:i:s');
    
    if (!isset($_SESSION['reset_email'])) {
        return "Authentication lifecycle expired. Please re-initialize password verification flow.";
    }
    
    $email = $_SESSION['reset_email'];
    
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > ?");
    $stmt->execute([$email, $token, $currentTime]);
    $resetRequest = $stmt->fetch();
    
    if (!$resetRequest) {
        return "Invalid authorization token, or validity window has lapsed.";
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ?, failed_attempts = 0, lockout_time = NULL WHERE email = ?");
    if ($stmt->execute([$hashedPassword, $email])) {
        
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        unset($_SESSION['reset_email']); 
        return "PASSWORD_CHANGED";
    }
    return "Database transaction block exception: Failed to update target profile data logs.";
}
?>