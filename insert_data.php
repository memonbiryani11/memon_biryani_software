<?php
require_once 'auth_functions.php';
require_once 'expense_functions.php';
require_once 'db.php';
checkSession();

$userId = $_SESSION['user_id'];
$msg = "";
$alertId = "";
$alertDate = "";

// Handle Expense Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_expenses'])) {
    $filtered_amounts = array_filter($_POST['amounts'], function($value) { return $value !== ''; });
    $res = insertExpenses($filtered_amounts, $userId, $_POST['selected_date']);
    if (strpos($res, "SUCCESS:") === 0) {
        $parts = explode(":", $res);
        $msg = "SUCCESS_ALERT";
        $alertId = $parts[1];
        $alertDate = $parts[2];
    } else {
        $msg = $res;
    }
}

// Handle Single Notification Clear
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_notif_id'])) {
    clearNotificationForUser($_POST['clear_notif_id'], $userId);
    header("Location: insert_data.php"); exit();
}

// Fetch ONLY active categories
$catStmt = $pdo->query("SELECT id, category_name FROM categories WHERE is_deleted = 0 ORDER BY category_name ASC");
$categories = $catStmt->fetchAll();

$notifications = getActiveNotificationsForUser($userId);
$notif_count   = count($notifications);
$user_name     = htmlspecialchars($_SESSION['user_name'] ?? 'Muhammad Hamza');
$user_initials = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Insert Expenses – Memon Biryani</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<style>
/* ══ THEMES ══ */
:root,[data-theme="light"]{
  --brand:#78450C;--brand-d:#5c3308;--brand-g:rgba(120,69,12,.18);
  --bg:#f2ebe1;--bg2:#e8ddd0;
  --surface:rgba(255,255,255,.78);--surface-s:rgba(255,255,255,.96);
  --border:rgba(120,69,12,.13);--border-s:rgba(120,69,12,.22);
  --text:#18100a;--text-m:#8a7060;
  --danger:#d94040;--danger-bg:rgba(217,64,64,.08);
  --success:#1a7a3f;--success-bg:rgba(26,122,63,.08);
  --blur:20px;--radius:12px;
}
[data-theme="dark"]{
  --brand:#c47a2a;--brand-d:#a05e18;--brand-g:rgba(196,122,42,.22);
  --bg:#0f0b08;--bg2:#1a1208;
  --surface:rgba(30,20,10,.86);--surface-s:rgba(40,28,14,.97);
  --border:rgba(196,122,42,.18);--border-s:rgba(196,122,42,.30);
  --text:#f0e6d8;--text-m:#9a8a78;
  --danger:#e07070;--danger-bg:rgba(224,112,112,.10);
  --success:#4caf7d;--success-bg:rgba(76,175,125,.10);
  --blur:18px;--radius:12px;
}
[data-theme="custom"]{
  --brand:#1a6b5c;--brand-d:#145248;--brand-g:rgba(26,107,92,.20);
  --bg:#eef6f4;--bg2:#daeee9;
  --surface:rgba(255,255,255,.76);--surface-s:rgba(255,255,255,.97);
  --border:rgba(26,107,92,.15);--border-s:rgba(26,107,92,.25);
  --text:#0d2e28;--text-m:#4a7a70;
  --danger:#c0392b;--danger-bg:rgba(192,57,43,.08);
  --success:#1a6b5c;--success-bg:rgba(26,107,92,.10);
  --blur:20px;--radius:12px;
}

*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;transition:background .35s,color .35s;}
#trail{position:fixed;inset:0;pointer-events:none;z-index:0;}
#overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(3px);z-index:990;}
#overlay.on{display:block;}

