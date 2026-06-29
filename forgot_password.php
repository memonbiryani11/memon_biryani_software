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

<?php if (!empty($msg)): ?>
    <div class="alert alert-danger" style="background-color: #fff5f5; border: 1px solid #fc8181; color: #c53030; padding: 12px; border-radius: 6px; margin: 15px 0; font-family: Arial, sans-serif; font-size: 14px;">
        <strong style="font-weight: 600;">System Alert:</strong> <?php echo htmlspecialchars($msg); ?>
    </div>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Memon Biryani Software</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: #ffffff;
            color: #111;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        canvas#trail {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 16px;
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: #78450C;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            box-shadow: 0 2px 12px rgba(120,69,12,0.25);
        }

        .brand-icon svg {
            width: 28px;
            height: 28px;
            fill: #fff;
        }

        .brand h1 {
            font-size: 20px;
            font-weight: 700;
            color: #78450C;
            letter-spacing: -0.3px;
        }

        .brand p {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(120,69,12,0.12);
            border-radius: 16px;
            padding: 32px 28px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.07), 0 1px 4px rgba(120,69,12,0.06);
        }

        .icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(120,69,12,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .icon-wrap svg {
            width: 24px;
            height: 24px;
            stroke: #78450C;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .card h2 {
            font-size: 17px;
            font-weight: 600;
            color: #111;
            margin-bottom: 6px;
        }

        .card .sub {
            font-size: 13px;
            color: #888;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .error-msg {
            background: #fff2f2;
            border: 1px solid #ffd0d0;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            color: #c0392b;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .error-msg svg {
            width: 16px;
            height: 16px;
            stroke: #c0392b;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .field {
            margin-bottom: 20px;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .field input {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            border: 1px solid #e0d6cc;
            border-radius: 9px;
            font-size: 14px;
            color: #111;
            background: rgba(255,255,255,0.7);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .field input:focus {
            border-color: #78450C;
            box-shadow: 0 0 0 3px rgba(120,69,12,0.1);
        }

        .field input::placeholder {
            color: #bbb;
        }

        .btn-submit {
            width: 100%;
            height: 44px;
            background: #78450C;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
            box-shadow: 0 3px 14px rgba(120,69,12,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit svg {
            width: 16px;
            height: 16px;
            stroke: #fff;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .btn-submit:hover {
            background: #5c3308;
            box-shadow: 0 4px 18px rgba(120,69,12,0.38);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
            font-size: 13px;
            color: #78450C;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link svg {
            width: 14px;
            height: 14px;
            stroke: #78450C;
            fill: none;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            font-size: 11px;
            color: #ccc;
            letter-spacing: 0.3px;
        }

        @media (max-width: 480px) {
            .card {
                padding: 24px 18px;
                border-radius: 12px;
            }
        }
    </style>
</head>
<body>

<canvas id="trail"></canvas>

<div class="wrapper">

    <div class="brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/>
            </svg>
        </div>
        <h1>Memon Biryani</h1>
        <p>Enterprise CRM Platform</p>
    </div>

    <div class="card">

        <div class="icon-wrap">
            <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
        </div>

        <h2>Forgot your password?</h2>
        <p class="sub">Enter your registered email and we'll send you a reset code right away.</p>

        <?php if (!empty($msg)): ?>
            <div class="error-msg">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label for="email">Registered email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@company.com"
                    required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                >
            </div>

            <button type="submit" class="btn-submit">
                <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Send reset code
            </button>
        </form>

        <a class="back-link" href="login.php">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Back to login
        </a>

    </div>

    <p class="footer">© 2025 Memon Biryani Software &middot; All rights reserved</p>
</div>

<script>
    const canvas = document.getElementById('trail');
    const ctx = canvas.getContext('2d');
    let W, H;
    let particles = [];

    function resize() {
        W = canvas.width = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    window.addEventListener('mousemove', function(e) {
        for (let i = 0; i < 3; i++) {
            particles.push({
                x: e.clientX + (Math.random() - 0.5) * 12,
                y: e.clientY + (Math.random() - 0.5) * 12,
                r: Math.random() * 22 + 10,
                alpha: 0.18,
                vx: (Math.random() - 0.5) * 0.6,
                vy: (Math.random() - 0.5) * 0.6,
                color: Math.random() > 0.5 ? '120,69,12' : '200,160,110'
            });
        }
    });

    function animate() {
        ctx.clearRect(0, 0, W, H);
        particles = particles.filter(function(p) { return p.alpha > 0.005; });
        for (let i = 0; i < particles.length; i++) {
            let p = particles[i];
            ctx.beginPath();
            let grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
            grad.addColorStop(0, 'rgba(' + p.color + ',' + p.alpha + ')');
            grad.addColorStop(1, 'rgba(' + p.color + ',0)');
            ctx.fillStyle = grad;
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
            p.x += p.vx;
            p.y += p.vy;
            p.alpha *= 0.92;
            p.r *= 0.97;
        }
        requestAnimationFrame(animate);
    }
    animate();
</script>

</body>
</html>