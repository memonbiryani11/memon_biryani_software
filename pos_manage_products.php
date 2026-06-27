<?php
session_start();
require_once 'auth_functions.php';
require_once 'db.php';

// Auth Check Rules
checkSession();

// 1. HANDLE CATEGORY ACTIONS (WordPress Style)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_category'])) {
    $cat_name = trim(htmlspecialchars($_POST['new_category_name']));
    if (!empty($cat_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pos_categories (category_name) VALUES (?)");
            $stmt->execute([$cat_name]);
        } catch (PDOException $e) {
            // Duplicate category error handle karne ke liye
        }
    }
    header("Location: pos_manage_products.php"); exit();
}

if (isset($_GET['delete_cat_id'])) {
    $stmt = $pdo->prepare("DELETE FROM pos_categories WHERE id = ?");
    $stmt->execute([intval($_GET['delete_cat_id'])]);
    header("Location: pos_manage_products.php"); exit();
}


// 2. HANDLE PRODUCT ACTIONS (Add / Update)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_product'])) {
    $id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $name = htmlspecialchars($_POST['product_name']);
    $category = htmlspecialchars($_POST['category_name']); // Dropdown se category select hogi
    $price = floatval($_POST['price']);
    
    $image_name = $_POST['existing_image'] ?? '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $image_name = $target_dir . time() . "_" . basename($_FILES['product_image']['name']);
        move_uploaded_file($_FILES['product_image']['tmp_name'], $image_name);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE pos_products SET product_name = ?, category_name = ?, price = ?, image_path = ? WHERE id = ?");
        $stmt->execute([$name, $category, $price, $image_name, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pos_products (product_name, category_name, price, image_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $category, $price, $image_name]);
    }
    header("Location: pos_manage_products.php"); exit();
}

// Handle Product Delete
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM pos_products WHERE id = ?");
    $stmt->execute([intval($_GET['delete_id'])]);
    header("Location: pos_manage_products.php"); exit();
}

// Fetch for Product Edit
$edit_prod = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM pos_products WHERE id = ?");
    $stmt->execute([intval($_GET['edit_id'])]);
    $edit_prod = $stmt->fetch();
}