/* ══ SIDEBAR ══ */
#sb{position:fixed;top:0;left:0;width:260px;height:100vh;background:var(--surface);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));border-right:1px solid var(--border);box-shadow:4px 0 32px rgba(0,0,0,.10);z-index:1000;display:flex;flex-direction:column;transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1);}
#sb.on{transform:translateX(0);}
.sb-head{display:flex;align-items:center;gap:10px;padding:16px 14px;border-bottom:1px solid var(--border);flex-shrink:0;}
.sb-ico{width:38px;height:38px;border-radius:10px;background:var(--brand);display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px var(--brand-g);flex-shrink:0;}
.sb-ico svg{width:20px;height:20px;fill:#fff;}
.sb-title h3{font-size:13.5px;font-weight:700;color:var(--brand);line-height:1.2;}
.sb-title span{font-size:10px;color:var(--text-m);text-transform:uppercase;letter-spacing:.7px;}
.sb-x{margin-left:auto;width:28px;height:28px;border-radius:7px;background:none;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-m);transition:background .15s,color .15s;}
.sb-x:hover{background:var(--brand-g);color:var(--brand);}
.sb-x svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}
.sb-nav{flex:1;overflow-y:auto;padding:8px 6px;}
.sb-nav::-webkit-scrollbar{width:3px;}
.sb-nav::-webkit-scrollbar-thumb{background:var(--brand-g);border-radius:3px;}
.nl{font-size:10px;font-weight:700;color:var(--text-m);text-transform:uppercase;letter-spacing:.9px;padding:10px 10px 3px;}
.ni{display:flex;align-items:center;gap:9px;padding:8px 11px;border-radius:8px;text-decoration:none;color:var(--text);font-size:13px;font-weight:500;transition:background .14s,color .14s;margin-bottom:1px;}
.ni:hover{background:var(--brand-g);color:var(--brand);}
.ni.act{background:var(--brand);color:#fff;box-shadow:0 2px 10px var(--brand-g);}
.ni.act svg{stroke:#fff!important;}
.ni svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.ni.dng{color:var(--danger);}
.ni.dng svg{stroke:var(--danger)!important;}
.ni.dng:hover{background:var(--danger-bg);}
.nt{display:flex;align-items:center;gap:9px;padding:8px 11px;border-radius:8px;color:var(--text);font-size:13px;font-weight:500;cursor:pointer;user-select:none;margin-bottom:1px;transition:background .14s,color .14s;}
.nt:hover{background:var(--brand-g);color:var(--brand);}
.nt svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.nt .cv{margin-left:auto;width:13px;height:13px;transition:transform .22s;}
.nt.open .cv{transform:rotate(180deg);}
.nsub{display:none;padding-left:24px;}
.nsub.on{display:block;}
.nsub .ni{font-size:12.5px;font-weight:400;padding:7px 11px;}
.sb-foot{padding:10px 6px;border-top:1px solid var(--border);flex-shrink:0;}
.sb-usr{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:8px;background:var(--brand-g);}
.av{border-radius:50%;background:var(--brand);color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px var(--brand-g);}
.sb-usr .av{width:32px;height:32px;font-size:12px;}
.sb-usr-info p{font-size:12.5px;font-weight:600;color:var(--text);line-height:1.2;}
.sb-usr-info span{font-size:11px;color:var(--text-m);}

/* ══ NAVBAR ══ */
#nb{position:fixed;top:0;left:0;right:0;height:60px;background:var(--surface);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));border-bottom:1px solid var(--border);box-shadow:0 2px 16px rgba(0,0,0,.07);display:flex;align-items:center;padding:0 18px;gap:10px;z-index:900;transition:background .35s;}
.nb-menu{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,border-color .15s;}
.nb-menu:hover{background:var(--brand-g);border-color:var(--brand);}
.nb-menu svg{width:17px;height:17px;stroke:var(--text);fill:none;stroke-width:2;stroke-linecap:round;}
.nb-logo{display:flex;align-items:center;gap:9px;text-decoration:none;}
.nb-logo-ic{width:30px;height:30px;border-radius:7px;background:var(--brand);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px var(--brand-g);}
.nb-logo-ic svg{width:15px;height:15px;fill:#fff;}
.nb-logo-txt{font-size:14.5px;font-weight:700;color:var(--brand);}
.nb-sp{flex:1;}
.nb-dt{font-size:11.5px;color:var(--text-m);display:none;}
@media(min-width:640px){.nb-dt{display:block;}}
.theme-sw{display:flex;align-items:center;gap:3px;background:var(--bg2);border-radius:8px;padding:3px;border:1px solid var(--border);}
.th-btn{width:30px;height:28px;border-radius:6px;border:none;background:none;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .15s;color:var(--text-m);}
.th-btn.act{background:var(--brand);color:#fff;}
.th-btn:hover:not(.act){background:var(--brand-g);}
.nb-bell{position:relative;width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s,border-color .15s;}
.nb-bell:hover{background:var(--brand-g);border-color:var(--brand);}
.nb-bell svg{width:17px;height:17px;stroke:var(--text);fill:none;stroke-width:1.8;stroke-linecap:round;}
.nb-bdg{position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;font-size:10px;font-weight:700;min-width:17px;height:17px;border-radius:9px;padding:0 3px;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);}
.nb-usr{display:flex;align-items:center;gap:7px;cursor:pointer;position:relative;}
.nb-usr .av{width:32px;height:32px;font-size:12px;}
.nb-un{font-size:12.5px;font-weight:600;color:var(--text);display:none;}
@media(min-width:520px){.nb-un{display:block;}}
.udd{display:none;position:absolute;top:calc(100% + 10px);right:0;background:var(--surface-s);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--border-s);border-radius:var(--radius);box-shadow:0 8px 28px rgba(0,0,0,.14);min-width:175px;z-index:9999;overflow:hidden;}
.udd.on{display:block;}
.udd-h{padding:11px 13px;border-bottom:1px solid var(--border);background:var(--brand-g);}
.udd-h p{font-size:12.5px;font-weight:600;color:var(--brand);}
.udd-h span{font-size:11px;color:var(--text-m);}
.udd a{display:flex;align-items:center;gap:9px;padding:9px 13px;font-size:12.5px;color:var(--text);text-decoration:none;transition:background .14s;}
.udd a:hover{background:var(--brand-g);color:var(--brand);}
.udd a.lg{color:var(--danger);}
.udd a.lg:hover{background:var(--danger-bg);}
.udd a svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
#bm{display:none;position:fixed;top:68px;right:14px;background:var(--surface-s);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--border-s);border-radius:var(--radius);box-shadow:0 10px 36px rgba(0,0,0,.14);width:310px;z-index:9998;overflow:hidden;}
#bm.on{display:block;}
.bm-h{padding:12px 15px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.bm-h h4{font-size:13.5px;font-weight:600;color:var(--text);}
.bm-x{background:none;border:none;cursor:pointer;color:var(--text-m);padding:2px;}
.bm-x svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}
.nm-list{max-height:280px;overflow-y:auto;}
.nm-it{display:flex;align-items:flex-start;gap:9px;padding:11px 15px;border-bottom:1px solid var(--border);}
.nm-dot{width:7px;height:7px;border-radius:50%;background:var(--brand);margin-top:4px;flex-shrink:0;}
.nm-it p{font-size:12.5px;color:var(--text);flex:1;line-height:1.4;}
.nm-it a{font-size:12px;color:var(--text);text-decoration:none;flex:1;line-height:1.4;}
.nm-it a:hover{color:var(--brand);}
.nm-clr{background:none;border:1px solid var(--border);border-radius:5px;padding:2px 7px;font-size:11px;color:var(--text-m);cursor:pointer;transition:background .14s;flex-shrink:0;}
.nm-clr:hover{background:var(--brand-g);color:var(--brand);}
.nm-mt{padding:20px 15px;text-align:center;font-size:12.5px;color:var(--text-m);}

/* ══ MAIN ══ */
#main{margin-top:60px;padding:24px 18px;max-width:900px;margin-left:auto;margin-right:auto;position:relative;z-index:1;}

.pg-hdr{margin-bottom:20px;animation:fadeSlideDown .4s ease both;}
.pg-hdr h2{font-size:20px;font-weight:700;color:var(--text);}
.pg-hdr p{font-size:12.5px;color:var(--text-m);margin-top:2px;}

/* ══ GLASS CARD ══ */
.card{background:var(--surface);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));border:1px solid var(--border);border-radius:var(--radius);padding:22px;box-shadow:0 4px 20px rgba(0,0,0,.07);transition:background .35s;margin-bottom:18px;animation:fadeSlideUp .4s ease both;}

