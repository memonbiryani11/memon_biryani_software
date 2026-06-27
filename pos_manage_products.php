<?php
session_start();
require_once 'auth_functions.php';
require_once 'db.php';
checkSession();

// 1. HANDLE CATEGORY ACTIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_category'])) {
    $cat_name = trim(htmlspecialchars($_POST['new_category_name']));
    if (!empty($cat_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pos_categories (category_name) VALUES (?)");
            $stmt->execute([$cat_name]);
        } catch (PDOException $e) {}
    }
    header("Location: pos_manage_products.php"); exit();
}

if (isset($_GET['delete_cat_id'])) {
    $pdo->prepare("DELETE FROM pos_categories WHERE id=?")->execute([intval($_GET['delete_cat_id'])]);
    header("Location: pos_manage_products.php"); exit();
}

// 2. HANDLE PRODUCT ACTIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_product'])) {
    $id       = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $name     = htmlspecialchars($_POST['product_name']);
    $category = htmlspecialchars($_POST['category_name']);
    $price    = floatval($_POST['price']);

    $image_name = $_POST['existing_image'] ?? '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $image_name = $target_dir . time() . "_" . basename($_FILES['product_image']['name']);
        move_uploaded_file($_FILES['product_image']['tmp_name'], $image_name);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE pos_products SET product_name=?,category_name=?,price=?,image_path=? WHERE id=?");
        $stmt->execute([$name,$category,$price,$image_name,$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pos_products (product_name,category_name,price,image_path) VALUES (?,?,?,?)");
        $stmt->execute([$name,$category,$price,$image_name]);
    }
    header("Location: pos_manage_products.php"); exit();
}

if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM pos_products WHERE id=?")->execute([intval($_GET['delete_id'])]);
    header("Location: pos_manage_products.php"); exit();
}

$edit_prod = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM pos_products WHERE id=?");
    $stmt->execute([intval($_GET['edit_id'])]);
    $edit_prod = $stmt->fetch();
}

$categories = $pdo->query("SELECT * FROM pos_categories ORDER BY category_name ASC")->fetchAll();
$products   = $pdo->query("SELECT * FROM pos_products ORDER BY id DESC")->fetchAll();

$user_name     = htmlspecialchars($_SESSION['user_name'] ?? 'Muhammad Hamza');
$user_initials = strtoupper(substr($user_name, 0, 1));
$notif_count   = 0; $notifications = [];
if (function_exists('getActiveNotificationsForUser')) {
    $notifications = getActiveNotificationsForUser($_SESSION['user_id']);
    $notif_count   = count($notifications);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>POS Items Manager – Memon Biryani</title>
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
  --blur:20px;--radius:12px;
}
[data-theme="dark"]{
  --brand:#c47a2a;--brand-d:#a05e18;--brand-g:rgba(196,122,42,.22);
  --bg:#0f0b08;--bg2:#1a1208;
  --surface:rgba(30,20,10,.86);--surface-s:rgba(40,28,14,.97);
  --border:rgba(196,122,42,.18);--border-s:rgba(196,122,42,.30);
  --text:#f0e6d8;--text-m:#9a8a78;
  --danger:#e07070;--danger-bg:rgba(224,112,112,.10);
  --blur:18px;--radius:12px;
}
[data-theme="custom"]{
  --brand:#1a6b5c;--brand-d:#145248;--brand-g:rgba(26,107,92,.20);
  --bg:#eef6f4;--bg2:#daeee9;
  --surface:rgba(255,255,255,.76);--surface-s:rgba(255,255,255,.97);
  --border:rgba(26,107,92,.15);--border-s:rgba(26,107,92,.25);
  --text:#0d2e28;--text-m:#4a7a70;
  --danger:#c0392b;--danger-bg:rgba(192,57,43,.08);
  --blur:20px;--radius:12px;
}

