<?php
require_once 'auth_functions.php';
require_once 'expense_functions.php';
require_once 'db.php';
checkSession();

// include "toast_notification.php";

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_notif_id'])) {
    clearNotificationForUser($_POST['clear_notif_id'], $userId);
    header("Location: dashboard.php");
    exit();
}

$catFieldsStmt = $pdo->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
$all_categories = $catFieldsStmt->fetchAll();

$filter_category = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filter_type     = isset($_GET['filter_type'])     ? $_GET['filter_type']     : 'current_month';
$specific_date   = isset($_GET['specific_date'])   ? $_GET['specific_date']   : '';
$start_date      = isset($_GET['start_date'])      ? $_GET['start_date']      : '';
$end_date        = isset($_GET['end_date'])        ? $_GET['end_date']        : '';
$filter_day      = isset($_GET['filter_day'])      ? $_GET['filter_day']      : '';
$filter_month    = isset($_GET['filter_month'])    ? $_GET['filter_month']    : '';
$filter_year     = isset($_GET['filter_year'])     ? $_GET['filter_year']     : '';

$sql    = "SELECT c.id as cat_id, c.category_name, COALESCE(SUM(CASE WHEN 1=1 ";
$params = [];

if (!empty($filter_category)) { $sql .= "AND e.category_id = ? "; $params[] = $filter_category; }

if      ($filter_type==='specific_date'  && !empty($specific_date))               { $sql.="AND e.date = ? ";                                                         $params[]=$specific_date; }
elseif  ($filter_type==='date_to_date'   && !empty($start_date) && !empty($end_date)) { $sql.="AND e.date BETWEEN ? AND ? ";                                         $params[]=$start_date; $params[]=$end_date; }
elseif  ($filter_type==='specific_day'   && !empty($filter_day))                  { $sql.="AND (e.expense_day=? OR DATE_FORMAT(e.date,'%W')=?) ";                    $params[]=$filter_day;  $params[]=$filter_day; }
elseif  ($filter_type==='specific_month' && !empty($filter_month))                { $sql.="AND (e.expense_month=? OR DATE_FORMAT(e.date,'%M')=?) ";                  $params[]=$filter_month;$params[]=$filter_month; }
elseif  ($filter_type==='specific_year'  && !empty($filter_year))                 { $sql.="AND (e.expense_year=? OR DATE_FORMAT(e.date,'%Y')=?) ";                   $params[]=$filter_year; $params[]=$filter_year; }
elseif  ($filter_type==='current_month')                                           { $sql.="AND (e.expense_month=? OR DATE_FORMAT(e.date,'%M')=?) AND (e.expense_year=? OR DATE_FORMAT(e.date,'%Y')=?) "; $params[]=date('F');$params[]=date('F');$params[]=date('Y');$params[]=date('Y'); }

$sql .= "THEN e.amount ELSE 0 END),0) as total_amount FROM categories c LEFT JOIN expenses e ON c.id=e.category_id GROUP BY c.id,c.category_name";
if (!empty($filter_category)) { $sql .= " HAVING total_amount>-1 AND cat_id=? "; $params[]=$filter_category; }
$sql .= " ORDER BY c.category_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$report_data = $stmt->fetchAll();

$grand_total=$chart_labels=$chart_values=[];
$grand_total=0;
foreach($report_data as $row){
    $amt=(float)($row['total_amount']??0);
    $grand_total+=$amt;
    $chart_labels[]=$row['category_name'];
    $chart_values[]=$amt;
}

$notifications = getActiveNotificationsForUser($userId);
$notif_count   = count($notifications);
$user_name     = htmlspecialchars($_SESSION['user_name']??'Muhammad Hamza');
$user_initials = strtoupper(substr($user_name,0,1));