.card-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;}
.card-title svg{width:16px;height:16px;stroke:var(--brand);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* ══ DATE SECTION ══ */
/* Date picker on top */
.date-top{margin-bottom:14px;}
.date-top label{display:block;font-size:11px;font-weight:700;color:var(--text-m);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px;}
.date-top input[type="date"]{
  height:38px;padding:0 12px;
  border:1px solid var(--border);border-radius:8px;
  font-size:13px;font-weight:600;color:var(--text);
  background:rgba(255,255,255,.55);backdrop-filter:blur(6px);
  outline:none;font-family:inherit;cursor:pointer;
  width:100%;max-width:220px;
  transition:border-color .17s,box-shadow .17s;
}
[data-theme="dark"] .date-top input{background:rgba(255,255,255,.07);}
.date-top input:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-g);}

/* Auto-fill date display row - below date picker */
.date-parts{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px;}
@media(max-width:480px){.date-parts{grid-template-columns:repeat(3,1fr);gap:8px;}}

.date-part-box{
  background:var(--brand-g);
  border:1px solid var(--border);border-radius:9px;
  padding:10px 12px;text-align:center;
}
.date-part-box .dp-label{font-size:10px;font-weight:700;color:var(--text-m);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;}
.date-part-box .dp-val{font-size:15px;font-weight:700;color:var(--brand);}

