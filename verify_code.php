<?php
require_once 'auth_functions.php';
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Form inputs capture karein
    $token = trim($_POST['token']);
    $newPassword = trim($_POST['new_password']);
    
    // 2. Auth engine se password update process execute karein
    $res = resetPassword($token, $newPassword);
    
    if ($res === "PASSWORD_CHANGED") {
        // 3. ENVIRONMENT CHECK: Local vs Production Redirect Setup
        if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
            // Local environment redirect path
            $redirectUrl = "/Memon_Biryani_Software/login?msg=PasswordUpdatedSuccessfully";
        } else {
            // Live production domain strict absolute redirect (support.memonbiryani.com)
            $redirectUrl = "https://support.memonbiryani.com/login?msg=PasswordUpdatedSuccessfully";
        }
        
        header("Location: " . $redirectUrl);
        exit();
    } else {
        // Agar code invalid ya expire ho chuka ho
        $msg = $res;
    }
}
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-danger" style="background-color: #fff5f5; border: 1px solid #fc8181; color: #c53030; padding: 12px; border-radius: 6px; margin: 15px 0; font-family: Arial, sans-serif; font-size: 14px;">
        <strong style="font-weight: 600;">Verification Alert:</strong> <?php echo htmlspecialchars($msg); ?>
    </div>
<?php endif; ?>
<!DOCTYPE html>
<html>
<head><title>Verify Reset Code</title></head>
<body>
    <h2>Verify Code & Change Password</h2>
    
    <?php if(!empty($success_msg)): ?>
        <p style="color:green; font-weight:bold;"><?php echo $success_msg; ?></p>
    <?php endif; ?>
    
    <?php if(!empty($msg)): ?>
        <p style="color:red; font-weight:bold;"><?php echo $msg; ?></p>
    <?php endif; ?>
    
    <p>Email: <b><?php echo $_SESSION['reset_email']; ?></b></p>
    
    <form method="POST">
        <input type="text" name="token" placeholder="6-Digit Code Dalein" maxlength="6" required><br><br>
        <input type="password" name="new_password" placeholder="Naya Password Likhein" required><br><br>
        <button type="submit">Update Password</button>
    </form>
</body>
</html>