<?php
require_once 'auth_functions.php';
require_once 'expense_functions.php';
require_once 'db.php';
checkSession();

// Hostinger collation issue permanent solution fix
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

$msg   = "";
$msg_type  = "ok"; // ok | err

// 1. ADD CATEGORY
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $res = createCategory($_POST['category_name']);
    if ($res === "SUCCESS") { 
        $msg = "Category successfully created!"; 
        $msg_type = "ok"; 
    } else { 
        $msg = $res; 
        $msg_type = "err"; 
    }
}

// 2. EDIT/UPDATE CATEGORY
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
    $cat_id   = intval($_POST['category_id']);
    $cat_name = trim($_POST['category_name']);
    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("UPDATE categories SET category_name=? WHERE id=?");
        if ($stmt->execute([$cat_name, $cat_id])) { 
            $msg = "Category updated successfully!"; 
            $msg_type = "ok"; 
        } else { 
            $msg = "Error: Category could not be updated."; 
            $msg_type = "err"; 
        }
    }
}

// 3. SAFE DELETE CATEGORY WITHOUT LOSING EXPENSE RECORDS
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    try {
        $pdo->beginTransaction();
        
        // Step A: Check karein ke 'Uncategorized' naam ki category pehle se hai ya nahi
        $chkDefault = $pdo->prepare("SELECT id FROM categories WHERE category_name = 'Uncategorized' LIMIT 1");
        $chkDefault->execute();
        $default_cat = $chkDefault->fetch();
        
        if ($default_cat) {
            $default_cat_id = $default_cat['id'];
        } else {
            // Agar nahi bani hui, to system khud bana dega
            $createDefault = $pdo->prepare("INSERT INTO categories (category_name) VALUES ('Uncategorized')");
            $createDefault->execute();
            $default_cat_id = $pdo->lastInsertId();
        }
        
        // Step B: Purani category ke expenses ko automatic 'Uncategorized' par shift karein (Data safe rahega)
        $update_expenses = $pdo->prepare("UPDATE expenses SET category_id = ? WHERE category_id = ?");
        $update_expenses->execute([$default_cat_id, $delete_id]);
        
        // Step C: Ab main category ko delete kar rahe hain
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            
            $pdo->commit(); // Save changes
            
            $current_page = basename($_SERVER['SCRIPT_NAME']); 
            header("Location: " . $current_page . "?msg=deleted");
            exit();
            
        } else {
            $pdo->rollBack();
            $msg = "Error: Category delete nahi ho saki.";
            $msg_type = "err";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Error: " . $e->getMessage();
        $msg_type = "err";
    }
}

// Check if redirected from a successful delete
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = "Category deleted successfully!";
    $msg_type = "ok";
}

// 4. FETCH ALL CATEGORIES
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Shared UI vars
$user_name     = htmlspecialchars($_SESSION['user_name'] ?? 'Muhammad Hamza');
$user_initials = strtoupper(substr($user_name, 0, 1));
$notif_count   = 0; $notifications = [];
if (function_exists('getActiveNotificationsForUser')) {
    $notifications = getActiveNotificationsForUser($_SESSION['user_id']);
    $notif_count   = count($notifications);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Expense Categories - Memon Biryani</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #fafafa; margin: 0; padding: 0; }
        .container { max-width: 700px; margin: 40px auto; background: white; padding: 30px; border: 1px solid #e6e6e6; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .form-group { margin-bottom: 15px; }
        .form-group input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; background: #007bff; color: white; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .table th { background: #f8fafc; }
        .action-btn { text-decoration: none; font-size: 13px; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; }
        .del-btn { color: #dc3545; }
        .del-btn svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2; }
    </style>
</head>
<body>

    <div class="container">
        <h3><i class="fa fa-folder"></i> Manage Expense Categories</h3>
        
        <?php if(!empty($msg)): ?>
            <div class="alert <?php echo ($msg_type == 'ok') ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-group">
            <input type="text" name="category_name" placeholder="Enter new category name..." required style="margin-bottom:10px;">
            <button type="submit" name="add_category" class="btn">Add Category</button>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($categories as $cat): ?>
                <tr>
                    <td><b><?php echo htmlspecialchars($cat['category_name']); ?></b></td>
                    <td>
                        <a href="<?php echo basename($_SERVER['SCRIPT_NAME']); ?>?delete_id=<?php echo $cat['id']; ?>" class="action-btn del-btn" onclick="return confirm('Kya aap waqai is category ko delete karna chahte hain? Purana saara record safe rahega.')">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>
</html>