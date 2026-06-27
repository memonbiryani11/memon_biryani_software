<?php
require_once 'auth_functions.php';
$msg = "";
$success_msg = "";

if (isset($_GET['msg'])) {
    $success_msg = htmlspecialchars($_GET['msg']);
}

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $res = resetPassword($_POST['token'], $_POST['new_password']);
    if ($res === "PASSWORD_CHANGED") {
        header("Location: login.php?msg=Password successfully updated! Ab login karein.");
        exit();
    } else {
        $msg = $res;
    }
}
?>
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