/* ══ EXPENSE GRID ══ */
/* phone: 2 cols, tablet: 3, desktop: 3 */
.expense-grid{
  display:grid;
  grid-template-columns:repeat(2,1fr);
  gap:12px;
}
@media(min-width:580px){.expense-grid{grid-template-columns:repeat(3,1fr);gap:14px;}}
@media(min-width:1024px){.expense-grid{grid-template-columns:repeat(3,1fr);gap:16px;}}

.exp-card{
  background:var(--surface-s);
  border:1px solid var(--border);border-radius:10px;
  padding:14px 12px;
  transition:border-color .18s,box-shadow .18s,opacity .2s;
  animation:fadeSlideUp .35s ease both;
  position:relative;
}
.exp-card:nth-child(1){animation-delay:.05s}.exp-card:nth-child(2){animation-delay:.08s}
.exp-card:nth-child(3){animation-delay:.11s}.exp-card:nth-child(4){animation-delay:.14s}
.exp-card:nth-child(5){animation-delay:.17s}.exp-card:nth-child(6){animation-delay:.20s}
.exp-card:nth-child(7){animation-delay:.23s}.exp-card:nth-child(8){animation-delay:.26s}
.exp-card:hover{border-color:var(--brand);box-shadow:0 4px 14px var(--brand-g);}
.exp-card.hidden{opacity:.35;pointer-events:none;}

.exp-card-name{
  font-size:12px;font-weight:700;color:var(--text-m);
  text-transform:uppercase;letter-spacing:.3px;
  margin-bottom:8px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.exp-card-input{
  width:100%;height:36px;padding:0 10px;
  border:1px solid var(--border);border-radius:7px;
  font-size:13px;color:var(--text);
  background:rgba(255,255,255,.5);backdrop-filter:blur(6px);
  outline:none;font-family:inherit;
  transition:border-color .17s,box-shadow .17s;
}
[data-theme="dark"] .exp-card-input{background:rgba(255,255,255,.06);}
.exp-card-input:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-g);}
.exp-card-input::placeholder{color:var(--text-m);}

/* Remove (×) button top-right */
.exp-rm-btn{
  position:absolute;top:8px;right:8px;
  width:20px;height:20px;border-radius:50%;
  background:none;border:1px solid var(--border);
  cursor:pointer;color:var(--danger);
  display:flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:700;line-height:1;
  transition:background .13s,border-color .13s;
}
.exp-rm-btn:hover{background:var(--danger-bg);border-color:var(--danger);}

/* ══ ALERTS ══ */
.alert{padding:10px 13px;border-radius:8px;font-size:12.5px;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:8px;animation:alertIn .3s ease both;}
.alert svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0;}
.alert-err{background:var(--danger-bg);color:var(--danger);border:1px solid currentColor;}

/* Submit button */
.btn-submit{
  height:44px;padding:0 28px;background:var(--brand);color:#fff;
  border:none;border-radius:10px;cursor:pointer;
  font-size:14px;font-weight:700;font-family:inherit;
  display:inline-flex;align-items:center;gap:9px;
  box-shadow:0 2px 12px var(--brand-g);
  transition:background .17s,transform .1s;margin-top:16px;
}
.btn-submit svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;}
.btn-submit:hover{background:var(--brand-d);}
.btn-submit:active{transform:scale(.97);}

/* ══ TOAST ══ */
.toast-wrap{position:fixed;bottom:20px;right:16px;z-index:9000;display:flex;flex-direction:column;gap:10px;max-width:290px;}
.toast{
  background:var(--surface-s);
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  border:1px solid var(--border-s);border-radius:10px;
  padding:13px 14px;
  box-shadow:0 6px 24px rgba(0,0,0,.15);
  border-left:4px solid var(--brand);
  position:relative;
  animation:fadeSlideUp .3s ease both;
}
.toast.success-toast{border-left-color:var(--success);}
.toast-close{position:absolute;top:7px;right:9px;background:none;border:none;cursor:pointer;color:var(--text-m);font-size:15px;font-weight:700;padding:0;line-height:1;}
.toast-close:hover{color:var(--danger);}
.toast a{text-decoration:none;color:var(--text);display:block;}
.toast a:hover{color:var(--brand);}
.toast strong{font-size:13px;color:var(--success);}
.toast span{font-size:11.5px;color:var(--text-m);}

