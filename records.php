<?php
require_once 'auth_functions.php';
require_once 'expense_functions.php';
require_once 'db.php';
checkSession();

$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

$msg = "";
$msg_type = "ok"; // ok | err | warn

// 1. SINGLE DELETE
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        if ($stmt->execute([$delete_id])) { $msg = "Record deleted successfully!"; $msg_type = "ok"; }
        else { $msg = "Error: Failed to delete the record."; $msg_type = "err"; }
    } catch (Exception $e) { $msg = "Error: " . $e->getMessage(); $msg_type = "err"; }
}

// 2. BULK DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete_submit'])) {
    $ids_to_delete = $_POST['selected_records'] ?? [];
    if (!empty($ids_to_delete)) {
        try {
            $sanitized_ids = array_map('intval', $ids_to_delete);
            $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id IN ($placeholders)");
            if ($stmt->execute($sanitized_ids)) { $msg = count($sanitized_ids) . " records deleted successfully!"; $msg_type = "ok"; }
            else { $msg = "Error: Failed to delete selected records."; $msg_type = "err"; }
        } catch (Exception $e) { $msg = "Error: " . $e->getMessage(); $msg_type = "err"; }
    } else { $msg = "No records were selected for deletion."; $msg_type = "warn"; }
}

// 3. INLINE UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expense_amount'])) {
    $expense_id = intval($_POST['expense_id']);
    $new_amount = floatval($_POST['amount']);
    $stmt = $pdo->prepare("UPDATE expenses SET amount = ? WHERE id = ?");
    if ($stmt->execute([$new_amount, $expense_id])) { $msg = "Amount updated successfully!"; $msg_type = "ok"; }
    else { $msg = "Error: Failed to update the amount."; $msg_type = "err"; }
}

// 4. CATEGORIES FOR FILTER
$catStmt = $pdo->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
$all_categories = $catStmt->fetchAll();

// 5. FILTER PARAMS
$filter_category = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filter_type     = isset($_GET['filter_type'])     ? $_GET['filter_type']     : 'all_records';
$specific_date   = isset($_GET['specific_date'])   ? $_GET['specific_date']   : '';
$start_date      = isset($_GET['start_date'])      ? $_GET['start_date']      : '';
$end_date        = isset($_GET['end_date'])        ? $_GET['end_date']        : '';
$filter_day      = isset($_GET['filter_day'])      ? $_GET['filter_day']      : '';
$filter_month    = isset($_GET['filter_month'])    ? $_GET['filter_month']    : '';
$filter_year     = isset($_GET['filter_year'])     ? $_GET['filter_year']     : '';

// 6. DYNAMIC QUERY
$sql = "SELECT e.id AS expense_row_id,
               COALESCE(c.category_name,'Uncategorized') AS category_name,
               e.amount, e.date, e.expense_day, e.expense_month, e.expense_year
        FROM expenses e LEFT JOIN categories c ON e.category_id = c.id WHERE 1=1";
$params = [];

if (!empty($filter_category)) { $sql .= " AND e.category_id = ?"; $params[] = $filter_category; }

if      ($filter_type === 'specific_date'  && !empty($specific_date))               { $sql .= " AND e.date = ?"; $params[] = $specific_date; }
elseif  ($filter_type === 'date_to_date'   && !empty($start_date) && !empty($end_date)) { $sql .= " AND e.date BETWEEN ? AND ?"; $params[] = $start_date; $params[] = $end_date; }
elseif  ($filter_type === 'specific_day'   && !empty($filter_day))                  { $sql .= " AND (e.expense_day COLLATE utf8mb4_general_ci = ? OR DATE_FORMAT(e.date,'%W') COLLATE utf8mb4_general_ci = ?)"; $params[] = $filter_day; $params[] = $filter_day; }
elseif  ($filter_type === 'specific_month' && !empty($filter_month))                { $sql .= " AND (e.expense_month COLLATE utf8mb4_general_ci = ? OR DATE_FORMAT(e.date,'%M') COLLATE utf8mb4_general_ci = ?)"; $params[] = $filter_month; $params[] = $filter_month; }
elseif  ($filter_type === 'specific_year'  && !empty($filter_year))                 { $sql .= " AND (e.expense_year COLLATE utf8mb4_general_ci = ? OR DATE_FORMAT(e.date,'%Y') COLLATE utf8mb4_general_ci = ?)"; $params[] = $filter_year; $params[] = $filter_year; }
elseif  ($filter_type === 'current_month')                                           { $sql .= " AND (e.expense_month COLLATE utf8mb4_general_ci = ? OR DATE_FORMAT(e.date,'%M') COLLATE utf8mb4_general_ci = ?) AND (e.expense_year COLLATE utf8mb4_general_ci = ? OR DATE_FORMAT(e.date,'%Y') COLLATE utf8mb4_general_ci = ?)"; $params[] = date('F'); $params[] = date('F'); $params[] = date('Y'); $params[] = date('Y'); }