// Format amount - remove useless trailing zeros
function fmtAmt($n){
    if($n==0) return '0';
    if($n==floor($n)) return number_format($n,0);
    return number_format($n,2);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard – Memon Biryani</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ═══════════════════════════════════════
   CSS CUSTOM PROPERTIES – ALL 3 THEMES
═══════════════════════════════════════ */
:root,[data-theme="light"]{
  --brand:#78450C; --brand-d:#5c3308; --brand-g:rgba(120,69,12,.18);
  --bg:#f2ebe1; --bg2:#e8ddd0;
  --surface:rgba(255,255,255,.78); --surface-s:rgba(255,255,255,.92);
  --border:rgba(120,69,12,.14); --border-s:rgba(120,69,12,.22);
  --text:#18100a; --text-m:#8a7060; --text-i:#fff;
  --card-top:var(--brand); --total-top:#e74c3c; --total-bg:rgba(231,76,60,.06);
  --chart-bar:rgba(120,69,12,.15); --chart-border:#78450C;
  --danger:#d94040; --danger-bg:rgba(217,64,64,.08);
  --blur:20px;
}
[data-theme="dark"]{
  --brand:#c47a2a; --brand-d:#a05e18; --brand-g:rgba(196,122,42,.22);
  --bg:#0f0b08; --bg2:#1a1208;
  --surface:rgba(30,20,10,.82); --surface-s:rgba(40,28,14,.96);
  --border:rgba(196,122,42,.18); --border-s:rgba(196,122,42,.30);
  --text:#f0e6d8; --text-m:#9a8a78; --text-i:#0f0b08;
  --card-top:var(--brand); --total-top:#e05252; --total-bg:rgba(224,82,82,.08);
  --chart-bar:rgba(196,122,42,.22); --chart-border:#c47a2a;
  --danger:#e07070; --danger-bg:rgba(224,112,112,.10);
  --blur:18px;
}
[data-theme="custom"]{
  --brand:#1a6b5c; --brand-d:#145248; --brand-g:rgba(26,107,92,.20);
  --bg:#eef6f4; --bg2:#daeee9;
  --surface:rgba(255,255,255,.76); --surface-s:rgba(255,255,255,.93);
  --border:rgba(26,107,92,.15); --border-s:rgba(26,107,92,.25);
  --text:#0d2e28; --text-m:#4a7a70; --text-i:#fff;
  --card-top:var(--brand); --total-top:#e05a2b; --total-bg:rgba(224,90,43,.06);
  --chart-bar:rgba(26,107,92,.16); --chart-border:#1a6b5c;
  --danger:#c0392b; --danger-bg:rgba(192,57,43,.08);
  --blur:20px;
}

*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}

body{
  font-family:'Segoe UI',system-ui,sans-serif;
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  overflow-x:hidden;
  transition:background .35s,color .35s;
}

/* ── MOUSE TRAIL ── */
#trail{position:fixed;inset:0;pointer-events:none;z-index:0;}

/* ── OVERLAY ── */
#overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.35);backdrop-filter:blur(3px);
  z-index:990;transition:opacity .25s;
}
#overlay.on{display:block;}

/* ═══════════════ SIDEBAR ═══════════════ */
#sb{
  position:fixed;top:0;left:0;
  width:260px;height:100vh;
  background:var(--surface);
  backdrop-filter:blur(var(--blur));
  -webkit-backdrop-filter:blur(var(--blur));
  border-right:1px solid var(--border);
  box-shadow:4px 0 32px rgba(0,0,0,.10);
  z-index:1000;display:flex;flex-direction:column;
  transform:translateX(-100%);
  transition:transform .3s cubic-bezier(.4,0,.2,1);
}
#sb.on{transform:translateX(0);}

.sb-head{
  display:flex;align-items:center;gap:10px;
  padding:16px 14px;border-bottom:1px solid var(--border);flex-shrink:0;
}
.sb-ico{
  width:38px;height:38px;border-radius:10px;
  background:var(--brand);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 3px 10px var(--brand-g);flex-shrink:0;
}
.sb-ico svg{width:20px;height:20px;fill:#fff;}
.sb-title h3{font-size:13.5px;font-weight:700;color:var(--brand);line-height:1.2;}
.sb-title span{font-size:10px;color:var(--text-m);text-transform:uppercase;letter-spacing:.7px;}
.sb-x{
  margin-left:auto;width:28px;height:28px;border-radius:7px;
  background:none;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  color:var(--text-m);transition:background .15s,color .15s;
}
.sb-x:hover{background:var(--brand-g);color:var(--brand);}
.sb-x svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}

.sb-nav{flex:1;overflow-y:auto;padding:8px 6px;}
.sb-nav::-webkit-scrollbar{width:3px;}
.sb-nav::-webkit-scrollbar-thumb{background:var(--brand-g);border-radius:3px;}

.nl{font-size:10px;font-weight:700;color:var(--text-m);
  text-transform:uppercase;letter-spacing:.9px;padding:10px 10px 3px;}

