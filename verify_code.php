<?php
require_once 'auth_functions.php';
$msg         = "";
$success_msg = "";

// Incoming dispatch notification from forgot_password page
if (isset($_GET['msg']) && $_GET['msg'] === 'SecurityCodeDispatched') {
    $success_msg = "A secure verification token has been successfully dispatched to your email registry.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['token']) && isset($_POST['new_password'])) {
        $token       = trim($_POST['token']);
        $newPassword = trim($_POST['new_password']);

        $res = resetPassword($token, $newPassword);

        if ($res === "PASSWORD_CHANGED") {
            header("Location: https://support.memonbiryani.com/?msg=PasswordUpdatedSuccessfully");
            exit();
        } else {
            $msg = $res;
        }
    } else {
        $msg = "Please populate all required system fields (Verification code and New password).";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>Verify Security Code – Memon Biryani</title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}

body{
  font-family:'Segoe UI',system-ui,sans-serif;
  background:#ffffff;
  color:#111;
  min-height:100vh;
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  position:relative;overflow:hidden;
}

canvas#trail{
  position:fixed;top:0;left:0;
  width:100%;height:100%;
  pointer-events:none;z-index:0;
}

.wrapper{
  position:relative;z-index:10;
  width:100%;max-width:420px;
  padding:16px;
}