$sql .= " ORDER BY e.date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$filtered_total = 0;
foreach($records as $r) { $filtered_total += (float)$r['amount']; }

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
<title>Expense Records – Memon Biryani</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<style>
:root,[data-theme="light"]{
  --brand:#78450C;--brand-d:#5c3308;--brand-g:rgba(120,69,12,.18);
  --bg:#f2ebe1;--bg2:#e8ddd0;
  --surface:rgba(255,255,255,.78);--surface-s:rgba(255,255,255,.96);
  --border:rgba(120,69,12,.13);--border-s:rgba(120,69,12,.22);
  --text:#18100a;--text-m:#8a7060;
  --danger:#d94040;--danger-bg:rgba(217,64,64,.08);
  --success:#1a7a3f;--success-bg:rgba(26,122,63,.08);
  --warn:#b45309;--warn-bg:rgba(180,83,9,.08);
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
  --warn:#f59e0b;--warn-bg:rgba(245,158,11,.10);
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
  --warn:#b45309;--warn-bg:rgba(180,83,9,.08);
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
.nm-clr{background:none;border:1px solid var(--border);border-radius:5px;padding:2px 7px;font-size:11px;color:var(--text-m);cursor:pointer;transition:background .14s;}
.nm-clr:hover{background:var(--brand-g);color:var(--brand);}
.nm-mt{padding:20px 15px;text-align:center;font-size:12.5px;color:var(--text-m);}

/* ══ MAIN ══ */
#main{margin-top:60px;padding:24px 18px;max-width:1100px;margin-left:auto;margin-right:auto;position:relative;z-index:1;}

.pg-hdr{margin-bottom:20px;animation:fadeSlideDown .4s ease both;}
.pg-hdr h2{font-size:20px;font-weight:700;color:var(--text);}
.pg-hdr p{font-size:12.5px;color:var(--text-m);margin-top:2px;}

/* CARD */
.card{background:var(--surface);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.07);transition:background .35s;margin-bottom:18px;animation:fadeSlideUp .4s ease both;}

.card-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;}
.card-title svg{width:16px;height:16px;stroke:var(--brand);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* FILTER */
.filter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:14px;}
.fg{display:flex;flex-direction:column;gap:5px;}
.fg label{font-size:11px;font-weight:700;color:var(--text-m);text-transform:uppercase;letter-spacing:.3px;}
.fg select,.fg input{
  height:36px;padding:0 10px;
  border:1px solid var(--border);border-radius:8px;
  font-size:13px;color:var(--text);
  background:rgba(255,255,255,.45);backdrop-filter:blur(6px);
  outline:none;font-family:inherit;
  transition:border-color .17s,box-shadow .17s;
}
[data-theme="dark"] .fg select,[data-theme="dark"] .fg input{background:rgba(255,255,255,.06);}
.fg select:focus,.fg input:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-g);}
.dyn{display:none;}
.filter-btns{display:flex;gap:10px;margin-top:4px;flex-wrap:wrap;}
.btn-apply{height:36px;padding:0 18px;background:var(--brand);color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;font-family:inherit;display:inline-flex;align-items:center;gap:7px;box-shadow:0 2px 10px var(--brand-g);transition:background .17s;}
.btn-apply:hover{background:var(--brand-d);}
.btn-apply svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;}
.btn-reset{height:36px;padding:0 16px;background:var(--bg2);color:var(--text-m);border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:7px;transition:background .17s;}
.btn-reset:hover{background:var(--brand-g);color:var(--brand);}
.btn-reset svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;}

/* ALERT INLINE */
.alert-inline{padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:8px;animation:alertIn .3s ease both;}
.alert-inline svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0;}
.a-ok{background:var(--success-bg);color:var(--success);border:1px solid currentColor;}
.a-err{background:var(--danger-bg);color:var(--danger);border:1px solid currentColor;}
.a-warn{background:var(--warn-bg);color:var(--warn);border:1px solid currentColor;}

/* KPI ROW */
.kpi-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;}
.kpi-row h3{font-size:15px;font-weight:700;color:var(--text);}
.total-badge{
  background:var(--brand-g);border:1px solid var(--border);
  border-radius:9px;padding:8px 16px;
  font-size:14px;font-weight:700;color:var(--brand);
  display:flex;align-items:center;gap:8px;
}
.total-badge svg{width:16px;height:16px;stroke:var(--brand);fill:none;stroke-width:2;stroke-linecap:round;}

