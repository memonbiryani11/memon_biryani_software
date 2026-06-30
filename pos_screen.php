<?php
session_start();
require_once 'auth_functions.php';
require_once 'db.php';
checkSession();

$categories = $pdo->query("SELECT DISTINCT category_name FROM pos_products ORDER BY category_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$products   = $pdo->query("SELECT * FROM pos_products ORDER BY product_name ASC")->fetchAll(PDO::FETCH_ASSOC);

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
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>POS Counter – Memon Biryani</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<style>
/* ══════════════════════ THEMES ══════════════════════ */
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

/* ══════════════════════ BASE ══════════════════════ */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html,body{
  height:100%;
  font-family:'Segoe UI',system-ui,sans-serif;
  background:var(--bg);color:var(--text);
  overflow:hidden;
  transition:background .35s,color .35s;
}
#trail{position:fixed;inset:0;pointer-events:none;z-index:0;}
#overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(3px);z-index:990;}
#overlay.on{display:block;}

/* ══════════════════════ SIDEBAR ══════════════════════ */
#sb{
  position:fixed;top:0;left:0;width:260px;height:100vh;
  background:var(--surface);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  border-right:1px solid var(--border);
  box-shadow:4px 0 32px rgba(0,0,0,.10);
  z-index:1100;display:flex;flex-direction:column;
  transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1);
}
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

/* ══════════════════════ NAVBAR ══════════════════════ */
#nb{
  position:fixed;top:0;left:0;right:0;height:56px;
  background:var(--surface);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  border-bottom:1px solid var(--border);box-shadow:0 2px 16px rgba(0,0,0,.07);
  display:flex;align-items:center;padding:0 14px;gap:10px;z-index:1000;
  transition:background .35s;
}
.nb-menu{width:34px;height:34px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,border-color .15s;}
.nb-menu:hover{background:var(--brand-g);border-color:var(--brand);}
.nb-menu svg{width:16px;height:16px;stroke:var(--text);fill:none;stroke-width:2;stroke-linecap:round;}
.nb-logo{display:flex;align-items:center;gap:8px;text-decoration:none;}
.nb-logo-ic{width:28px;height:28px;border-radius:7px;background:var(--brand);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px var(--brand-g);}
.nb-logo-ic svg{width:14px;height:14px;fill:#fff;}
.nb-logo-txt{font-size:14px;font-weight:700;color:var(--brand);}
.nb-sp{flex:1;}
.theme-sw{display:flex;align-items:center;gap:2px;background:var(--bg2);border-radius:7px;padding:2px;border:1px solid var(--border);}
.th-btn{width:28px;height:26px;border-radius:5px;border:none;background:none;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:background .15s;color:var(--text-m);}
.th-btn.act{background:var(--brand);color:#fff;}
.th-btn:hover:not(.act){background:var(--brand-g);}
.nb-bell{position:relative;width:34px;height:34px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s,border-color .15s;}
.nb-bell:hover{background:var(--brand-g);border-color:var(--brand);}
.nb-bell svg{width:16px;height:16px;stroke:var(--text);fill:none;stroke-width:1.8;stroke-linecap:round;}
.nb-bdg{position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;font-size:9px;font-weight:700;min-width:16px;height:16px;border-radius:8px;padding:0 3px;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);}
.nb-usr{display:flex;align-items:center;gap:6px;cursor:pointer;position:relative;}
.nb-usr .av{width:30px;height:30px;font-size:11px;}
.nb-un{font-size:12px;font-weight:600;color:var(--text);display:none;}
@media(min-width:480px){.nb-un{display:block;}}
.udd{display:none;position:absolute;top:calc(100% + 8px);right:0;background:var(--surface-s);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--border-s);border-radius:var(--radius);box-shadow:0 8px 28px rgba(0,0,0,.14);min-width:170px;z-index:9999;overflow:hidden;}
.udd.on{display:block;}
.udd-h{padding:10px 13px;border-bottom:1px solid var(--border);background:var(--brand-g);}
.udd-h p{font-size:12.5px;font-weight:600;color:var(--brand);}
.udd-h span{font-size:11px;color:var(--text-m);}
.udd a{display:flex;align-items:center;gap:9px;padding:9px 13px;font-size:12.5px;color:var(--text);text-decoration:none;transition:background .14s;}
.udd a:hover{background:var(--brand-g);color:var(--brand);}
.udd a.lg{color:var(--danger);}
.udd a.lg:hover{background:var(--danger-bg);}
.udd a svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
#bm{display:none;position:fixed;top:64px;right:12px;background:var(--surface-s);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--border-s);border-radius:var(--radius);box-shadow:0 10px 36px rgba(0,0,0,.14);width:300px;z-index:9998;overflow:hidden;}
#bm.on{display:block;}
.bm-h{padding:11px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.bm-h h4{font-size:13px;font-weight:600;color:var(--text);}
.bm-x{background:none;border:none;cursor:pointer;color:var(--text-m);padding:2px;}
.bm-x svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}
.nm-list{max-height:260px;overflow-y:auto;}
.nm-it{display:flex;align-items:flex-start;gap:9px;padding:10px 14px;border-bottom:1px solid var(--border);}
.nm-dot{width:7px;height:7px;border-radius:50%;background:var(--brand);margin-top:4px;flex-shrink:0;}
.nm-it p{font-size:12px;color:var(--text);flex:1;line-height:1.4;}
.nm-clr{background:none;border:1px solid var(--border);border-radius:5px;padding:2px 7px;font-size:11px;color:var(--text-m);cursor:pointer;transition:background .14s;}
.nm-clr:hover{background:var(--brand-g);color:var(--brand);}
.nm-mt{padding:18px 14px;text-align:center;font-size:12px;color:var(--text-m);}