/* ══ ANIMATIONS ══ */
@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeSlideUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes alertIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}

@media(max-width:480px){
  #main{padding:14px 10px;}
  .pg-hdr h2{font-size:17px;}
  .btn-submit{width:100%;}
}
</style>
</head>
<body>

<canvas id="trail"></canvas>
<div id="overlay" onclick="closeSb()"></div>

<!-- ══ SIDEBAR ══ -->
<aside id="sb">
  <div class="sb-head">
    <div class="sb-ico"><svg viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/></svg></div>
    <div class="sb-title"><h3>Memon Biryani</h3><span>Enterprise CRM</span></div>
    <button class="sb-x" onclick="closeSb()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
  </div>
  <nav class="sb-nav">
    <p class="nl">Main</p>
    <a href="dashboard.php" class="ni"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a>
    <a href="insert_data.php" class="ni act"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Insert Data</a>
    <a href="records.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Records</a>
    <p class="nl">POS Modules</p>
    <div class="nt" onclick="tog('ps',this)"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>POS Modules<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub" id="ps">
      <a href="pos_screen.php" class="ni"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>POS Counter</a>
      <a href="sell_records.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>POS Reports</a>
      <a href="view_pos_entries.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>View Entries Log</a>
      <a href="cancel_order.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>Order Cancellation</a>
      <a href="pos_manage_products.php" class="ni"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Manage Items</a>
    </div>
    <p class="nl">Expenses</p>
    <div class="nt open" onclick="tog('es',this)"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Expense Module<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub on" id="es">
      <a href="add_expense.php" class="ni"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add New Expense</a>
      <a href="expense_records.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Expense History</a>
      <a href="expense_categories.php" class="ni"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>Category Setup</a>
    </div>
    <p class="nl">Settings</p>
    <div class="nt" onclick="tog('ss',this)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Settings<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub" id="ss">
      <a href="settings.php" class="ni"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>General Settings</a>
      <a href="manage_users.php" class="ni"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Staff Management</a>
      <a href="db_backup.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>Backup &amp; Restore</a>
    </div>
    <p class="nl">Account</p>
    <a href="logout.php" class="ni dng"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
  </nav>
  <div class="sb-foot">
    <div class="sb-usr">
      <div class="av"><?php echo $user_initials; ?></div>
      <div class="sb-usr-info"><p><?php echo $user_name; ?></p><span>Active Session</span></div>
    </div>
  </div>
</aside>

<!-- ══ NAVBAR ══ -->
<nav id="nb">
  <button class="nb-menu" onclick="openSb()"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
  <a href="dashboard.php" class="nb-logo">
    <div class="nb-logo-ic"><svg viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/></svg></div>
    <span class="nb-logo-txt">Memon Biryani</span>
  </a>
  <div class="nb-sp"></div>
  <span class="nb-dt" id="nbDate"></span>
  <div class="theme-sw">
    <button class="th-btn act" id="th-l" onclick="setTheme('light')" title="Light">☀️</button>
    <button class="th-btn" id="th-d" onclick="setTheme('dark')" title="Dark">🌙</button>
    <button class="th-btn" id="th-c" onclick="setTheme('custom')" title="Custom">🎨</button>
  </div>
  <button class="nb-bell" onclick="togBell(event)">
    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <?php if($notif_count>0): ?><span class="nb-bdg"><?php echo $notif_count; ?></span><?php endif; ?>
  </button>
  <div class="nb-usr" onclick="togUdd(event)">
    <div class="av"><?php echo $user_initials; ?></div>
    <span class="nb-un"><?php echo $user_name; ?></span>
    <div class="udd" id="udd">
      <div class="udd-h"><p><?php echo $user_name; ?></p><span>Active User</span></div>
      <a href="settings.php"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Settings</a>
      <a href="logout.php" class="lg"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
    </div>
  </div>
</nav>