/* ══ BASE ══ */
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
.nm-clr{background:none;border:1px solid var(--border);border-radius:5px;padding:2px 7px;font-size:11px;color:var(--text-m);cursor:pointer;transition:background .14s;}
.nm-clr:hover{background:var(--brand-g);color:var(--brand);}
.nm-mt{padding:20px 15px;text-align:center;font-size:12.5px;color:var(--text-m);}

/* ══ MAIN LAYOUT ══ */
#main{margin-top:60px;padding:24px 18px;max-width:1350px;margin-left:auto;margin-right:auto;position:relative;z-index:1;}

.pg-hdr{margin-bottom:20px;animation:fadeSlideDown .4s ease both;}
.pg-hdr h2{font-size:20px;font-weight:700;color:var(--text);}
.pg-hdr p{font-size:12.5px;color:var(--text-m);margin-top:2px;}

/* 2-col grid: left sidebar + right content */
.grid-layout{display:grid;grid-template-columns:290px 1fr;gap:18px;align-items:start;}
@media(max-width:900px){.grid-layout{grid-template-columns:1fr;}}

/* ══ GLASS CARD ══ */
.card{
  background:var(--surface);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  border:1px solid var(--border);border-radius:var(--radius);
  padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.07);
  transition:background .35s;
  animation:fadeSlideUp .4s ease both;
}
.card+.card{margin-top:16px;animation-delay:.06s;}
.card-title{font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;}
.card-title svg{width:15px;height:15px;stroke:var(--brand);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* ══ FORM ELEMENTS ══ */
.field{margin-bottom:13px;}
.field label{display:block;font-size:11px;font-weight:700;color:var(--text-m);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px;}
.field input[type="text"],
.field input[type="number"],
.field select,
.field input[type="file"]{
  width:100%;height:36px;padding:0 11px;
  border:1px solid var(--border);border-radius:8px;
  font-size:13px;color:var(--text);
  background:rgba(255,255,255,.45);
  backdrop-filter:blur(6px);outline:none;font-family:inherit;
  transition:border-color .17s,box-shadow .17s,background .35s;
}
[data-theme="dark"] .field input,
[data-theme="dark"] .field select{background:rgba(255,255,255,.06);}
.field input:focus,.field select:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-g);background:var(--surface-s);}
.field input::placeholder{color:var(--text-m);}
.field input[type="file"]{height:auto;padding:7px 11px;cursor:pointer;}

.prod-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:600px){.prod-form-grid{grid-template-columns:1fr;}}

.btn-submit{
  width:100%;height:40px;background:var(--brand);color:#fff;
  border:none;border-radius:8px;cursor:pointer;
  font-size:13px;font-weight:700;font-family:inherit;
  display:flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 2px 10px var(--brand-g);
  transition:background .17s,transform .1s;margin-top:4px;
}
.btn-submit svg{width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.btn-submit:hover{background:var(--brand-d);}
.btn-submit:active{transform:scale(.97);}

/* ══ CATEGORY LIST ══ */
.cat-list{max-height:240px;overflow-y:auto;}
.cat-list::-webkit-scrollbar{width:3px;}
.cat-list::-webkit-scrollbar-thumb{background:var(--brand-g);border-radius:3px;}
.cat-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:8px 4px;border-bottom:1px solid var(--border);
  animation:fadeSlideUp .3s ease both;
}
.cat-row:last-child{border-bottom:none;}
.cat-row span{font-size:13px;font-weight:500;color:var(--text);}
.cat-del{
  width:26px;height:26px;border-radius:6px;background:none;
  border:1px solid var(--border);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  color:var(--danger);transition:background .13s;
}
.cat-del:hover{background:var(--danger-bg);border-color:var(--danger);}
.cat-del svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;}
.cat-empty{font-size:12.5px;color:var(--text-m);text-align:center;padding:16px 0;}

