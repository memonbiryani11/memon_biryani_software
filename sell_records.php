<?php
session_start();
require_once 'auth_functions.php';
require_once 'db.php';

// Auth Check Access Validator
checkSession();

$selected_date = $_GET['filter_date'] ?? date('Y-m-d');

// 1. SUMMARY FOR ACTIVE SALES (Excluding Cancelled Status)
$summary_stmt = $pdo->prepare("SELECT SUM(total_amount) AS overall_earnings, COUNT(*) AS checkouts_processed FROM sell_records WHERE sell_date = ? AND (status != 'cancelled' OR status IS NULL)");
$summary_stmt->execute([$selected_date]);
$summary = $summary_stmt->fetch();

// 2. SUMMARY FOR CANCELLED SALES ONLY
$cancel_stmt = $pdo->prepare("SELECT SUM(total_amount) AS total_cancelled, COUNT(*) AS count_cancelled FROM sell_records WHERE sell_date = ? AND status = 'cancelled'");
$cancel_stmt->execute([$selected_date]);
$cancel_summary = $cancel_stmt->fetch();

$total_active_earnings = $summary['overall_earnings'] ?? 0;
$active_orders_count = $summary['checkouts_processed'] ?? 0;

$total_cancelled_amount = $cancel_summary['total_cancelled'] ?? 0;
$cancelled_orders_count = $cancel_summary['count_cancelled'] ?? 0;