/* BULK DELETE BTN */
.btn-bulk{
  height:36px;padding:0 16px;background:var(--danger);color:#fff;
  border:none;border-radius:8px;cursor:pointer;
  font-size:13px;font-weight:700;font-family:inherit;
  display:none;align-items:center;gap:7px;
  box-shadow:0 2px 8px var(--danger-bg);
  transition:background .17s;margin-bottom:12px;
}
.btn-bulk:hover{background:#b03030;}
.btn-bulk svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;}

/* TABLE */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{border-bottom:2px solid var(--border);}
th{font-size:11px;font-weight:700;color:var(--text-m);text-transform:uppercase;letter-spacing:.4px;padding:9px 10px;text-align:left;}
td{padding:10px 10px;font-size:13px;color:var(--text);border-bottom:1px solid var(--border);vertical-align:middle;}
tbody tr{transition:background .14s;animation:fadeSlideUp .3s ease both;}
tbody tr:nth-child(1){animation-delay:.04s}tbody tr:nth-child(2){animation-delay:.07s}tbody tr:nth-child(3){animation-delay:.10s}tbody tr:nth-child(4){animation-delay:.13s}tbody tr:nth-child(5){animation-delay:.16s}tbody tr:nth-child(6){animation-delay:.19s}
tbody tr:hover{background:var(--brand-g);}
tbody tr:last-child td{border-bottom:none;}
.chk-col{width:38px;text-align:center !important;}
td.chk-col{text-align:center !important;}

input[type="checkbox"]{
  width:15px;height:15px;accent-color:var(--brand);cursor:pointer;
}

/* Inline amount input */
.amt-input{
  width:100px;height:32px;padding:0 8px;
  border:1px solid var(--border);border-radius:7px;
  font-size:13px;color:var(--text);
  background:rgba(255,255,255,.5);backdrop-filter:blur(4px);
  outline:none;font-family:inherit;
  transition:border-color .15s,box-shadow .15s;
}
[data-theme="dark"] .amt-input{background:rgba(255,255,255,.07);}
.amt-input:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-g);}

.day-sub{font-size:11.5px;color:var(--text-m);}
.cat-tag{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;background:var(--brand-g);color:var(--brand);font-size:11.5px;font-weight:600;}

.del-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;border:1px solid var(--border);font-size:11.5px;font-weight:600;text-decoration:none;color:var(--danger);background:none;cursor:pointer;font-family:inherit;transition:background .14s,border-color .14s;}
.del-btn:hover{background:var(--danger-bg);border-color:var(--danger);}
.del-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;}

.empty-row td{text-align:center;padding:32px;color:var(--text-m);font-size:13px;}

/* ══ TOAST NOTIFICATIONS ══ */
.toast-wrap{position:fixed;bottom:20px;right:16px;z-index:9000;display:flex;flex-direction:column;gap:10px;max-width:290px;}
.toast{
  background:var(--surface-s);
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  border:1px solid var(--border-s);border-radius:10px;
  padding:13px 14px;
  box-shadow:0 6px 24px rgba(0,0,0,.15);
  border-left:4px solid var(--brand);
  position:relative;
  animation:toastIn .3s ease both;
}
.toast.t-ok{border-left-color:var(--success);}
.toast.t-err{border-left-color:var(--danger);}
.toast.t-warn{border-left-color:var(--warn);}
.toast.t-notif{border-left-color:var(--brand);}
.toast-close{position:absolute;top:7px;right:9px;background:none;border:none;cursor:pointer;color:var(--text-m);font-size:15px;font-weight:700;padding:0;line-height:1;}
.toast-close:hover{color:var(--danger);}
.toast strong{font-size:13px;display:block;margin-bottom:2px;}
.toast span{font-size:11.5px;color:var(--text-m);}