/* ══════════════════════ POS LAYOUT ══════════════════════ */
#posWrap{
  position:fixed;
  top:56px;left:0;right:0;bottom:0;
  display:flex;
  overflow:hidden;
  z-index:1;
}

/* LEFT: PRODUCT AREA */
#prodArea{
  flex:1;
  display:flex;flex-direction:column;
  overflow:hidden;
  padding:14px;
  gap:12px;
}

/* CATEGORY PILLS */
.cat-row{
  display:flex;gap:8px;overflow-x:auto;
  padding-bottom:4px;flex-shrink:0;
}
.cat-row::-webkit-scrollbar{height:3px;}
.cat-row::-webkit-scrollbar-thumb{background:var(--brand-g);border-radius:3px;}
.cat-pill{
  white-space:nowrap;padding:7px 16px;
  border-radius:20px;border:1px solid var(--border);
  background:var(--surface);color:var(--text);
  font-size:12.5px;font-weight:600;cursor:pointer;
  backdrop-filter:blur(8px);
  transition:background .16s,color .16s,border-color .16s,transform .1s;
  flex-shrink:0;
}
.cat-pill:active{transform:scale(.95);}
.cat-pill.act{background:var(--brand);color:#fff;border-color:var(--brand);box-shadow:0 2px 10px var(--brand-g);}

/* PRODUCT GRID — phone:2, tablet:3, desktop:4 */
/* Grid: phone=2, tablet=3, desktop=6 */
.prod-grid{
  display:grid;
  grid-template-columns:repeat(2,1fr);
  row-gap:14px;
  column-gap:10px;
  overflow-y:auto;
  flex:1;
  align-items:start;   /* each card only as tall as its content */
  align-content:start;
  padding-bottom:80px; /* space for FAB button */
}
.prod-grid::-webkit-scrollbar{width:3px;}
.prod-grid::-webkit-scrollbar-thumb{background:var(--brand-g);border-radius:3px;}
@media(min-width:580px){
  .prod-grid{grid-template-columns:repeat(3,1fr);row-gap:16px;column-gap:12px;}
}
@media(min-width:1024px){
  .prod-grid{grid-template-columns:repeat(6,1fr);row-gap:18px;column-gap:14px;}
}

/* Card — content-fit height, no overflow:hidden so nothing clips */
.p-card{
  background:var(--surface);
  backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
  border:1px solid var(--border);border-radius:var(--radius);
  cursor:pointer;
  display:flex;flex-direction:column;
  overflow:hidden;        /* keep border-radius clean on image */
  transition:transform .18s,box-shadow .18s,border-color .18s;
  animation:fadeUp .3s ease both;
}
.p-card:hover{transform:translateY(-3px);box-shadow:0 6px 20px var(--brand-g);border-color:var(--brand);}
.p-card:active{transform:scale(.97);opacity:.9;}

/* Image — fixed height per breakpoint, never clips text below */
.p-card img{
  width:100%;
  height:80px;
  object-fit:cover;
  display:block;
  flex-shrink:0;
  background:var(--bg2);
  transition:transform .22s;
}
@media(min-width:580px){.p-card img{height:95px;}}
@media(min-width:1024px){.p-card img{height:110px;}}
.p-card:hover img{transform:scale(1.04);}

/* Info — padding around name + price */
.p-card .p-info{
  padding:7px 9px 9px;
  display:flex;flex-direction:column;gap:3px;
}
.p-card .p-name{
  font-size:12px;font-weight:600;
  color:var(--text);line-height:1.3;
}
.p-card .p-price{
  font-size:13px;font-weight:700;
  color:var(--brand);
}

/* ══════════════════════ CART PANEL ══════════════════════ */
/* Cart toggle button — always visible, bottom right */
#cartToggleBtn{
  position:fixed;
  bottom:20px;right:16px;
  width:54px;height:54px;
  border-radius:50%;
  background:var(--brand);color:#fff;
  border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 18px var(--brand-g);
  z-index:1200;
  transition:transform .15s,box-shadow .15s;
}
#cartToggleBtn:active{transform:scale(.92);}
#cartToggleBtn svg{width:22px;height:22px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
#cartBadge{
  position:absolute;top:-3px;right:-3px;
  background:#e74c3c;color:#fff;
  font-size:10px;font-weight:700;
  min-width:18px;height:18px;border-radius:9px;padding:0 4px;
  display:none;align-items:center;justify-content:center;
  border:2px solid var(--brand);
}
#cartBadge.show{display:flex;}