.ni{
  display:flex;align-items:center;gap:9px;
  padding:8px 11px;border-radius:8px;
  text-decoration:none;color:var(--text);
  font-size:13px;font-weight:500;
  transition:background .14s,color .14s;margin-bottom:1px;
}
.ni:hover{background:var(--brand-g);color:var(--brand);}
.ni.act{background:var(--brand);color:#fff;box-shadow:0 2px 10px var(--brand-g);}
.ni.act svg{stroke:#fff!important;}
.ni svg{width:15px;height:15px;stroke:currentColor;fill:none;
  stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.ni.dng{color:var(--danger);}
.ni.dng svg{stroke:var(--danger)!important;}
.ni.dng:hover{background:var(--danger-bg);}

.nt{
  display:flex;align-items:center;gap:9px;
  padding:8px 11px;border-radius:8px;
  color:var(--text);font-size:13px;font-weight:500;
  cursor:pointer;user-select:none;margin-bottom:1px;
  transition:background .14s,color .14s;
}
.nt:hover{background:var(--brand-g);color:var(--brand);}
.nt svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.nt .cv{margin-left:auto;width:13px;height:13px;transition:transform .22s;}
.nt.open .cv{transform:rotate(180deg);}

.nsub{display:none;padding-left:24px;}
.nsub.on{display:block;}
.nsub .ni{font-size:12.5px;font-weight:400;padding:7px 11px;}

.sb-foot{
  padding:10px 6px;border-top:1px solid var(--border);flex-shrink:0;
}
.sb-usr{
  display:flex;align-items:center;gap:9px;
  padding:9px 11px;border-radius:8px;background:var(--brand-g);
}
.av{
  border-radius:50%;background:var(--brand);color:#fff;
  font-weight:700;display:flex;align-items:center;justify-content:center;
  flex-shrink:0;box-shadow:0 2px 8px var(--brand-g);
}
.sb-usr .av{width:32px;height:32px;font-size:12px;}
.sb-usr-info p{font-size:12.5px;font-weight:600;color:var(--text);line-height:1.2;}
.sb-usr-info span{font-size:11px;color:var(--text-m);}

/* ═══════════════ NAVBAR ═══════════════ */
#nb{
  position:fixed;top:0;left:0;right:0;height:60px;
  background:var(--surface);
  backdrop-filter:blur(var(--blur));
  -webkit-backdrop-filter:blur(var(--blur));
  border-bottom:1px solid var(--border);
  box-shadow:0 2px 16px rgba(0,0,0,.07);
  display:flex;align-items:center;padding:0 18px;gap:10px;
  z-index:900;transition:background .35s;
}
.nb-menu{
  width:36px;height:36px;border-radius:8px;
  border:1px solid var(--border);background:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  transition:background .15s,border-color .15s;
}
.nb-menu:hover{background:var(--brand-g);border-color:var(--brand);}
.nb-menu svg{width:17px;height:17px;stroke:var(--text);fill:none;stroke-width:2;stroke-linecap:round;}

.nb-logo{display:flex;align-items:center;gap:9px;text-decoration:none;}
.nb-logo-ic{
  width:30px;height:30px;border-radius:7px;background:var(--brand);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 8px var(--brand-g);
}
.nb-logo-ic svg{width:15px;height:15px;fill:#fff;}
.nb-logo-txt{font-size:14.5px;font-weight:700;color:var(--brand);}

.nb-sp{flex:1;}
.nb-dt{font-size:11.5px;color:var(--text-m);display:none;}
@media(min-width:640px){.nb-dt{display:block;}}

/* theme switcher */
.theme-sw{
  display:flex;align-items:center;gap:4px;
  background:var(--bg2);border-radius:8px;padding:3px;
  border:1px solid var(--border);
}
.th-btn{
  width:30px;height:28px;border-radius:6px;border:none;
  background:none;cursor:pointer;font-size:14px;
  display:flex;align-items:center;justify-content:center;
  transition:background .15s;color:var(--text-m);
}
.th-btn.act{background:var(--brand);color:#fff;}
.th-btn:hover:not(.act){background:var(--brand-g);}

/* bell */
.nb-bell{
  position:relative;width:36px;height:36px;border-radius:8px;
  border:1px solid var(--border);background:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:background .15s,border-color .15s;
}
.nb-bell:hover{background:var(--brand-g);border-color:var(--brand);}
.nb-bell svg{width:17px;height:17px;stroke:var(--text);fill:none;stroke-width:1.8;stroke-linecap:round;}
.nb-bdg{
  position:absolute;top:-4px;right:-4px;
  background:#e74c3c;color:#fff;font-size:10px;font-weight:700;
  min-width:17px;height:17px;border-radius:9px;padding:0 3px;
  display:flex;align-items:center;justify-content:center;
  border:2px solid var(--bg);
}

/* user area */
.nb-usr{
  display:flex;align-items:center;gap:7px;
  cursor:pointer;position:relative;
}
.nb-usr .av{width:32px;height:32px;font-size:12px;}
.nb-un{font-size:12.5px;font-weight:600;color:var(--text);display:none;}
@media(min-width:520px){.nb-un{display:block;}}

.udd{
  display:none;position:absolute;top:calc(100% + 10px);right:0;
  background:var(--surface-s);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border:1px solid var(--border-s);border-radius:11px;
  box-shadow:0 8px 28px rgba(0,0,0,.14);
  min-width:175px;z-index:9999;overflow:hidden;
}
.udd.on{display:block;}
.udd-h{padding:11px 13px;border-bottom:1px solid var(--border);background:var(--brand-g);}
.udd-h p{font-size:12.5px;font-weight:600;color:var(--brand);}
.udd-h span{font-size:11px;color:var(--text-m);}
.udd a{
  display:flex;align-items:center;gap:9px;padding:9px 13px;
  font-size:12.5px;color:var(--text);text-decoration:none;
  transition:background .14s;
}
.udd a:hover{background:var(--brand-g);color:var(--brand);}
.udd a.lg{color:var(--danger);}
.udd a.lg:hover{background:var(--danger-bg);}
.udd a svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* ═══════════════ BELL MODAL ═══════════════ */
#bm{
  display:none;position:fixed;
  top:68px;right:14px;
  background:var(--surface-s);
  backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border:1px solid var(--border-s);border-radius:12px;
  box-shadow:0 10px 36px rgba(0,0,0,.14);
  width:310px;z-index:9998;overflow:hidden;
}
#bm.on{display:block;}
.bm-h{
  padding:12px 15px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.bm-h h4{font-size:13.5px;font-weight:600;color:var(--text);}
.bm-x{background:none;border:none;cursor:pointer;color:var(--text-m);padding:2px;}
.bm-x svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;}
.nm-list{max-height:290px;overflow-y:auto;}
.nm-it{display:flex;align-items:flex-start;gap:9px;padding:11px 15px;border-bottom:1px solid var(--border);}
.nm-dot{width:7px;height:7px;border-radius:50%;background:var(--brand);margin-top:4px;flex-shrink:0;}
.nm-it p{font-size:12.5px;color:var(--text);flex:1;line-height:1.4;}
.nm-clr{
  background:none;border:1px solid var(--border);
  border-radius:5px;padding:2px 7px;font-size:11px;
  color:var(--text-m);cursor:pointer;flex-shrink:0;
  transition:background .14s;
}
.nm-clr:hover{background:var(--brand-g);color:var(--brand);}
.nm-mt{padding:22px 15px;text-align:center;font-size:12.5px;color:var(--text-m);}

/* ═══════════════ MAIN ═══════════════ */
#main{
  margin-top:60px;padding:24px 18px;
  max-width:1280px;margin-left:auto;margin-right:auto;
  position:relative;z-index:1;
}

.pg-title{margin-bottom:20px;}
.pg-title h2{font-size:20px;font-weight:700;color:var(--text);}
.pg-title p{font-size:12.5px;color:var(--text-m);margin-top:2px;}

/* ── FILTER ── */
.fp{
  background:var(--surface);
  backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));
  border:1px solid var(--border);border-radius:12px;
  padding:16px 18px;margin-bottom:22px;
  box-shadow:0 4px 20px rgba(0,0,0,.07);
  transition:background .35s;
}
.fp h3{font-size:13px;font-weight:600;color:var(--text);margin-bottom:12px;}
.fg{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;}
.fb{display:flex;flex-direction:column;gap:4px;}
.fb label{font-size:10.5px;font-weight:600;color:var(--text-m);text-transform:uppercase;letter-spacing:.3px;}
.fb input,.fb select{
  height:34px;padding:0 9px;
  border:1px solid var(--border);border-radius:7px;
  font-size:12.5px;color:var(--text);
  background:rgba(255,255,255,.45);
  backdrop-filter:blur(6px);outline:none;min-width:135px;
  font-family:inherit;transition:border-color .17s,box-shadow .17s,background .35s;
}
[data-theme="dark"] .fb input,
[data-theme="dark"] .fb select{background:rgba(255,255,255,.06);}
.fb input:focus,.fb select:focus{
  border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-g);
  background:var(--surface-s);
}
.bf{
  height:34px;padding:0 18px;background:var(--brand);color:#fff;
  border:none;border-radius:7px;cursor:pointer;font-size:12.5px;font-weight:600;
  font-family:inherit;box-shadow:0 2px 10px var(--brand-g);
  transition:background .17s,transform .1s;
}
.bf:hover{background:var(--brand-d);}
.bf:active{transform:scale(.97);}