@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeSlideUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes alertIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
@keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:600px){
  #main{padding:14px 10px;}
  .filter-grid{grid-template-columns:1fr;}
  .kpi-row{flex-direction:column;align-items:flex-start;}
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
    <!-- <a href="insert_data.php" class="ni"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Insert Data</a>
    <a href="records.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Records</a> -->
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
    <div class="nsub" id="es">
      <a href="insert_data.php" class="ni"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add New Expense</a>
      <a href="records.php" class="ni"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Expense History</a>
      <a href="settings.php" class="ni"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>Category Setup</a>
    </div>
    <p class="nl">Settings</p>
    <div class="nt" onclick="tog('ss',this)"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>Settings<svg class="cv" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div>
    <div class="nsub" id="ss">
      <!-- <a href="settings.php" class="ni"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>General Settings</a> -->
      <a href="manage_users.php" class="ni"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Staff Management</a>
      <a href="backup_system.php" class="ni"><svg viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>Backup &amp; Restore</a>
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
    <?php else: foreach($notifications as $n): ?>
      <div class="nm-it"><div class="nm-dot"></div><p><?php echo htmlspecialchars($n['message']??$n['title']??'Notification'); ?></p><form method="POST" action="dashboard.php" style="margin:0"><input type="hidden" name="clear_notif_id" value="<?php echo $n['id']; ?>"><button type="submit" class="nm-clr">Clear</button></form></div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ══ MAIN ══ -->
<main id="main">
  <div class="pg-hdr">
    <h2>Expense Records Ledger</h2>
    <p>Filter, review, update and delete expense records</p>
  </div>

  <!-- FILTER CARD -->
  <div class="card">
    <div class="card-title">
      <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Search Filters
    </div>
    <form method="GET" action="">
      <div class="filter-grid">
        <div class="fg">
          <label>Category</label>
          <select name="filter_category">
            <option value="">-- All Categories --</option>
            <?php foreach($all_categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>" <?php echo ($filter_category==$cat['id'])?'selected':''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label>Filter Type</label>
          <select name="filter_type" id="filter_type" onchange="toggleFlt()">
            <option value="all_records"    <?php if($filter_type=='all_records')    echo 'selected'; ?>>All Records</option>
            <option value="current_month"  <?php if($filter_type=='current_month')  echo 'selected'; ?>>Current Month</option>
            <option value="specific_date"  <?php if($filter_type=='specific_date')  echo 'selected'; ?>>Specific Date</option>
            <option value="date_to_date"   <?php if($filter_type=='date_to_date')   echo 'selected'; ?>>Date Range</option>
            <option value="specific_day"   <?php if($filter_type=='specific_day')   echo 'selected'; ?>>Specific Day</option>
            <option value="specific_month" <?php if($filter_type=='specific_month') echo 'selected'; ?>>Specific Month</option>
            <option value="specific_year"  <?php if($filter_type=='specific_year')  echo 'selected'; ?>>Specific Year</option>
          </select>
        </div>
        <div class="fg dyn" id="sec_specific_date"><label>Date</label><input type="date" name="specific_date" value="<?php echo $specific_date; ?>"></div>
        <div class="fg dyn" id="sec_start_date"><label>From Date</label><input type="date" name="start_date" value="<?php echo $start_date; ?>"></div>
        <div class="fg dyn" id="sec_end_date"><label>To Date</label><input type="date" name="end_date" value="<?php echo $end_date; ?>"></div>
        <div class="fg dyn" id="sec_specific_day">
          <label>Day</label>
          <select name="filter_day">
            <option value="">-- Choose Day --</option>
            <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
              <option value="<?php echo $d; ?>" <?php if($filter_day==$d) echo 'selected'; ?>><?php echo $d; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg dyn" id="sec_specific_month">
          <label>Month</label>
          <select name="filter_month">
            <option value="">-- Choose Month --</option>
            <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
              <option value="<?php echo $m; ?>" <?php if($filter_month==$m) echo 'selected'; ?>><?php echo $m; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg dyn" id="sec_specific_year"><label>Year</label><input type="number" name="filter_year" placeholder="e.g. 2026" value="<?php echo $filter_year; ?>"></div>
      </div>
      <div class="filter-btns">
        <button type="submit" class="btn-apply"><svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>Apply Filters</button>
        <a href="records.php" class="btn-reset"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>Reset</a>
      </div>
    </form>
  </div>

  <!-- RECORDS CARD -->
  <div class="card" style="animation-delay:.08s;">

    <?php if(!empty($msg)): ?>
      <div class="alert-inline a-<?php echo $msg_type; ?>">
        <?php if($msg_type==='ok'): ?><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        <?php elseif($msg_type==='err'): ?><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php else: ?><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?php endif; ?>
        <?php echo $msg; ?>
      </div>
    <?php endif; ?>

    <div class="kpi-row">
      <h3>Filtered Statement — <?php echo count($records); ?> row(s)</h3>
      <div class="total-badge">
        <!-- <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> -->
        Total: Rs. <?php echo number_format($filtered_total, 0); ?>
      </div>
    </div>

    <form method="POST" action="" onsubmit="return confirmBulkDel()">
      <button type="submit" name="bulk_delete_submit" id="bulkBtn" class="btn-bulk">
        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        Delete Selected (0)
      </button>

      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th class="chk-col"><input type="checkbox" onclick="selAll(this)"></th>
              <th>Date</th>
              <th>Day / Month / Year</th>
              <th>Category</th>
              <th>Amount (Rs.)</th>
              <th style="text-align:center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if(count($records)>0): foreach($records as $row): ?>
              <tr>
                <td class="chk-col">
                  <input type="checkbox" name="selected_records[]" value="<?php echo $row['expense_row_id']; ?>" class="rc" onclick="updBulkBtn()">
                </td>
                <td><?php echo date('d-M-Y', strtotime($row['date'])); ?></td>
                <td><span class="day-sub"><?php echo $row['expense_day']; ?> / <?php echo $row['expense_month']; ?> / <?php echo $row['expense_year']; ?></span></td>
                <td><span class="cat-tag"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                <td>
                  <input type="number" step="0.01" class="amt-input"
                         value="<?php echo $row['amount']; ?>"
                         onchange="updateAmt(<?php echo $row['expense_row_id']; ?>, this.value)">
                </td>
                <td style="text-align:center;">
                  <a href="records.php?delete_id=<?php echo $row['expense_row_id']; ?>"
                     class="del-btn"
                     onclick="return confirm('Delete this record permanently?')">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr class="empty-row"><td colspan="6">No records found matching the filter criteria.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </form>
  </div>
</main>

<!-- Hidden update form — original logic intact -->
<form id="hiddenUpdateForm" method="POST" action="" style="display:none;">
  <input type="hidden" name="update_expense_amount" value="1">
  <input type="hidden" name="expense_id" id="hidden_expense_id">
  <input type="hidden" name="amount" id="hidden_amount">
</form>

<!-- ══ TOAST NOTIFICATIONS ══ -->
<?php if(!empty($notifications)): ?>
<div class="toast-wrap">
  <?php foreach($notifications as $n): ?>
    <div class="toast t-notif">
      <button class="toast-close" onclick="this.closest('.toast').remove()">&times;</button>
      <strong style="color:var(--brand);">Alert (ID: <?php echo $n['id']; ?>)</strong>
      <span><?php echo htmlspecialchars($n['message'] ?? $n['title'] ?? 'Notification'); ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
/* MOUSE TRAIL */
var tc=document.getElementById('trail'),tx=tc.getContext('2d'),TW,TH,pts=[];
function rsz(){TW=tc.width=window.innerWidth;TH=tc.height=window.innerHeight;}
rsz();window.addEventListener('resize',rsz);
document.addEventListener('mousemove',function(e){for(var i=0;i<3;i++) pts.push({x:e.clientX+(Math.random()-.5)*16,y:e.clientY+(Math.random()-.5)*16,r:Math.random()*24+8,a:.16,vx:(Math.random()-.5)*.5,vy:(Math.random()-.5)*.5,c:Math.random()>.5?'120,69,12':'190,130,60'});});
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

/* FILTER TOGGLE — original logic */
function toggleFlt(){
  var t=document.getElementById('filter_type').value;
  var map={specific_date:['sec_specific_date'],date_to_date:['sec_start_date','sec_end_date'],specific_day:['sec_specific_day'],specific_month:['sec_specific_month'],specific_year:['sec_specific_year']};
  document.querySelectorAll('.dyn').forEach(function(el){el.style.display='none';});
  if(map[t]) map[t].forEach(function(id){document.getElementById(id).style.display='flex';});
}

/* SELECT ALL — original logic */
function selAll(master){
  document.querySelectorAll('.rc').forEach(function(c){c.checked=master.checked;});
  updBulkBtn();
}

/* BULK BTN VISIBILITY — original logic */
function updBulkBtn(){
  var n=document.querySelectorAll('.rc:checked').length;
  var btn=document.getElementById('bulkBtn');
  if(n>0){btn.style.display='inline-flex';btn.innerHTML='<svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Delete Selected ('+n+')';}
  else{btn.style.display='none';}
}

/* CONFIRM BULK DELETE */
function confirmBulkDel(){
  var n=document.querySelectorAll('.rc:checked').length;
  return confirm('Delete '+n+' selected record(s) permanently?');
}

/* INLINE AMOUNT UPDATE — original logic */
function updateAmt(id,val){
  document.getElementById('hidden_expense_id').value=id;
  document.getElementById('hidden_amount').value=val;
  document.getElementById('hiddenUpdateForm').submit();
}

/* DATE */
document.getElementById('nbDate').textContent=new Date().toLocaleDateString('en-PK',{weekday:'short',year:'numeric',month:'short',day:'numeric'});

/* INIT */
window.onload=function(){toggleFlt();};
</script>
</body>
</html>