/* Cart Slide Panel */
#cartPanel{
  position:fixed;
  top:56px;right:0;bottom:0;
  width:min(340px, 100vw);
  background:var(--surface-s);
  backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);
  border-left:1px solid var(--border-s);
  box-shadow:-6px 0 32px rgba(0,0,0,.12);
  z-index:1099;
  display:flex;flex-direction:column;
  transform:translateX(100%);
  transition:transform .3s cubic-bezier(.4,0,.2,1);
}
#cartPanel.on{transform:translateX(0);}

.cart-hdr{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 16px;
  border-bottom:1px solid var(--border);
  flex-shrink:0;
}
.cart-hdr-left{display:flex;align-items:center;gap:8px;}
.cart-hdr-left svg{width:18px;height:18px;stroke:var(--brand);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.cart-hdr-left h3{font-size:14.5px;font-weight:700;color:var(--text);}
.cart-hdr-left span{font-size:11px;color:var(--text-m);font-weight:500;}
.cart-close-btn{width:28px;height:28px;border-radius:7px;background:var(--brand-g);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-m);transition:background .14s,color .14s;}
.cart-close-btn:hover{background:var(--brand);color:#fff;}
.cart-close-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}

/* Cart items */
#cartItems{flex:1;overflow-y:auto;padding:10px 14px;}
#cartItems::-webkit-scrollbar{width:3px;}
#cartItems::-webkit-scrollbar-thumb{background:var(--brand-g);border-radius:3px;}

.cart-empty{
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;height:100%;gap:10px;
  color:var(--text-m);font-size:13px;
}
.cart-empty svg{width:40px;height:40px;stroke:var(--border-s);fill:none;stroke-width:1.5;stroke-linecap:round;}

.ci{
  display:flex;align-items:center;gap:10px;
  padding:10px 0;
  border-bottom:1px solid var(--border);
  animation:fadeUp .2s ease both;
}
.ci:last-child{border-bottom:none;}
.ci-info{flex:1;min-width:0;}
.ci-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ci-price{font-size:11px;color:var(--text-m);margin-top:2px;}

.qty-ctrl{
  display:flex;align-items:center;gap:0;
  border:1px solid var(--border);border-radius:8px;overflow:hidden;
  flex-shrink:0;
}
.qty-btn{
  width:28px;height:28px;border:none;background:none;
  cursor:pointer;font-size:16px;font-weight:700;
  color:var(--brand);
  display:flex;align-items:center;justify-content:center;
  transition:background .12s;
}
.qty-btn:hover{background:var(--brand-g);}
.qty-num{
  width:28px;text-align:center;
  font-size:13px;font-weight:700;color:var(--text);
  border-left:1px solid var(--border);border-right:1px solid var(--border);
  line-height:28px;
}