// 3. PRODUCT BREAKDOWN LIST (Only count from valid non-cancelled orders)
$details_stmt = $pdo->prepare("
    SELECT 
        si.product_name,
        SUM(si.quantity) AS total_qty_sold,
        si.price,
        SUM(si.price * si.quantity) AS consolidated_subtotal
    FROM sell_items si
    JOIN sell_records sr ON si.sell_record_id = sr.id
    WHERE sr.sell_date = ? AND (sr.status != 'cancelled' OR sr.status IS NULL)
    GROUP BY si.product_name, si.price
    ORDER BY total_qty_sold DESC
");
$details_stmt->execute([$selected_date]);
$sales_breakdown = $details_stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>POS Reports - Memon Biryani</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #fafafa; padding: 0; margin: 0; }
        
        /* NAVBAR STYLES */
        .top-navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: white; border-bottom: 1px solid #eef0f2; }
        .brand-section { display: flex; align-items: center; gap: 15px; }
        .brand-section h2 { margin: 0; font-family: 'Georgia', serif; font-size: 22px; font-weight: bold; }
        .nav-links { display: flex; gap: 20px; font-size: 14px; align-items: center; }
        .nav-links a { text-decoration: none; color: #444; padding: 5px 0; font-weight: bold; }
        .nav-btn { color: white !important; padding: 6px 15px !important; border-radius: 4px; font-weight: bold; }

        /* DROPDOWN STYLING */
        .pos-dropdown { position: relative; display: inline-block; }
        .pos-dropbtn { background: none; border: none; color: #000; padding: 5px 0; font-size: 14px; font-weight: bold; cursor: pointer; border-bottom: 2px solid #10b981; }
        .pos-dropdown-content { display: none; position: absolute; background-color: #ffffff; min-width: 170px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); border-radius: 4px; z-index: 5000; top: 100%; left: 0; }
        .pos-dropdown-content a { color: #333 !important; padding: 10px 15px !important; display: block !important; }
        .pos-dropdown:hover .pos-dropdown-content { display: block; }

        .report-card { max-width: 950px; margin: 30px auto; background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .metric-box { padding: 20px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center; }
        .table-report { width: 100%; border-collapse: collapse; }
        .table-report th, .table-report td { padding: 12px 15px; border-bottom: 1px solid #edf2f7; text-align: left; }
        input[type="date"] { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>

    <div class="top-navbar">
        <div class="brand-section">
            <h2>Memon Biryani</h2>
        </div>
        <div class="nav-links">
            <a href="insert_data.php">Insert</a>
            <a href="records.php">Records</a>
            <a href="dashboard.php">Dashboard</a>
            
            <div class="pos-dropdown">
                <button class="pos-dropbtn"><i class="fa fa-calculator"></i> POS Modules <i class="fa fa-caret-down"></i></button>
                <div class="pos-dropdown-content">
                    <a href="pos_screen.php"><i class="fa fa-shopping-cart" style="color: #10b981;"></i> POS Counter</a>
                    <a href="sell_records.php"><i class="fa fa-chart-line" style="color: #007bff;"></i> POS Reports</a>
                    <a href="cancel_order.php"><i class="fa fa-undo" style="color: #dc3545;"></i> Order Cancellation</a>
                    <a href="pos_manage_products.php"><i class="fa fa-cog" style="color: #6c757d;"></i> Manage Items</a>
                </div>
            </div>
            
            <a href="settings.php">Settings</a>
            <?php include 'notifications_panel.php'; ?>
            <a href="logout.php" class="nav-btn" style="margin-left: 10px; background:#dc3545;">Logout</a>
        </div>
    </div>

    <div class="report-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0;"><i class="fa fa-chart-line" style="color:#007bff;"></i> Sales Accountability Audit</h2>
            <form method="GET" style="margin:0;">
                <input type="date" name="filter_date" value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
            </form>
        </div>

        <div class="metrics-grid">
            <div class="metric-box" style="background: #ecfdf5; border-color: #a7f3d0; text-align: left; display: flex; justify-content: space-between; align-items: center; padding: 15px 25px;">
                <div>
                    <div style="font-size: 13px; color: #065f46; font-weight: bold; text-transform: uppercase;">Net Sales Revenue</div>
                    <div style="font-size: 26px; font-weight: bold; color: #047857; margin: 4px 0;">Rs. <?php echo number_format($total_active_earnings, 2); ?></div>
                    <div style="font-size: 12px; color: #047857; font-weight: 500;">Valid Active Invoices: <?php echo $active_orders_count; ?></div>
                </div>
                <i class="fa fa-wallet" style="font-size: 32px; color: #10b981; opacity: 0.7;"></i>
            </div>
            
            <div class="metric-box" style="background: #fef2f2; border-color: #fca5a5; text-align: left; display: flex; justify-content: space-between; align-items: center; padding: 15px 25px;">
                <div>
                    <div style="font-size: 13px; color: #991b1b; font-weight: bold; text-transform: uppercase;">Cancelled / Returns</div>
                    <div style="font-size: 26px; font-weight: bold; color: #dc3545; margin: 4px 0;">Rs. <?php echo number_format($total_cancelled_amount, 2); ?></div>
                    <div style="font-size: 12px; color: #b91c1c; font-weight: 500;">Total Cancelled Invoices: <?php echo $cancelled_orders_count; ?></div>
                </div>
                <i class="fa fa-ban" style="font-size: 32px; color: #ef4444; opacity: 0.7;"></i>
            </div>
        </div>

        <h3 style="font-size: 16px; color:#334155; margin-top:25px; border-bottom: 2px solid #f1f5f9; padding-bottom:8px;"><i class="fa fa-cubes"></i> Item-Wise Sales Performance Breakdown</h3>
        <table class="table-report">
            <thead>
                <tr><th>Product Name</th><th>Units Sold</th><th>Price</th><th style="text-align: right;">Total Amount</th></tr>
            </thead>
            <tbody>
                <?php if(count($sales_breakdown) > 0): ?>
                    <?php foreach($sales_breakdown as $item): ?>
                    <tr>
                        <td><b><?php echo htmlspecialchars($item['product_name']); ?></b></td>
                        <td><span style="background:#f1f5f9; padding:4px 10px; font-weight:bold; border-radius:4px;"><?php echo $item['total_qty_sold']; ?></span></td>
                        <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                        <td style="text-align: right; font-weight: bold;">Rs. <?php echo number_format($item['consolidated_subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 40px;">Is tareekh me koi active items sell nahi hue hain.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>