/* ══ PRODUCT TABLE ══ */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{border-bottom:2px solid var(--border);}
th{font-size:10.5px;font-weight:700;color:var(--text-m);text-transform:uppercase;letter-spacing:.4px;padding:9px 10px;text-align:left;}
td{padding:10px 10px;font-size:13px;color:var(--text);border-bottom:1px solid var(--border);vertical-align:middle;}
tbody tr{transition:background .14s;animation:fadeSlideUp .3s ease both;}
tbody tr:nth-child(1){animation-delay:.08s}tbody tr:nth-child(2){animation-delay:.11s}tbody tr:nth-child(3){animation-delay:.14s}tbody tr:nth-child(4){animation-delay:.17s}tbody tr:nth-child(5){animation-delay:.20s}
tbody tr:hover{background:var(--brand-g);}
tbody tr:last-child td{border-bottom:none;}

.prod-img{width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border);}
.cat-tag{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;background:var(--brand-g);color:var(--brand);font-size:11.5px;font-weight:700;}
.price-val{font-size:14px;font-weight:700;color:var(--brand);}

.action-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;border:1px solid var(--border);font-size:11.5px;font-weight:600;text-decoration:none;transition:background .14s,border-color .14s,color .14s;cursor:pointer;background:none;font-family:inherit;}
.action-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.edit-btn{color:var(--brand);}
.edit-btn:hover{background:var(--brand-g);border-color:var(--brand);}
.del-btn{color:var(--danger);}
.del-btn:hover{background:var(--danger-bg);border-color:var(--danger);}
.actions-cell{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;}

.empty-row td{text-align:center;padding:30px;color:var(--text-m);font-size:13px;}

/* ══ RIGHT CONTENT STACK ══ */
.right-col{display:flex;flex-direction:column;gap:16px;}
.right-col .card{animation-delay:.07s;}
.right-col .card:nth-child(2){animation-delay:.12s;}