.ci-total{font-size:13px;font-weight:700;color:var(--text);min-width:52px;text-align:right;flex-shrink:0;}
.ci-del{width:26px;height:26px;border-radius:6px;background:none;border:1px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--danger);transition:background .12s;flex-shrink:0;}
.ci-del:hover{background:var(--danger-bg);border-color:var(--danger);}
.ci-del svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;}

/* Cart footer */
.cart-footer{
  padding:14px 16px;
  border-top:1px solid var(--border);
  flex-shrink:0;
}
.summary-row{
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:6px;font-size:12.5px;color:var(--text-m);
}
.summary-row span:last-child{font-weight:600;color:var(--text);}
.total-row{
  display:flex;justify-content:space-between;align-items:center;
  padding:10px 0;
  border-top:1px solid var(--border);
  margin-bottom:12px;
}
.total-row span:first-child{font-size:13px;font-weight:600;color:var(--text-m);}
.total-row .total-val{font-size:20px;font-weight:800;color:var(--brand);}

.btn-checkout{
  width:100%;height:46px;
  background:var(--brand);color:#fff;
  border:none;border-radius:10px;cursor:pointer;
  font-size:14px;font-weight:700;font-family:inherit;
  display:flex;align-items:center;justify-content:center;gap:9px;
  box-shadow:0 3px 14px var(--brand-g);
  transition:background .17s,transform .1s;
}
.btn-checkout svg{width:17px;height:17px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.btn-checkout:hover{background:var(--brand-d);}
.btn-checkout:active{transform:scale(.97);}
.btn-checkout:disabled{opacity:.55;cursor:not-allowed;}

/* success flash */
.flash-ok{
  position:fixed;top:70px;left:50%;transform:translateX(-50%) translateY(-10px);
  background:var(--brand);color:#fff;
  padding:10px 22px;border-radius:30px;
  font-size:13px;font-weight:600;
  box-shadow:0 4px 16px var(--brand-g);
  z-index:9999;opacity:0;pointer-events:none;
  transition:opacity .25s,transform .25s;
}
.flash-ok.show{opacity:1;transform:translateX(-50%) translateY(0);}

/* ══════════════════════ ANIMATIONS ══════════════════════ */
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ══════════════════════ RESPONSIVE ══════════════════════ */
@media(min-width:768px){
  #cartToggleBtn{bottom:24px;right:20px;}
}
@media(max-width:480px){
  #prodArea{padding:10px;}
  .cat-pill{padding:6px 13px;font-size:12px;}
}
</style>
</head>
<body>

<canvas id="trail"></canvas>
<div id="overlay" onclick="closeSb()"></div>

<!-- ══════════ SIDEBAR ══════════ -->
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
      <a href="pos_screen.php" class="ni act"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>POS Counter</a>
      <a href="sell_records.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>POS Reports</a>
      <a href="view_pos_entries.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>View Entries Log</a>
      <a href="cancel_order.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>Order Cancellation</a>
      <a href="pos_manage_products.php" class="ni"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Manage Items</a>
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

<!-- ══════════ NAVBAR ══════════ -->
<nav id="nb">
  <button class="nb-menu" onclick="openSb()"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
  <a href="dashboard.php" class="nb-logo">
    <div class="nb-logo-ic"><svg viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/></svg></div>
    <span class="nb-logo-txt">Memon Biryani</span>
  </a>
  <div class="nb-sp"></div>
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
      <div class="udd-h"><p><?php echo $user_name; ?></p><span>POS Cashier</span></div>
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
    <?php else: foreach($notifications as $n): ?>
      <div class="nm-it"><div class="nm-dot"></div><p><?php echo htmlspecialchars($n['message']??$n['title']??''); ?></p><form method="POST" action="dashboard.php" style="margin:0"><input type="hidden" name="clear_notif_id" value="<?php echo $n['id']; ?>"><button type="submit" class="nm-clr">Clear</button></form></div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- success flash -->
<div class="flash-ok" id="flashOk">✓ Invoice saved successfully!</div>

<!-- ══════════ POS WRAPPER ══════════ -->
<div id="posWrap">

  <!-- LEFT: Products -->
  <div id="prodArea">
    <!-- Category Pills -->
    <!-- <div class="cat-row">
      <button class="cat-pill act" onclick="filterCat('all',this)">All Items</button>
      <?php foreach($categories as $cat): ?>
        <button class="cat-pill" onclick="filterCat('<?php echo htmlspecialchars($cat,ENT_QUOTES); ?>',this)"><?php echo htmlspecialchars($cat); ?></button>
      <?php endforeach; ?>
    </div> -->

    <!-- Product Grid -->
    <div class="prod-grid" id="prodGrid">
      <?php foreach($products as $p):
        $img = !empty($p['image_path']) ? $p['image_path'] : 'uploads/default.png';
      ?>
        <div class="p-card" style="position:relative;"
          data-cat="<?php echo htmlspecialchars($p['category_name'],ENT_QUOTES); ?>"
          onclick="addToCart('<?php echo htmlspecialchars($p['product_name'],ENT_QUOTES); ?>',<?php echo (float)$p['price']; ?>)">
          <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['product_name']); ?>" loading="lazy">
          <div class="p-info">
            <div class="p-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
            <div class="p-price">Rs. <?php echo number_format($p['price'],0); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- CART PANEL (slide-in) -->
  <div id="cartPanel">
    <div class="cart-hdr">
      <div class="cart-hdr-left">
        <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        <h3>Cart</h3>
        <span id="hdrCount">0 items</span>
      </div>
      <button class="cart-close-btn" onclick="closeCart()">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div id="cartItems">
      <div class="cart-empty">
        <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Cart is empty
      </div>
    </div>

    <div class="cart-footer">
      <div class="summary-row"><span>Items:</span><span id="footItems">0</span></div>
      <div class="summary-row"><span>Subtotal:</span><span id="footSub">Rs. 0</span></div>
      <div class="total-row">
        <span>Total</span>
        <span class="total-val" id="footTotal">Rs. 0</span>
      </div>
      <button class="btn-checkout" id="checkoutBtn" onclick="processCheckoutInvoice()" disabled>
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Clear Amount &amp; Save
      </button>
    </div>
  </div>

