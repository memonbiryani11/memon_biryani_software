<?php
session_start();
require_once 'auth_functions.php';
require_once 'db.php';

// 1. Auth Check: Secure this page from guests
checkSession();

// Fetch items and structural categories
$categories = $pdo->query("SELECT DISTINCT category_name FROM pos_products ORDER BY category_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$products = $pdo->query("SELECT * FROM pos_products ORDER BY product_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>POS Counter - Memon Biryani</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f6f9; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        /* NAVBAR STYLES */
        .top-navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: white; border-bottom: 1px solid #eef0f2; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .brand-section { display: flex; align-items: center; gap: 15px; }
        .brand-section h2 { margin: 0; font-family: 'Georgia', serif; font-size: 22px; font-weight: bold; }
        .brand-section span.user-tag { color: #d4af37; font-weight: bold; font-style: italic; font-size: 15px; }
        .nav-links { display: flex; gap: 20px; font-size: 14px; align-items: center; }
        .nav-links a { text-decoration: none; color: #444; padding: 5px 0; font-weight: bold; }
        .nav-links a:hover { color: #000; }
        .nav-links a.active { color: #000; border-bottom: 2px solid #000; }
        .nav-btn { color: white !important; padding: 6px 15px !important; border-radius: 4px; font-weight: bold; transition: background 0.2s; }
        .nav-btn:hover { background: #bd2130 !important; }

        /* POS DROPDOWN STYLING */
        .pos-dropdown { position: relative; display: inline-block; }
        .pos-dropbtn { background: none; border: none; color: #000; padding: 5px 0; font-size: 14px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px; border-bottom: 2px solid #10b981; }
        .pos-dropdown-content { display: none; position: absolute; background-color: #ffffff; min-width: 170px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); border: 1px solid #eef0f2; border-radius: 4px; z-index: 5000; top: 100%; left: 0; margin-top: 5px; }
        .pos-dropdown-content a { color: #333 !important; padding: 10px 15px !important; text-decoration: none !important; display: flex !important; align-items: center; gap: 10px; font-size: 13px; border-bottom: 1px solid #f8f9fa; }
        .pos-dropdown-content a:hover { background-color: #f8fafc; color: #000 !important; }
        .pos-dropdown:hover .pos-dropdown-content { display: block; }

        /* MAIN CONTENT LAYOUT */
        .pos-main-container { display: flex; flex: 1; overflow: hidden; }
        .main-billing-area { width: 70%; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; }
        .side-cart-panel { width: 30%; background: white; border-left: 1px solid #cbd5e1; display: flex; flex-direction: column; box-shadow: -4px 0 15px rgba(0,0,0,0.05); }
        
        /* CATEGORY ROW & CARDS */
        .category-filter-row { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .cat-btn { background: white; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 20px; cursor: pointer; font-weight: bold; white-space: nowrap; transition: all 0.2s; }
        .cat-btn.active, .cat-btn:hover { background: #007bff; color: white; border-color: #007bff; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; }
        .product-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; cursor: pointer; transition: transform 0.2s; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .product-card img { width: 100%; height: 100px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; }
        
        /* SIDE CART ELEMENTS */
        .cart-header { padding: 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .cart-items-list { flex-grow: 1; overflow-y: auto; padding: 15px; }
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .cart-item-info { display: flex; flex-direction: column; width: 60%; }
        .remove-item-btn { background: none; border: none; color: #ef4444; font-size: 16px; cursor: pointer; }
        .cart-summary { padding: 20px; border-top: 1px solid #f1f5f9; background: #f8fafc; }
        .total-row { display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; margin-bottom: 15px; }
        .btn-clear-checkout { background: #10b981; color: white; border: none; padding: 14px; width: 100%; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>

    <div class="top-navbar">
        <div class="brand-section">
            <h2>Memon Biryani</h2>
            <span class="user-tag"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Hamza'); ?></span>
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
                    <a href="pos_manage_products.php"><i class="fa fa-cog" style="color: #6c757d;"></i> Manage Items</a>
                </div>
            </div>
            
            <a href="settings.php">Settings</a>
            
            <?php include 'notifications_panel.php'; ?>
            
            <a href="logout.php" class="nav-btn" style="margin-left: 10px; background:#dc3545;">Logout</a>
        </div>
    </div>

    <div class="pos-main-container">
        <div class="main-billing-area">
            <div class="category-filter-row">
                <button class="cat-btn active" onclick="filterCategory('all', this)">All Items</button>
                <?php foreach($categories as $cat): ?>
                    <button class="cat-btn" onclick="filterCategory('<?php echo htmlspecialchars($cat); ?>', this)"><?php echo htmlspecialchars($cat); ?></button>
                <?php endforeach; ?>
            </div>

            <div class="products-grid">
                <?php foreach($products as $p): ?>
                    <div class="product-card" data-category="<?php echo htmlspecialchars($p['category_name']); ?>" onclick="addToCart('<?php echo htmlspecialchars($p['product_name']); ?>', <?php echo $p['price']; ?>)">
                        <img src="<?php echo !empty($p['image_path']) ? $p['image_path'] : 'uploads/default.png'; ?>">
                        <div style="font-size: 14px; font-weight: bold; margin: 5px 0;"><?php echo htmlspecialchars($p['product_name']); ?></div>
                        <div style="color: #10b981; font-weight: bold;">Rs. <?php echo number_format($p['price'], 0); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="side-cart-panel">
            <div class="cart-header">
                <h3 style="margin:0;"><i class="fa fa-shopping-cart"></i> Current Cart</h3>
                <span id="itemsCountText" style="font-weight:bold; color:#64748b;">0 Items</span>
            </div>
            <div class="cart-items-list" id="cartContainer">
                <p id="emptyCartMessage" style="text-align:center; color:#94a3b8; margin-top:40px;">Cart is currently empty.</p>
            </div>
            <div class="cart-summary">
                <div class="total-row">
                    <span>Total Amount:</span>
                    <span style="color:#10b981;">Rs. <span id="cartTotalSum">0</span></span>
                </div>
                <button type="button" class="btn-clear-checkout" onclick="processCheckoutInvoice()"><i class="fa fa-check-circle"></i> Clear Amount & Save</button>
            </div>
        </div>
    </div>

    <script>
        let cart = {};
        function filterCategory(catName, btnElement) {
            document.querySelectorAll('.cat-btn').forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');
            document.querySelectorAll('.product-card').forEach(card => {
                if(catName === 'all' || card.getAttribute('data-category') === catName) { card.style.display = 'block'; } 
                else { card.style.display = 'none'; }
            });
        }
        function addToCart(name, price) {
            if (cart[name]) { cart[name].qty += 1; } else { cart[name] = { price: price, qty: 1 }; }
            renderCartUI();
        }
        function removeFromCart(name) { delete cart[name]; renderCartUI(); }
        function renderCartUI() {
            const container = document.getElementById('cartContainer');
            const totalSumElement = document.getElementById('cartTotalSum');
            const countElement = document.getElementById('itemsCountText');
            container.innerHTML = '';
            let total = 0; let totalItems = 0; let keys = Object.keys(cart);
            if(keys.length === 0) {
                container.innerHTML = '<p id="emptyCartMessage" style="text-align:center; color:#94a3b8; margin-top:40px;">Cart is currently empty.</p>';
                totalSumElement.innerText = '0'; countElement.innerText = '0 Items'; return;
            }
            keys.forEach(name => {
                let itemTotal = cart[name].price * cart[name].qty;
                total += itemTotal; totalItems += cart[name].qty;
                let rowHTML = `<div class="cart-item"><div class="cart-item-info"><b>${name}</b><span style="font-size:11px; color:#64748b;">Rs. ${cart[name].price} x ${cart[name].qty}</span></div><span style="font-weight:bold;">Rs. ${itemTotal}</span><button class="remove-item-btn" onclick="removeFromCart('${name}')">&times;</button></div>`;
                container.insertAdjacentHTML('beforeend', rowHTML);
            });
            totalSumElement.innerText = total.toLocaleString();
            countElement.innerText = totalItems + ' Items';
        }
        function processCheckoutInvoice() {
            if(Object.keys(cart).length === 0) { alert("Cannot process an empty cart!"); return; }
            fetch('pos_process_checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cart)
            })
            .then(res => res.json())
            .then(resData => {
                if(resData.status === 'success') { alert("Invoice cleared successfully!"); cart = {}; renderCartUI(); } 
                else { alert("Error saving transaction data logs."); }
            });
        }
    </script>
</body>
</html>