@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeSlideUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:600px){
  #main{padding:14px 10px;}
  .pg-hdr h2{font-size:17px;}
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
    <a href="insert_data.php" class="ni"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Insert Data</a>
    <a href="records.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Records</a>
    <p class="nl">POS Modules</p>
    <div class="nt open" onclick="tog('ps',this)"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>POS Modules<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub on" id="ps">
      <a href="pos_screen.php" class="ni"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>POS Counter</a>
      <a href="sell_records.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>POS Reports</a>
      <a href="view_pos_entries.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>View Entries Log</a>
      <a href="cancel_order.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>Order Cancellation</a>
      <a href="pos_manage_products.php" class="ni act"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Manage Items</a>
    </div>
    <p class="nl">Expenses</p>
    <div class="nt" onclick="tog('es',this)"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Expense Module<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub" id="es">
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
    <?php if(empty($notifications)): ?><p class="nm-mt">No active notifications ✓</p>
    <?php else: foreach($notifications as $n): ?>
      <div class="nm-it"><div class="nm-dot"></div><p><?php echo htmlspecialchars($n['message']??$n['title']??'Notification'); ?></p><form method="POST" action="dashboard.php" style="margin:0"><input type="hidden" name="clear_notif_id" value="<?php echo $n['id']; ?>"><button type="submit" class="nm-clr">Clear</button></form></div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ══ MAIN ══ -->
<main id="main">
  <div class="pg-hdr">
    <h2>POS Items Manager</h2>
    <p>Manage product categories and menu items</p>
  </div>

  <div class="grid-layout">

    <!-- LEFT: Categories -->
    <div>
      <!-- Add Category -->
      <div class="card">
        <div class="card-title">
          <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
          Add Category
        </div>
        <form method="POST">
          <div class="field">
            <label>Category Name</label>
            <input type="text" name="new_category_name" placeholder="e.g. Cold Drinks, BBQ" required>
          </div>
          <button type="submit" name="save_category" class="btn-submit">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Category
          </button>
        </form>
      </div>

      <!-- Category List -->
      <div class="card">
        <div class="card-title">
          <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
          Active Categories
          <span style="margin-left:auto;background:var(--brand-g);color:var(--brand);font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;"><?php echo count($categories); ?></span>
        </div>
        <div class="cat-list">
          <?php if(count($categories)>0): foreach($categories as $cat): ?>
            <div class="cat-row">
              <span><?php echo htmlspecialchars($cat['category_name']); ?></span>
              <a href="pos_manage_products.php?delete_cat_id=<?php echo $cat['id']; ?>"
                 class="cat-del"
                 onclick="return confirm('Delete this category?')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </a>
            </div>
          <?php endforeach; else: ?>
            <p class="cat-empty">No categories added yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: Product form + table -->
    <div class="right-col">

      <!-- Add/Edit Product -->
      <div class="card">
        <div class="card-title">
          <svg viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
          <?php echo $edit_prod ? 'Edit Product Details' : 'Create New Product'; ?>
        </div>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="product_id" value="<?php echo $edit_prod['id'] ?? 0; ?>">
          <input type="hidden" name="existing_image" value="<?php echo $edit_prod['image_path'] ?? ''; ?>">

          <div class="prod-form-grid">
            <div>
              <div class="field">
                <label>Product Name</label>
                <input type="text" name="product_name" value="<?php echo htmlspecialchars($edit_prod['product_name'] ?? ''); ?>" placeholder="e.g. Chicken Biryani" required>
              </div>
              <div class="field">
                <label>Category</label>
                <select name="category_name" required>
                  <option value="">-- Choose Category --</option>
                  <?php foreach($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['category_name']); ?>"
                      <?php echo (isset($edit_prod['category_name']) && $edit_prod['category_name']==$cat['category_name']) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div>
              <div class="field">
                <label>Price (Rs.)</label>
                <input type="number" step="0.01" name="price" value="<?php echo $edit_prod['price'] ?? ''; ?>" placeholder="270" required>
              </div>
              <div class="field">
                <label>Product Image</label>
                <input type="file" name="product_image" accept="image/*">
                <?php if(!empty($edit_prod['image_path'])): ?>
                  <div style="margin-top:6px;display:flex;align-items:center;gap:8px;">
                    <img src="<?php echo htmlspecialchars($edit_prod['image_path']); ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                    <span style="font-size:11.5px;color:var(--text-m);">Current image</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <button type="submit" name="save_product" class="btn-submit">
            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php echo $edit_prod ? 'Update Product' : 'Publish Product'; ?>
          </button>

          <?php if($edit_prod): ?>
            <a href="pos_manage_products.php" style="display:block;text-align:center;margin-top:10px;font-size:12px;color:var(--text-m);text-decoration:none;">✕ Cancel Edit</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Product Table -->
      <div class="card">
        <div class="card-title">
          <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          Product Catalogue
          <span style="margin-left:auto;background:var(--brand-g);color:var(--brand);font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;"><?php echo count($products); ?></span>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th style="text-align:center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(count($products)>0): foreach($products as $p): ?>
                <tr>
                  <td><img src="<?php echo !empty($p['image_path']) ? htmlspecialchars($p['image_path']) : 'uploads/default.png'; ?>" class="prod-img" alt=""></td>
                  <td><strong><?php echo htmlspecialchars($p['product_name']); ?></strong></td>
                  <td><span class="cat-tag"><?php echo htmlspecialchars($p['category_name']); ?></span></td>
                  <td><span class="price-val">Rs. <?php echo number_format($p['price'],0); ?></span></td>
                  <td>
                    <div class="actions-cell">
                      <a href="pos_manage_products.php?edit_id=<?php echo $p['id']; ?>" class="action-btn edit-btn">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Edit
                      </a>
                      <a href="pos_manage_products.php?delete_id=<?php echo $p['id']; ?>" class="action-btn del-btn" onclick="return confirm('Delete this product?')">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>Delete
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr class="empty-row"><td colspan="5">No products found. Add one above!</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</main>

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
</script>
</body>
</html>