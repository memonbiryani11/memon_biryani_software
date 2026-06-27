<?php
session_start();
require_once 'auth_functions.php';
require_once 'db.php';

checkSession();

$message = '';
$error = '';
$order = null;
$order_items = [];

// 1. SEARCH ORDER
if (isset($_GET['search_order_id'])) {
    $order_id = intval($_GET['search_order_id']);
    
    $stmt = $pdo->prepare("SELECT * FROM sell_records WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if ($order) {
        if (isset($order['status']) && $order['status'] == 'cancelled') {
            $error = "Yeh order pehle se hi cancel ho chuka hai!";
        } else {
            $stmt_items = $pdo->prepare("SELECT si.* FROM sell_items si WHERE si.sell_record_id = ?");
            $stmt_items->execute([$order_id]);
            $order_items = $stmt_items->fetchAll();
        }
    } else {
        $error = "Order ID #$order_id system me nahi mili.";
    }
}

// 2. PROCESS CANCELLATION (Without stock dependency)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_cancel'])) {
    $order_id = intval($_POST['order_id']);
    
    try {
        $pdo->beginTransaction();
        
        // sell_records status ko direct 'cancelled' kar rahe hain
        $update_order = $pdo->prepare("UPDATE sell_records SET status = 'cancelled' WHERE id = ?");
        $update_order->execute([$order_id]);
        
        $pdo->commit();
        $message = "Order #$order_id kamyabi se cancel ho gaya!";
        $order = null; 
        $order_items = [];
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Cancellation me masla aaya: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Cancellation & Returns - Memon Biryani</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #fafafa; margin: 0; padding: 0; }
        .top-navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: white; border-bottom: 1px solid #eef0f2; }
        .brand-section h2 { margin: 0; font-family: 'Georgia', serif; font-size: 22px; font-weight: bold; }
        .nav-links { display: flex; gap: 20px; font-size: 14px; align-items: center; }
        .nav-links a { text-decoration: none; color: #444; padding: 5px 0; font-weight: bold; }
        .nav-links a.active { color: #dc3545; border-bottom: 2px solid #dc3545; }
        .pos-dropdown { position: relative; display: inline-block; }
        .pos-dropbtn { background: none; border: none; color: #000; padding: 5px 0; font-size: 14px; font-weight: bold; cursor: pointer; }
        .pos-dropdown-content { display: none; position: absolute; background-color: #ffffff; min-width: 170px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); border-radius: 4px; z-index: 5000; top: 100%; left: 0; }
        .pos-dropdown-content a { color: #333 !important; padding: 10px 15px !important; display: block !important; }
        .pos-dropdown:hover .pos-dropdown-content { display: block; }
        .nav-btn { color: white !important; padding: 6px 15px !important; border-radius: 4px; font-weight: bold; }

        .container { max-width: 800px; margin: 40px auto; background: white; padding: 30px; border: 1px solid #e6e6e6; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        h3 { margin-top: 0; color: #334155; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        
        .search-box { display: flex; gap: 10px; margin-bottom: 25px; }
        .search-box input { flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 15px; }
        .btn { padding: 12px 25px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; width: 100%; margin-top: 20px; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        
        .order-details { background: #f8fafc; padding: 20px; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .table th { background: #edf2f7; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="top-navbar">
        <div class="brand-section"><h2>Memon Biryani</h2></div>
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
        <h3><i class="fa fa-undo"></i> Order Return & Cancellation System</h3>
        
        <?php if(!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Search Form -->
        <form method="GET" class="search-box">
            <input type="number" name="search_order_id" placeholder="Enter Order ID / Invoice Number..." required value="<?php echo $_GET['search_order_id'] ?? ''; ?>">
            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Find Order</button>
        </form>

        <!-- Order Summary Display -->
        <?php if($order): ?>
            <div class="order-details">
                <h4>Order Summary (#<?php echo $order['id']; ?>)</h4>
                <p><strong>Date:</strong> <?php echo $order['sell_date']; ?></p>
                <p><strong>Total Amount:</strong> Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                
                <h5>Items List:</h5>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                            <td>Rs. <?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="POST" onsubmit="return confirm('Kya aap waqai is order ko cancel karna chahte hain?')">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <button type="submit" name="process_cancel" class="btn btn-danger">
                        <i class="fa fa-trash-alt"></i> Confirm Cancel Order
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>