<?php
require_once 'auth_functions.php';
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $msg = registerUser($_POST['name'], $_POST['email'], $_POST['password']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Memon Biryani Software</title>
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

        .success-msg {
            background: #f0faf4;
            border: 1px solid #b7eacb;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            color: #1a7a3f;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .success-msg svg {
            width: 16px;
            height: 16px;
            stroke: #1a7a3f;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .field {
            margin-bottom: 16px;
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

        .input-wrap {
            position: relative;
        }

        .input-wrap svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            stroke: #bbb;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            pointer-events: none;
            transition: stroke 0.2s;
        }

        .input-wrap input {
            width: 100%;
            height: 42px;
            padding: 0 14px 0 38px;
            border: 1px solid #e0d6cc;
            border-radius: 9px;
            font-size: 14px;
            color: #111;
            background: rgba(255,255,255,0.7);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-wrap input:focus {
            border-color: #78450C;
            box-shadow: 0 0 0 3px rgba(120,69,12,0.1);
        }

        .input-wrap input:focus + svg,
        .input-wrap input:focus ~ svg {
            stroke: #78450C;
        }

        .input-wrap input::placeholder {
            color: #bbb;
        }

        /* icon after input for focus color */
        .input-wrap {
            display: flex;
            align-items: center;
        }

        .input-wrap input {
            flex: 1;
        }

        .input-wrap .ico {
            position: absolute;
            left: 12px;
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
            margin-top: 4px;
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

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .divider span {
            flex: 1;
            height: 1px;
            background: #ede8e2;
        }

        .divider p {
            font-size: 12px;
            color: #bbb;
        }

        .login-link {
            text-align: center;
            font-size: 13px;
            color: #666;
        }

        .login-link a {
            color: #78450C;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
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
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>

        <h2>Create your account</h2>
        <p class="sub">Join Memon Biryani CRM — fill in the details below to get started.</p>

        <?php if (!empty($msg)): ?>
            <?php if (stripos($msg, 'success') !== false || stripos($msg, 'registered') !== false || stripos($msg, 'created') !== false): ?>
                <div class="success-msg">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php echo $msg; ?>
                </div>
            <?php else: ?>
                <div class="error-msg">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="field">
                <label for="name">Full name</label>
                <div class="input-wrap">
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Ali Hassan"
                        required
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                    >
                    <svg class="ico" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
            </div>

            <div class="field">
                <label for="email">Email address</label>
                <div class="input-wrap">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@company.com"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                    <svg class="ico" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                    >
                    <svg class="ico" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                Create account
            </button>

        </form>

        <div class="divider">
            <span></span>
            <p>OR</p>
            <span></span>
        </div>

        <p class="login-link">Already have an account? <a href="login.php">Sign in</a></p>

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