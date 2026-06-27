<?php
session_start();
require_once 'auth_functions.php';
require_once 'db.php';

// Auth Check Access Validator
checkSession();

// Default filter: Current Date (Aaj ki tareekh)
$selected_date = $_GET['filter_date'] ?? date('Y-m-d');

// 1. Fetch all orders for the selected date
$orders_stmt = $pdo->prepare("
    SELECT * FROM sell_records 
    WHERE sell_date = ? 
    ORDER BY id DESC
");
$orders_stmt->execute([$selected_date]);
$orders = $orders_stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>POS Entries Log - Memon Biryani</title>
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

        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .filter-card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        input[type="date"] { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-weight: bold; font-size: 14px; }
        
        /* ORDER ENTRY BLOCK */
        .order-entry-box { background: white; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        .order-header { background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; }
        .order-id { font-size: 16px; font-weight: bold; color: #1e293b; }
        .order-time { font-size: 13px; color: #64748b; margin-left: 10px; }
        .order-amount { font-size: 16px; font-weight: bold; color: #047857; }
        
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { padding: 10px 20px; text-align: left; font-size: 14px; }
        .items-table th { background: #ffffff; color: #64748b; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; }
        .items-table td { border-bottom: 1px solid #f8fafc; color: #334155; }
        
        .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-badge.success { background: #dcfce7; color: #15803d; }
        .status-badge.cancelled { background: #fee2e2; color: #b91c1c; }
        .box-cancelled { border-left: 4px solid #ef4444; opacity: 0.8; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
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
                    <a href="view_pos_entries.php"><i class="fa fa-list" style="color: #ff9800;"></i> View Entries</a>
                    <a href="cancel_order.php"><i class="fa fa-undo" style="color: #dc3545;"></i> Order Cancellation</a>
                    <a href="pos_manage_products.php"><i class="fa fa-cog" style="color: #6c757d;"></i> Manage Items</a>
                </div>
            </div>
            
            <a href="settings.php">Settings</a>
            <?php include 'notifications_panel.php'; ?>
            <a href="logout.php" class="nav-btn" style="margin-left: 10px; background:#dc3545;">Logout</a>
        </div>
    </div>

    <div class="container">
        
        <!-- DATE FILTER CARD -->
        <div class="filter-card">
            <h2 style="margin:0; font-size:18px; color:#1e293b;"><i class="fa fa-history" style="color:#ff9800;"></i> Detailed POS Entry Logs</h2>
            <form method="GET" style="margin:0;">
                <label style="font-weight:bold; margin-right:8px; font-size:14px; color:#475569;">Filter By Date:</label>
                <input type="date" name="filter_date" value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
            </form>
        </div>

        <!-- ENTRIES LIST -->
        <?php if(count($orders) > 0): ?>
            <?php foreach($orders as $order): 
                $is_cancelled = (isset($order['status']) && $order['status'] == 'cancelled');
                
                // Fetch items for this specific order
                $items_stmt = $pdo->prepare("SELECT * FROM sell_items WHERE sell_record_id = ?");
                $items_stmt->execute([$order['id']]);
                $order_items = $items_stmt->fetchAll();
            ?>
                <div class="order-entry-box <?php echo $is_cancelled ? 'box-cancelled' : ''; ?>">
                    
                    <!-- Order Main Header -->
                    <div class="order-header">
                        <div>
                            <span class="order-id">Invoice #<?php echo $order['id']; ?></span>
                            <span class="order-time"><i class="fa fa-clock"></i> Date: <?php echo $order['sell_date']; ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span class="status-badge <?php echo $is_cancelled ? 'cancelled' : 'success'; ?>">
                                <?php echo $is_cancelled ? 'Cancelled' : 'Completed'; ?>
                            </span>
                            <span class="order-amount" style="<?php echo $is_cancelled ? 'color:#dc3545; text-decoration:line-through;' : ''; ?>">
                                Rs. <?php echo number_format($order['total_amount'], 2); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Items Table inside Order -->
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Qty Sold</th>
                                <th>Unit Price</th>
                                <th style="text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($order_items as $item): ?>
                                <tr>
                                    <td><b><?php echo htmlspecialchars($item['product_name']); ?></b></td>
                                    <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:bold;"><?php echo $item['quantity']; ?></span></td>
                                    <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                    <td style="text-align: right; font-weight: bold;">Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 5px; text-align: center; color: #94a3b8; padding: 50px 20px;">
                <i class="fa fa-folder-open" style="font-size: 40px; margin-bottom: 10px; color: #cbd5e1;"></i>
                <p style="margin:0; font-weight: bold;">Is tareekh (<?php echo date('d-M-Y', strtotime($selected_date)); ?>) me koi POS checkouts processed nahi hue.</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>