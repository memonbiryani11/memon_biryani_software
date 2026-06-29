<?php if (isset($_GET['msg']) && $_GET['msg'] === 'PasswordUpdatedSuccessfully'): ?>
    <div class="alert alert-success" style="background-color: #f0fff4; border: 1px solid #68d391; color: #22543d; padding: 12px; border-radius: 6px; margin: 15px 0; font-family: Arial, sans-serif; font-size: 14px; text-align: center;">
        <strong>Success!</strong> Your security credentials have been successfully updated. Please authenticate to continue.
    </div>
<?php endif; ?>

<?php
// Top par cache kill headers taake dashboard se back aane par glitch na ho
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'auth_functions.php';

// Agar user pehle se logged in hai to login page khulne ke bajaye seedha dashboard bhejein
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$msg = "";
if (isset($_GET['msg'])) { 
    $msg = htmlspecialchars($_GET['msg']); 
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $res = loginUser($_POST['email'], $_POST['password']);
    if ($res === "SUCCESS") {
        header("Location: dashboard.php");
        exit();
    } else {
        $msg = $res;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Memon Biryani Software</title>
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

        .login-wrapper {
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

        .card h2 {
            font-size: 17px;
            font-weight: 600;
            color: #111;
            margin-bottom: 4px;
        }

        .card .sub {
            font-size: 13px;
            color: #888;
            margin-bottom: 24px;
        }

        .error-msg {
            background: #fff2f2;
            border: 1px solid #ffd0d0;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            color: #c0392b;
            margin-bottom: 16px;
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

        .forgot {
            display: block;
            text-align: right;
            font-size: 12px;
            color: #78450C;
            text-decoration: none;
            margin-top: -8px;
            margin-bottom: 20px;
        }

        .forgot:hover {
            text-decoration: underline;
        }

        .btn-login {
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
        }

        .btn-login:hover {
            background: #5c3308;
            box-shadow: 0 4px 18px rgba(120,69,12,0.38);
        }

        .btn-login:active {
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

        .signup-link {
            text-align: center;
            font-size: 13px;
            color: #666;
        }

        .signup-link a {
            color: #78450C;
            font-weight: 600;
            text-decoration: none;
        }

        .signup-link a:hover {
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

<div class="login-wrapper">

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
        <h2>Welcome back</h2>
        <p class="sub">Sign in to your account to continue</p>

        <?php if (!empty($msg)): ?>
            <div class="error-msg"><?php echo $msg; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@company.com"
                    required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                >
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                >
            </div>

            <a class="forgot" href="forgot_password.php">Forgot password?</a>

            <button type="submit" class="btn-login">Sign in</button>
        </form>

        <div class="divider">
            <span></span>
            <p>OR</p>
            <span></span>
        </div>

        <p class="signup-link">
            New to platform? <a href="signup.php">Create an account</a>
        </p>
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