// Global Selects for View Panels
$categories = $pdo->query("SELECT * FROM pos_categories ORDER BY category_name ASC")->fetchAll();
$products = $pdo->query("SELECT * FROM pos_products ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>POS Items Manager - Memon Biryani</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #fafafa; margin: 0; padding: 0; }
        
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

        /* GRID LAYOUTS */
        .container { max-width: 1350px; margin: 30px auto; display: grid; grid-template-columns: 320px 1fr; gap: 25px; padding: 0 20px; }
        .left-sidebar { display: flex; flex-direction: column; gap: 20px; }
        .right-content { display: flex; flex-direction: column; gap: 20px; }
        
        .card-panel { background: white; padding: 20px; border: 1px solid #e6e6e6; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        h3 { margin-top: 0; margin-bottom: 15px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; font-size: 16px; color: #334155; }
        
        label { font-weight: bold; font-size: 13px; display: block; margin-bottom: 5px; color: #475569; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #10b981; color: white; border: none; padding: 10px 15px; cursor: pointer; font-weight: bold; width: 100%; border-radius: 4px; font-size: 14px; }
        .btn-primary { background: #007bff; }
        
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border-bottom: 1px solid #f1f5f9; padding: 12px 10px; text-align: left; font-size: 14px; }
        .table th { background: #f8fafc; font-weight: bold; color: #475569; }
        .prod-img { width: 45px; height: 45px; object-fit: cover; border-radius: 4px; }
        
        .cat-badge-list { max-height: 250px; overflow-y: auto; padding-right: 5px; }
        .cat-item-row { display: flex; justify-content: space-between; padding: 8px 5px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    </style>
</head>
<body>

    <!-- SYSTEM GLOBAL NAVBAR -->
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
                    <a href="pos_manage_products.php"><i class="fa fa-cog" style="color: #6c757d;"></i> Manage Items</a>
                </div>
            </div>
            
            <a href="settings.php">Settings</a>
            <?php include 'notifications_panel.php'; ?>
            <a href="logout.php" class="nav-btn" style="margin-left: 10px; background:#dc3545;">Logout</a>
        </div>
    </div>

    <!-- MAIN GRID CONTAINER -->
    <div class="container">
        
        <!-- LEFT SIDEBAR: CATEGORY MANAGEMENT -->
        <div class="left-sidebar">
            <div class="card-panel">
                <h3><i class="fa fa-folder-plus"></i> Add New Category</h3>
                <form method="POST">
                    <label>Category Name</label>
                    <input type="text" name="new_category_name" placeholder="e.g., Cold Drinks, BBQ" required>
                    <button type="submit" name="save_category" class="btn btn-primary"><i class="fa fa-plus"></i> Add Category</button>
                </form>
            </div>
            
            <div class="card-panel">
                <h3><i class="fa fa-tags"></i> Active Categories</h3>
                <div class="cat-badge-list">
                    <?php if(count($categories) > 0): ?>
                        <?php foreach($categories as $cat): ?>
                        <div class="cat-item-row">
                            <span><b><?php echo htmlspecialchars($cat['category_name']); ?></b></span>
                            <a href="pos_manage_products.php?delete_cat_id=<?php echo $cat['id']; ?>" style="color:#dc3545;" onclick="return confirm('Is category ko delete karne se products delete nahi honge. Delete?')"><i class="fa fa-times-circle"></i></a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size:12px; color:#94a3b8; text-align:center;">Koi category nahi bani hui.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT CONTENT AREA: PRODUCT CREATION & LIST -->
        <div class="right-content">
            <div class="card-panel">
                <h3><i class="fa fa-box-open"></i> <?php echo $edit_prod ? 'Edit Product Details' : 'Create New Product'; ?></h3>
                <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <input type="hidden" name="product_id" value="<?php echo $edit_prod['id'] ?? 0; ?>">
                    <input type="hidden" name="existing_image" value="<?php echo $edit_prod['image_path'] ?? ''; ?>">
                    
                    <div>
                        <label>Product Name</label>
                        <input type="text" name="product_name" value="<?php echo $edit_prod['product_name'] ?? ''; ?>" required>
                        
                        <label>Select Category (WordPress Style)</label>
                        <select name="category_name" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category_name']); ?>" <?php echo (isset($edit_prod['category_name']) && $edit_prod['category_name'] == $cat['category_name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Price (Rs.)</label>
                        <input type="number" step="0.01" name="price" value="<?php echo $edit_prod['price'] ?? ''; ?>" required>
                        
                        <label>Product Image</label>
                        <input type="file" name="product_image" accept="image/*">
                    </div>
                    
                    <div style="grid-column: span 2;">
                        <button type="submit" name="save_product" class="btn"><i class="fa fa-save"></i> <?php echo $edit_prod ? 'Update Product Item' : 'Publish Product'; ?></button>
                    </div>
                </form>
            </div>

            <div class="card-panel">
                <h3><i class="fa fa-list"></i> Product Catalogue</h3>
                <table class="table">
                    <thead>
                        <tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th style="text-align:center;">Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if(count($products) > 0): ?>
                            <?php foreach($products as $p): ?>
                            <tr>
                                <td><img src="<?php echo !empty($p['image_path']) ? $p['image_path'] : 'uploads/default.png'; ?>" class="prod-img"></td>
                                <td><b><?php echo htmlspecialchars($p['product_name']); ?></b></td>
                                <td><span style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:bold;"><?php echo htmlspecialchars($p['category_name']); ?></span></td>
                                <td><b>Rs. <?php echo number_format($p['price'], 0); ?></b></td>
                                <td style="text-align:center;">
                                    <a href="pos_manage_products.php?edit_id=<?php echo $p['id']; ?>" style="color:#ffbc00; margin-right:10px;"><i class="fa fa-edit"></i> Edit</a> | 
                                    <a href="pos_manage_products.php?delete_id=<?php echo $p['id']; ?>" style="color:#dc3545; margin-left:10px;" onclick="return confirm('Delete this product?')"><i class="fa fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:30px;">Koi products available nahi hain.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>