<!-- Bell Modal -->
<div id="bm">
  <div class="bm-h"><h4>Notifications<?php if($notif_count>0) echo " ($notif_count)"; ?></h4><button class="bm-x" onclick="togBell(event)"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
  <div class="nm-list">
    <?php if(empty($notifications)): ?>
      <p class="nm-mt">No active notifications ✓</p>
    <?php else: foreach($notifications as $notif): ?>
      <div class="nm-it">
        <div class="nm-dot"></div>
        <a href="check.php?id=<?php echo $notif['id']; ?>">
          <strong>ID: <?php echo $notif['id']; ?> | <?php echo $notif['expense_date']; ?></strong><br>
          <?php echo htmlspecialchars($notif['message']); ?>
        </a>
        <form method="POST" style="margin:0;">
          <input type="hidden" name="clear_notif_id" value="<?php echo $notif['id']; ?>">
          <button type="submit" class="nm-clr">Clear</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ══ MAIN ══ -->
<main id="main">
  <div class="pg-hdr">
    <h2>Insert Daily Expenses</h2>
    <p>Record today's expense entries by category</p>
  </div>

  <!-- DATE CARD -->
  <div class="card">
    <div class="card-title">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Select Date
    </div>

    <!-- Date picker on top -->
    <div class="date-top">
      <label>Date</label>
      <input type="date" name="selected_date_preview" id="selected_date_preview" onchange="autoFillDateParts()">
    </div>

    <!-- Day / Month / Year boxes below -->
    <div class="date-parts">
      <div class="date-part-box">
        <div class="dp-label">Day</div>
        <div class="dp-val" id="show_day">—</div>
      </div>
      <div class="date-part-box">
        <div class="dp-label">Month</div>
        <div class="dp-val" id="show_month">—</div>
      </div>
      <div class="date-part-box">
        <div class="dp-label">Year</div>
        <div class="dp-val" id="show_year">—</div>
      </div>
    </div>
  </div>

  <!-- EXPENSES CARD -->
  <div class="card" style="animation-delay:.08s;">
    <div class="card-title">
      <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Category Expense Items
    </div>

    <?php if(!empty($msg) && $msg !== "SUCCESS_ALERT"): ?>
      <div class="alert alert-err">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php echo $msg; ?>
      </div>
    <?php endif; ?>

    <?php if(count($categories) > 0): ?>
      <form method="POST" id="expenseForm">
        <!-- Hidden actual date field submitted with form -->
        <input type="hidden" name="selected_date" id="selected_date">

        <div class="expense-grid" id="expenseGrid">
          <?php foreach($categories as $cat): ?>
            <div class="exp-card" id="card-<?php echo $cat['id']; ?>">
              <button type="button" class="exp-rm-btn" onclick="removeRow(<?php echo $cat['id']; ?>)" title="Remove">&times;</button>
              <div class="exp-card-name"><?php echo htmlspecialchars($cat['category_name']); ?></div>
              <input
                type="number"
                class="exp-card-input"
                name="amounts[<?php echo $cat['id']; ?>]"
                id="amt-<?php echo $cat['id']; ?>"
                placeholder="Rs. 0"
                step="0.01"
                min="0">
            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit" name="submit_expenses" class="btn-submit">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Submit Expenses
        </button>
      </form>
    <?php else: ?>
      <p style="font-size:13px;color:var(--text-m);">No categories found. Go to <a href="expense_categories.php" style="color:var(--brand);">Category Setup</a> first.</p>
    <?php endif; ?>
  </div>
</main>

<!-- ══ TOASTS ══ -->
<div class="toast-wrap">
  <?php if($msg === "SUCCESS_ALERT"): ?>
    <div class="toast success-toast" id="successToast">
      <form method="POST" style="margin:0;">
        <input type="hidden" name="clear_notif_id" value="<?php echo $alertId; ?>">
        <button type="submit" class="toast-close">&times;</button>
      </form>
      <a href="check.php?id=<?php echo $alertId; ?>">
        <strong>✓ Data Inserted!</strong><br>
        <span>ID: <?php echo $alertId; ?> | Date: <?php echo $alertDate; ?></span>
      </a>
    </div>
  <?php endif; ?>

  <?php foreach($notifications as $notif): ?>
    <?php if($notif['id'] != $alertId): ?>
      <div class="toast">
        <form method="POST" style="margin:0;">
          <input type="hidden" name="clear_notif_id" value="<?php echo $notif['id']; ?>">
          <button type="submit" class="toast-close">&times;</button>
        </form>
        <a href="check.php?id=<?php echo $notif['id']; ?>">
          <strong style="color:var(--brand);">Log Alert (ID: <?php echo $notif['id']; ?>)</strong><br>
          <span><?php echo htmlspecialchars($notif['message']); ?></span>
        </a>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<script>