/* ── KPI GRID ── */
.kpi-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
  gap:13px;margin-bottom:22px;
}

.kc{
  background:var(--surface);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border:1px solid var(--border);border-radius:12px;
  padding:16px 15px;
  box-shadow:0 3px 16px rgba(0,0,0,.07);
  border-left:4px solid var(--card-top);
  transition:transform .18s,box-shadow .18s,background .35s;
  display:flex;flex-direction:column;gap:6px;
}
.kc:hover{transform:translateY(-3px);box-shadow:0 8px 24px var(--brand-g);}
.kc.tot{border-left-color:var(--total-top);background:var(--total-bg);}
.kc .kc-lbl{font-size:10.5px;font-weight:600;color:var(--text-m);text-transform:uppercase;letter-spacing:.4px;}
.kc .kc-val{font-size:19px;font-weight:700;color:var(--text);}
.kc.tot .kc-val{color:var(--total-top);}
.kc .kc-unit{font-size:11px;color:var(--text-m);}

/* ── CHART ── */
.ch-box{
  background:var(--surface);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border:1px solid var(--border);border-radius:12px;
  padding:20px 18px;box-shadow:0 3px 16px rgba(0,0,0,.07);
  margin-bottom:22px;transition:background .35s;
}
.ch-box-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.ch-box-hdr h4{font-size:13.5px;font-weight:600;color:var(--text);}
.ch-tabs{display:flex;gap:4px;}
.ch-tab{
  padding:4px 10px;border-radius:6px;border:none;
  font-size:11.5px;font-weight:500;cursor:pointer;
  background:var(--bg2);color:var(--text-m);
  transition:background .14s,color .14s;
}
.ch-tab.act{background:var(--brand);color:#fff;}


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

/* ── RESPONSIVE ── */
@media(max-width:640px){
  #main{padding:16px 12px;}
  .kpi-grid{grid-template-columns:repeat(2,1fr);gap:10px;}
  .fg{gap:8px;}
  .fb input,.fb select{min-width:120px;}
  .ch-box-hdr{flex-direction:column;align-items:flex-start;gap:8px;}
}
@media(max-width:400px){
  .kpi-grid{grid-template-columns:1fr 1fr;}
  .kc .kc-val{font-size:16px;}
}
@media(min-width:1024px){
  .kpi-grid{grid-template-columns:repeat(auto-fill,minmax(175px,1fr));}
}
</style>
</head>
<body>

<canvas id="trail"></canvas>
<div id="overlay" onclick="closeSb()"></div>

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside id="sb">
  <div class="sb-head">
    <div class="sb-ico">
      <svg viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/></svg>
    </div>
    <div class="sb-title">
      <h3>Memon Biryani</h3>
      <span>Enterprise CRM</span>
    </div>
    <button class="sb-x" onclick="closeSb()">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>

  <nav class="sb-nav">
    <p class="nl">Main</p>
    <a href="dashboard.php" class="ni act">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard
    </a>
    <!-- <a href="insert_data.php" class="ni">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Insert Data
    </a>
    <a href="records.php" class="ni">
      <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Records
    </a> -->

    <p class="nl">POS Modules</p>
    <div class="nt" onclick="tog('ps',this)">
      <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>POS Modules
      <svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="nsub" id="ps">
      <a href="pos_screen.php" class="ni"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>POS Counter</a>
      <a href="sell_records.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>POS Reports</a>
      <a href="view_pos_entries.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>View Entries Log</a>
      <a href="cancel_order.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>Order Cancellation</a>
      <a href="pos_manage_products.php" class="ni"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Manage Items</a>
    </div>

    <p class="nl">Expenses</p>
    <div class="nt" onclick="tog('es',this)">
      <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Expense Module
      <svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="nsub" id="es">
      <a href="insert_data.php" class="ni"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add New Expense</a>
      <a href="records.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Expense History</a>
      <a href="settings.php" class="ni"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>Category Setup</a>
    </div>

    <p class="nl">Settings</p>
    <div class="nt" onclick="tog('ss',this)">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Settings
      <svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="nsub" id="ss">
      <a href="settings.php" class="ni"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>General Settings</a>
      <a href="manage_users.php" class="ni"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Staff Management</a>
      <a href="db_backup.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>Backup &amp; Restore</a>
    </div>

    <p class="nl">Account</p>
    <a href="logout.php" class="ni dng">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
    </a>
  </nav>

  <div class="sb-foot">
    <div class="sb-usr">
      <div class="av"><?php echo $user_initials; ?></div>
      <div class="sb-usr-info">
        <p><?php echo $user_name; ?></p>
        <span>Active Session</span>
      </div>
    </div>
  </div>
</aside>

<!-- ═══════════ NAVBAR ═══════════ -->
<nav id="nb">
  <button class="nb-menu" onclick="openSb()">
    <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>

  <a href="dashboard.php" class="nb-logo">
    <div class="nb-logo-ic">
      <svg viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 3.5 2 6.5 5 8.2V21h8v-1.8c3-1.7 5-4.7 5-8.2 0-5-4-9-9-9zm0 2c3.9 0 7 3.1 7 7 0 2.8-1.6 5.2-4 6.5V19H9v-1.5C6.6 16.2 5 13.8 5 11c0-3.9 3.1-7 7-7z"/></svg>
    </div>
    <span class="nb-logo-txt">Memon Biryani</span>
  </a>

  <div class="nb-sp"></div>
  <span class="nb-dt" id="nbDate"></span>

  <!-- Theme switcher -->
  <div class="theme-sw">
    <button class="th-btn act" id="th-l" onclick="setTheme('light')" title="Light">☀️</button>
    <button class="th-btn" id="th-d" onclick="setTheme('dark')" title="Dark">🌙</button>
    <button class="th-btn" id="th-c" onclick="setTheme('custom')" title="Custom">🎨</button>
  </div>

  <button class="nb-bell" onclick="togBell(event)">
    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <?php if($notif_count>0): ?><span class="nb-bdg" id="bellBdg"><?php echo $notif_count; ?></span><?php endif; ?>
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

<!-- ═══════════ BELL MODAL ═══════════ -->
<div id="bm">
  <div class="bm-h">
    <h4>Notifications <?php if($notif_count>0) echo "($notif_count)"; ?></h4>
    <button class="bm-x" onclick="togBell(event)">
      <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="nm-list">
    <?php if(empty($notifications)): ?>
      <p class="nm-mt">No active notifications ✓</p>
    <?php else: foreach($notifications as $n): ?>
      <div class="nm-it">
        <div class="nm-dot"></div>
        <p><?php echo htmlspecialchars($n['message']??$n['title']??'Notification'); ?></p>
        <form method="POST" style="margin:0">
          <input type="hidden" name="clear_notif_id" value="<?php echo $n['id']; ?>">
          <button type="submit" class="nm-clr">Clear</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ═══════════ MAIN ═══════════ -->
<main id="main">
  <div class="pg-title">
    <h2>Control Dashboard</h2>
    <p>Expense summary &amp; analytics — <?php echo date('F Y'); ?></p>
  </div>

  <!-- FILTER -->
  <div class="fp">
    <h3>Interconnected Search Filter</h3>
    <form method="GET" action="dashboard.php">
      <div class="fg">
        <div class="fb">
          <label>Category</label>
          <select name="filter_category">
            <option value="">All Categories</option>
            <?php foreach($all_categories as $c): ?>
              <option value="<?php echo $c['id']; ?>" <?php if($filter_category==$c['id']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($c['category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fb">
          <label>Date Filter</label>
          <select name="filter_type" id="ft" onchange="togFlt()">
            <option value="all" <?php if($filter_type=='all') echo 'selected'; ?>>All Time</option>
            <option value="current_month" <?php if($filter_type=='current_month') echo 'selected'; ?>>Current Month</option>
            <option value="specific_date" <?php if($filter_type=='specific_date') echo 'selected'; ?>>Specific Date</option>
            <option value="date_to_date" <?php if($filter_type=='date_to_date') echo 'selected'; ?>>Date Range</option>
            <option value="specific_day" <?php if($filter_type=='specific_day') echo 'selected'; ?>>Specific Day</option>
            <option value="specific_month" <?php if($filter_type=='specific_month') echo 'selected'; ?>>Specific Month</option>
            <option value="specific_year" <?php if($filter_type=='specific_year') echo 'selected'; ?>>Specific Year</option>
          </select>
        </div>
        <div class="fb f-sd" style="display:none"><label>Date</label><input type="date" name="specific_date" value="<?php echo $specific_date; ?>"></div>
        <div class="fb f-dr" style="display:none"><label>From</label><input type="date" name="start_date" value="<?php echo $start_date; ?>"></div>
        <div class="fb f-dr" style="display:none"><label>To</label><input type="date" name="end_date" value="<?php echo $end_date; ?>"></div>
        <div class="fb f-dy" style="display:none">
          <label>Day</label>
          <select name="filter_day">
            <option value="">Choose Day</option>
            <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
              <option value="<?php echo $d; ?>" <?php if($filter_day==$d) echo 'selected'; ?>><?php echo $d; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fb f-mo" style="display:none">
          <label>Month</label>
          <select name="filter_month">
            <option value="">Choose Month</option>
            <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
              <option value="<?php echo $m; ?>" <?php if($filter_month==$m) echo 'selected'; ?>><?php echo $m; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fb f-yr" style="display:none"><label>Year</label><input type="number" name="filter_year" placeholder="2026" value="<?php echo $filter_year; ?>"></div>
        <button type="submit" class="bf">Apply</button>
      </div>
    </form>
  </div>

  <!-- KPI CARDS -->
  <div class="kpi-grid">
    <div class="kc tot">
      <span class="kc-lbl">Grand Total</span>
      <span class="kc-val">Rs. <?php echo fmtAmt($grand_total); ?></span>
      <span class="kc-unit"><?php echo date('F Y'); ?></span>
    </div>
    <?php foreach($report_data as $row):
      $a=(float)($row['total_amount']??0); ?>
      <div class="kc">
        <span class="kc-lbl"><?php echo htmlspecialchars($row['category_name']); ?></span>
        <span class="kc-val">Rs. <?php echo fmtAmt($a); ?></span>
        <span class="kc-unit">Expense</span>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- CHART -->
  <div class="ch-box">
    <div class="ch-box-hdr">
      <h4>Expense Breakdown</h4>
      <div class="ch-tabs">
        <button class="ch-tab act" onclick="switchChart('bar',this)">Bar</button>
        <button class="ch-tab" onclick="switchChart('doughnut',this)">Donut</button>
        <button class="ch-tab" onclick="switchChart('line',this)">Line</button>
      </div>
    </div>
    <canvas id="kpiChart" style="max-height:320px;"></canvas>
  </div>
</main>


<?php

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

?>

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
/* ═══ MOUSE TRAIL ═══ */
var tc=document.getElementById('trail'),tx=tc.getContext('2d'),TW,TH,pts=[];
function rsz(){TW=tc.width=window.innerWidth;TH=tc.height=window.innerHeight;}
rsz();window.addEventListener('resize',rsz);
document.addEventListener('mousemove',function(e){
  for(var i=0;i<3;i++) pts.push({
    x:e.clientX+(Math.random()-.5)*16,y:e.clientY+(Math.random()-.5)*16,
    r:Math.random()*26+8,a:.16,
    vx:(Math.random()-.5)*.5,vy:(Math.random()-.5)*.5,
    c:Math.random()>.5?'120,69,12':'190,130,60'
  });
});
function animTr(){
  tx.clearRect(0,0,TW,TH);
  pts=pts.filter(p=>p.a>.003);
  pts.forEach(p=>{
    tx.beginPath();
    var g=tx.createRadialGradient(p.x,p.y,0,p.x,p.y,p.r);
    g.addColorStop(0,'rgba('+p.c+','+p.a+')');
    g.addColorStop(1,'rgba('+p.c+',0)');
    tx.fillStyle=g;tx.arc(p.x,p.y,p.r,0,Math.PI*2);tx.fill();
    p.x+=p.vx;p.y+=p.vy;p.a*=.91;p.r*=.97;
  });
  requestAnimationFrame(animTr);
}
animTr();

/* ═══ THEME ═══ */
function setTheme(t){
  document.documentElement.setAttribute('data-theme',t);
  localStorage.setItem('mbTheme',t);
  ['l','d','c'].forEach(function(x){document.getElementById('th-'+x).classList.remove('act');});
  var map={'light':'l','dark':'d','custom':'c'};
  document.getElementById('th-'+map[t]).classList.add('act');
  if(window._chart) updateChartColors();
}
(function(){
  var saved=localStorage.getItem('mbTheme')||'light';
  setTheme(saved);
})();

/* ═══ SIDEBAR ═══ */
function openSb(){document.getElementById('sb').classList.add('on');document.getElementById('overlay').classList.add('on');}
function closeSb(){document.getElementById('sb').classList.remove('on');document.getElementById('overlay').classList.remove('on');}

/* ═══ NAV GROUPS ═══ */
function tog(id,el){document.getElementById(id).classList.toggle('on');el.classList.toggle('open');}

/* ═══ BELL ═══ */
function togBell(e){e.stopPropagation();document.getElementById('bm').classList.toggle('on');}

/* ═══ USER DROPDOWN ═══ */
function togUdd(e){e.stopPropagation();document.getElementById('udd').classList.toggle('on');}
document.addEventListener('click',function(e){
  if(!e.target.closest('.nb-usr')) document.getElementById('udd').classList.remove('on');
  if(!e.target.closest('.nb-bell')&&!e.target.closest('#bm')) document.getElementById('bm').classList.remove('on');
});

/* ═══ FILTER TOGGLE ═══ */
function togFlt(){
  var t=document.getElementById('ft').value;
  document.querySelectorAll('.f-sd').forEach(function(el){el.style.display=t==='specific_date'?'flex':'none';});
  document.querySelectorAll('.f-dr').forEach(function(el){el.style.display=t==='date_to_date'?'flex':'none';});
  document.querySelectorAll('.f-dy').forEach(function(el){el.style.display=t==='specific_day'?'flex':'none';});
  document.querySelectorAll('.f-mo').forEach(function(el){el.style.display=t==='specific_month'?'flex':'none';});
  document.querySelectorAll('.f-yr').forEach(function(el){el.style.display=t==='specific_year'?'flex':'none';});
}

/* ═══ DATE ═══ */
document.getElementById('nbDate').textContent=new Date().toLocaleDateString('en-PK',{weekday:'short',year:'numeric',month:'short',day:'numeric'});

/* ═══ CHART ═══ */
var rawLabels=<?php echo json_encode($chart_labels); ?>;
var rawData=<?php echo json_encode($chart_values); ?>;

// Strip categories with 0 value for cleaner chart
var labels=[],data=[];
for(var i=0;i<rawLabels.length;i++){
  if(rawData[i]>0){labels.push(rawLabels[i]);data.push(rawData[i]);}
}

var palette=['#78450C','#c47a2a','#e0a060','#a05e18','#d4956a','#8b5a2b','#f0c090','#5c3308'];

function getBrandColor(){
  return getComputedStyle(document.documentElement).getPropertyValue('--brand').trim();
}

var chartCfg={
  type:'bar',
  data:{
    labels:labels,
    datasets:[{
      label:'Rs.',data:data,
      backgroundColor:palette.map(function(c){return c+'33';}),
      borderColor:palette,
      borderWidth:2,borderRadius:8,borderSkipped:false
    }]
  },
  options:{
    responsive:true,maintainAspectRatio:true,
    plugins:{
      legend:{display:false},
      tooltip:{callbacks:{label:function(c){return ' Rs. '+c.parsed.y.toLocaleString();}}}
    },
    scales:{
      y:{beginAtZero:true,grid:{color:'rgba(120,69,12,0.07)'},ticks:{color:'#8a7060',font:{size:11},callback:function(v){return v>=1000?'Rs.'+(v/1000).toFixed(0)+'k':'Rs.'+v;}}},
      x:{grid:{display:false},ticks:{color:'#8a7060',font:{size:11}}}
    }
  }
};

var ctx=document.getElementById('kpiChart').getContext('2d');
window._chart=new Chart(ctx,chartCfg);

function switchChart(type,btn){
  document.querySelectorAll('.ch-tab').forEach(function(b){b.classList.remove('act');});
  btn.classList.add('act');
  window._chart.destroy();
  chartCfg.type=type;
  if(type==='doughnut'||type==='pie'){
    chartCfg.options.scales={};
  } else {
    chartCfg.options.scales={
      y:{beginAtZero:true,grid:{color:'rgba(120,69,12,0.07)'},ticks:{color:'#8a7060',font:{size:11},callback:function(v){return v>=1000?'Rs.'+(v/1000).toFixed(0)+'k':'Rs.'+v;}}},
      x:{grid:{display:false},ticks:{color:'#8a7060',font:{size:11}}}
    };
  }
  window._chart=new Chart(document.getElementById('kpiChart').getContext('2d'),chartCfg);
}

window.onload=function(){togFlt();};
</script>
</body>
</html>