</div><!-- /posWrap -->

<!-- Cart FAB -->
<button id="cartToggleBtn" onclick="toggleCart()">
  <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
  <span id="cartBadge" class="nb-bdg"></span>
</button>

<script>
/* ══ MOUSE TRAIL ══ */
var tc=document.getElementById('trail'),tx=tc.getContext('2d'),TW,TH,pts=[];
function rsz(){TW=tc.width=window.innerWidth;TH=tc.height=window.innerHeight;}
rsz();window.addEventListener('resize',rsz);
document.addEventListener('mousemove',function(e){
  for(var i=0;i<3;i++) pts.push({x:e.clientX+(Math.random()-.5)*16,y:e.clientY+(Math.random()-.5)*16,r:Math.random()*22+7,a:.15,vx:(Math.random()-.5)*.5,vy:(Math.random()-.5)*.5,c:Math.random()>.5?'120,69,12':'190,130,60'});
});
function animTr(){tx.clearRect(0,0,TW,TH);pts=pts.filter(function(p){return p.a>.003;});pts.forEach(function(p){tx.beginPath();var g=tx.createRadialGradient(p.x,p.y,0,p.x,p.y,p.r);g.addColorStop(0,'rgba('+p.c+','+p.a+')');g.addColorStop(1,'rgba('+p.c+',0)');tx.fillStyle=g;tx.arc(p.x,p.y,p.r,0,Math.PI*2);tx.fill();p.x+=p.vx;p.y+=p.vy;p.a*=.91;p.r*=.97;});requestAnimationFrame(animTr);}
animTr();

/* ══ THEME ══ */
function setTheme(t){document.documentElement.setAttribute('data-theme',t);localStorage.setItem('mbTheme',t);['l','d','c'].forEach(function(x){document.getElementById('th-'+x).classList.remove('act');});document.getElementById('th-'+{light:'l',dark:'d',custom:'c'}[t]).classList.add('act');}
(function(){setTheme(localStorage.getItem('mbTheme')||'light');})();

/* ══ SIDEBAR ══ */
function openSb(){document.getElementById('sb').classList.add('on');document.getElementById('overlay').classList.add('on');}
function closeSb(){document.getElementById('sb').classList.remove('on');document.getElementById('overlay').classList.remove('on');}
function tog(id,el){document.getElementById(id).classList.toggle('on');el.classList.toggle('open');}