/* BRAND */
.brand{text-align:center;margin-bottom:28px;}
.brand-icon{
  width:56px;height:56px;border-radius:14px;
  background:#78450C;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 12px;
  box-shadow:0 2px 12px rgba(120,69,12,.25);
}
.brand-icon svg{width:28px;height:28px;fill:#fff;}
.brand h1{font-size:20px;font-weight:700;color:#78450C;letter-spacing:-.3px;}
.brand p{font-size:12px;color:#888;margin-top:2px;letter-spacing:.8px;text-transform:uppercase;}

/* CARD */
.card{
  background:rgba(255,255,255,.85);
  backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
  border:1px solid rgba(120,69,12,.12);
  border-radius:16px;
  padding:32px 28px;
  box-shadow:0 8px 40px rgba(0,0,0,.07),0 1px 4px rgba(120,69,12,.06);
}
.card h2{font-size:17px;font-weight:600;color:#111;margin-bottom:4px;}
.card .sub{font-size:13px;color:#888;margin-bottom:22px;line-height:1.5;}

/* ICON WRAP */
.icon-wrap{
  width:48px;height:48px;border-radius:12px;
  background:rgba(120,69,12,.08);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:16px;
}
.icon-wrap svg{width:24px;height:24px;stroke:#78450C;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}

/* ALERTS */
.alert{
  border-radius:8px;padding:10px 13px;
  font-size:13px;font-weight:600;
  margin-bottom:16px;
  display:flex;align-items:flex-start;gap:8px;
}
.alert svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0;margin-top:1px;}
.alert-ok{background:#f0faf4;border:1px solid #b7eacb;color:#1a7a3f;}
.alert-err{background:#fff2f2;border:1px solid #ffd0d0;color:#c0392b;}

/* FIELD */
.field{margin-bottom:16px;}
.field label{
  display:block;font-size:11.5px;font-weight:700;
  color:#555;margin-bottom:6px;
  letter-spacing:.3px;text-transform:uppercase;
}
.field input{
  width:100%;height:42px;padding:0 14px;
  border:1px solid #e0d6cc;border-radius:9px;
  font-size:14px;color:#111;
  background:rgba(255,255,255,.7);
  outline:none;font-family:inherit;
  transition:border-color .2s,box-shadow .2s;
}
.field input:focus{
  border-color:#78450C;
  box-shadow:0 0 0 3px rgba(120,69,12,.10);
}
.field input::placeholder{color:#bbb;}

/* Token field special */
#token{letter-spacing:3px;font-size:18px;font-weight:700;text-align:center;}
#token::placeholder{letter-spacing:1px;font-size:14px;font-weight:400;}

/* Password wrapper */
.pw-wrap{position:relative;}
.pw-wrap input{padding-right:42px;}
.pw-eye{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  color:#bbb;font-size:15px;padding:0;
  transition:color .14s;
}
.pw-eye:hover{color:#78450C;}

/* Submit */
.btn-submit{
  width:100%;height:44px;
  background:#78450C;color:#fff;
  border:none;border-radius:10px;cursor:pointer;
  font-size:14px;font-weight:700;font-family:inherit;
  display:flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 3px 14px rgba(120,69,12,.30);
  transition:background .2s,transform .1s;
  margin-top:6px;
}
.btn-submit svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.btn-submit:hover{background:#5c3308;box-shadow:0 4px 18px rgba(120,69,12,.38);}
.btn-submit:active{transform:scale(.97);}

/* Back link */
.back-link{
  display:flex;align-items:center;justify-content:center;gap:6px;
  margin-top:20px;font-size:13px;color:#78450C;
  text-decoration:none;font-weight:500;
}
.back-link svg{width:14px;height:14px;stroke:#78450C;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.back-link:hover{text-decoration:underline;}

.footer{margin-top:24px;text-align:center;font-size:11px;color:#ccc;letter-spacing:.3px;}

@media(max-width:480px){
  .card{padding:24px 18px;border-radius:12px;}
}
</style>
</head>
<body>

<canvas id="trail"></canvas>

<div class="wrapper">

  <div class="brand">
    <div class="brand-icon">
      <svg viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/></svg>
    </div>
    <h1>Memon Biryani</h1>
    <p>Enterprise CRM Platform</p>
  </div>

  <div class="card">

    <div class="icon-wrap">
      <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1.5" fill="#78450C" stroke="none"/></svg>
    </div>

    <h2>Verify Authorization Code</h2>
    <p class="sub">Enter the 6-digit code sent to your email along with your new password.</p>

    <?php if(!empty($success_msg)): ?>
      <div class="alert alert-ok">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        <?php echo htmlspecialchars($success_msg); ?>
      </div>
    <?php endif; ?>

    <?php if(!empty($msg)): ?>
      <div class="alert alert-err">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <form action="" method="POST" autocomplete="off">

      <div class="field">
        <label for="token">Verification Code</label>
        <input type="text" id="token" name="token"
               placeholder="Enter 6-digit code"
               maxlength="10" required
               inputmode="numeric">
      </div>

      <div class="field">
        <label for="new_password">New Password</label>
        <div class="pw-wrap">
          <input type="password" id="new_password" name="new_password"
                 placeholder="Enter your new password" required>
          <button type="button" class="pw-eye" onclick="togPw()">
            <i id="eyeIco" class="fa fa-eye" style="font-family:sans-serif;font-style:normal;">👁</i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Authorize &amp; Update Password
      </button>

    </form>

    <a class="back-link" href="login.php">
      <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      Return to Login
    </a>

  </div>

  <p class="footer">© 2025 Memon Biryani Software &middot; All rights reserved</p>
</div>

<script>
/* MOUSE TRAIL */
var tc=document.getElementById('trail'),tx=tc.getContext('2d'),TW,TH,pts=[];
function rsz(){TW=tc.width=window.innerWidth;TH=tc.height=window.innerHeight;}
rsz();window.addEventListener('resize',rsz);
window.addEventListener('mousemove',function(e){
  for(var i=0;i<3;i++) pts.push({
    x:e.clientX+(Math.random()-.5)*14,y:e.clientY+(Math.random()-.5)*14,
    r:Math.random()*22+10,a:.17,
    vx:(Math.random()-.5)*.6,vy:(Math.random()-.5)*.6,
    c:Math.random()>.5?'120,69,12':'200,150,90'
  });
});
function animT(){
  tx.clearRect(0,0,TW,TH);
  pts=pts.filter(function(p){return p.a>.004;});
  pts.forEach(function(p){
    tx.beginPath();
    var g=tx.createRadialGradient(p.x,p.y,0,p.x,p.y,p.r);
    g.addColorStop(0,'rgba('+p.c+','+p.a+')');
    g.addColorStop(1,'rgba('+p.c+',0)');
    tx.fillStyle=g;tx.arc(p.x,p.y,p.r,0,Math.PI*2);tx.fill();
    p.x+=p.vx;p.y+=p.vy;p.a*=.92;p.r*=.97;
  });
  requestAnimationFrame(animT);
}
animT();

/* PASSWORD TOGGLE */
function togPw(){
  var f=document.getElementById('new_password');
  var ico=document.getElementById('eyeIco');
  if(f.type==='password'){f.type='text';ico.textContent='🙈';}
  else{f.type='password';ico.textContent='👁';}
}
</script>
</body>
</html>