/* MOUSE TRAIL */
var tc=document.getElementById('trail'),tx=tc.getContext('2d'),TW,TH,pts=[];
function rsz(){TW=tc.width=window.innerWidth;TH=tc.height=window.innerHeight;}
rsz();window.addEventListener('resize',rsz);
document.addEventListener('mousemove',function(e){
  for(var i=0;i<3;i++) pts.push({x:e.clientX+(Math.random()-.5)*16,y:e.clientY+(Math.random()-.5)*16,r:Math.random()*24+8,a:.16,vx:(Math.random()-.5)*.5,vy:(Math.random()-.5)*.5,c:Math.random()>.5?'120,69,12':'190,130,60'});
});
function animTr(){tx.clearRect(0,0,TW,TH);pts=pts.filter(function(p){return p.a>.003;});pts.forEach(function(p){tx.beginPath();var g=tx.createRadialGradient(p.x,p.y,0,p.x,p.y,p.r);g.addColorStop(0,'rgba('+p.c+','+p.a+')');g.addColorStop(1,'rgba('+p.c+',0)');tx.fillStyle=g;tx.arc(p.x,p.y,p.r,0,Math.PI*2);tx.fill();p.x+=p.vx;p.y+=p.vy;p.a*=.91;p.r*=.97;});requestAnimationFrame(animTr);}
animTr();

/* THEME */
function setTheme(t){document.documentElement.setAttribute('data-theme',t);localStorage.setItem('mbTheme',t);['l','d','c'].forEach(function(x){document.getElementById('th-'+x).classList.remove('act');});document.getElementById('th-'+{light:'l',dark:'d',custom:'c'}[t]).classList.add('act');}
(function(){setTheme(localStorage.getItem('mbTheme')||'light');})();

/* SIDEBAR */
function openSb(){document.getElementById('sb').classList.add('on');document.getElementById('overlay').classList.add('on');}
function closeSb(){document.getElementById('sb').classList.remove('on');document.getElementById('overlay').classList.remove('on');}
function tog(id,el){document.getElementById(id).classList.toggle('on');el.classList.toggle('open');}

/* BELL */
function togBell(e){e.stopPropagation();document.getElementById('bm').classList.toggle('on');}

/* USER DD */
function togUdd(e){e.stopPropagation();document.getElementById('udd').classList.toggle('on');}
document.addEventListener('click',function(e){
  if(!e.target.closest('.nb-usr'))document.getElementById('udd').classList.remove('on');
  if(!e.target.closest('.nb-bell')&&!e.target.closest('#bm'))document.getElementById('bm').classList.remove('on');
});

/* DATE */
document.getElementById('nbDate').textContent=new Date().toLocaleDateString('en-PK',{weekday:'short',year:'numeric',month:'short',day:'numeric'});

/* AUTO-FILL DATE PARTS — original logic preserved */
var days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
var months=['January','February','March','April','May','June','July','August','September','October','November','December'];

function autoFillDateParts(){
  var val=document.getElementById('selected_date_preview').value;
  if(!val) return;
  // sync hidden field for form submit
  document.getElementById('selected_date').value=val;
  var d=new Date(val+'T00:00:00');
  document.getElementById('show_day').textContent=days[d.getDay()];
  document.getElementById('show_month').textContent=months[d.getMonth()];
  document.getElementById('show_year').textContent=d.getFullYear();
}

/* REMOVE ROW — clear amount + fully hide */
function removeRow(id){
  var card=document.getElementById('card-'+id);
  var inp=document.getElementById('amt-'+id);
  if(inp) inp.value='';
  if(card){
    card.style.transition='opacity .18s, transform .18s';
    card.style.opacity='0';
    card.style.transform='scale(.95)';
    setTimeout(function(){ card.style.display='none'; }, 180);
  }
}

/* DEFAULT TODAY */
window.onload=function(){
  var today=new Date().toISOString().split('T')[0];
  document.getElementById('selected_date_preview').value=today;
  document.getElementById('selected_date').value=today;
  autoFillDateParts();
};
</script>
</body>
</html>