/* ══ BELL ══ */
function togBell(e){e.stopPropagation();document.getElementById('bm').classList.toggle('on');}

/* ══ USER DD ══ */
function togUdd(e){e.stopPropagation();document.getElementById('udd').classList.toggle('on');}
document.addEventListener('click',function(e){
  if(!e.target.closest('.nb-usr'))document.getElementById('udd').classList.remove('on');
  if(!e.target.closest('.nb-bell')&&!e.target.closest('#bm'))document.getElementById('bm').classList.remove('on');
});

/* ══ CATEGORY FILTER ══ */
function filterCat(cat, btn){
  document.querySelectorAll('.cat-pill').forEach(function(b){b.classList.remove('act');});
  btn.classList.add('act');
  document.querySelectorAll('.p-card').forEach(function(c){
    c.style.display=(cat==='all'||c.dataset.cat===cat)?'flex':'none';
  });
}

/* ══ CART STATE ══ */
var cart={};

function addToCart(name, price){
  if(cart[name]) cart[name].qty+=1;
  else cart[name]={price:price,qty:1};
  renderCart();
}
function removeFromCart(name){delete cart[name];renderCart();}
function changeQty(name,delta){
  if(!cart[name]) return;
  cart[name].qty+=delta;
  if(cart[name].qty<=0){delete cart[name];}
  renderCart();
}

function renderCart(){
  var container=document.getElementById('cartItems');
  var keys=Object.keys(cart);
  var totalItems=0,totalAmt=0;

  if(keys.length===0){
    container.innerHTML='<div class="cart-empty"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>Cart is empty</div>';
  } else {
    var html='';
    keys.forEach(function(name){
      var it=cart[name];
      var lineTotal=it.price*it.qty;
      totalItems+=it.qty; totalAmt+=lineTotal;
      html+='<div class="ci">'
        +'<div class="ci-info"><div class="ci-name">'+esc(name)+'</div><div class="ci-price">Rs. '+it.price.toLocaleString()+' each</div></div>'
        +'<div class="qty-ctrl">'
          +'<button class="qty-btn" onclick="changeQty(\''+esc(name)+'\',-1)">−</button>'
          +'<div class="qty-num">'+it.qty+'</div>'
          +'<button class="qty-btn" onclick="changeQty(\''+esc(name)+'\',1)">+</button>'
        +'</div>'
        +'<div class="ci-total">Rs. '+lineTotal.toLocaleString()+'</div>'
        +'<button class="ci-del" onclick="removeFromCart(\''+esc(name)+'\')"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>'
      +'</div>';
    });
    container.innerHTML=html;
  }

  // Update header count
  document.getElementById('hdrCount').textContent=totalItems+' item'+(totalItems!==1?'s':'');

  // Update footer
  document.getElementById('footItems').textContent=totalItems;
  document.getElementById('footSub').textContent='Rs. '+totalAmt.toLocaleString();
  document.getElementById('footTotal').textContent='Rs. '+totalAmt.toLocaleString();

  // FAB badge
  var badge=document.getElementById('cartBadge');
  if(totalItems>0){badge.textContent=totalItems;badge.classList.add('show');}
  else{badge.classList.remove('show');}

  // Checkout btn
  document.getElementById('checkoutBtn').disabled=(keys.length===0);
}

function esc(str){return str.replace(/'/g,"\\'").replace(/"/g,'&quot;');}

/* ══ CART OPEN/CLOSE ══ */
function toggleCart(){
  var p=document.getElementById('cartPanel');
  p.classList.toggle('on');
}
function closeCart(){document.getElementById('cartPanel').classList.remove('on');}

/* ══ CHECKOUT — original logic preserved ══ */
function processCheckoutInvoice(){
  if(Object.keys(cart).length===0){alert("Cart is empty!");return;}
  fetch('pos_process_checkout.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(cart)
  })
  .then(function(r){return r.json();})
  .then(function(d){
    if(d.status==='success'){
      cart={};renderCart();closeCart();
      var f=document.getElementById('flashOk');
      f.classList.add('show');
      setTimeout(function(){f.classList.remove('show');},2400);
    } else {
      alert("Error saving transaction data logs.");
    }
  })
  .catch(function(){alert("Network error. Please try again.");});
}

renderCart();
</script>
</body>
</html>