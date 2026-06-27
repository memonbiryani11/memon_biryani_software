<?php
require_once 'auth_functions.php';
require_once 'expense_functions.php';
checkSession();

$userId = $_SESSION['user_id'];
$msg = "";
$alertId = "";
$alertDate = "";

// Handle Expense Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_expenses'])) {
    $res = insertExpenses($_POST['amounts'], $userId, $_POST['selected_date']);
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
    header("Location: insert_data.php");
    exit();
}

$categories = getAllCategories();
$notifications = getActiveNotificationsForUser($userId); 
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insert Expenses - Memon Biryani Software</title>
    <style>
        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 1000; display: flex; flex-direction: column; gap: 10px; font-family: Arial, sans-serif; }
        .toast-alert { background: #222; color: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-left: 5px solid #28a745; position: relative; width: 280px; }
        .toast-alert a { color: #fff; text-decoration: none; display: block; }
        .close-toast { position: absolute; top: 5px; right: 10px; background: none; border: none; color: #aaa; cursor: pointer; font-size: 16px; font-weight: bold; }
        .bell-modal { display: none; position: fixed; top: 60px; right: 20px; background: white; border: 1px solid #ccc; padding: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); border-radius: 5px; width: 340px; z-index: 999; font-family: Arial, sans-serif; }
        .notification-item { border-bottom: 1px solid #eee; padding: 8px 0; display: flex; justify-content: space-between; align-items: center; }
        
        /* Date Block Style */
        .date-container { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; }
        .date-field { display: flex; flex-direction: column; gap: 5px; }
        .date-field label { font-size: 12px; font-weight: bold; color: #555; }
        .date-field input { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; background: #fff; }
        .readonly-field { background: #e9ecef !important; cursor: not-allowed; }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <div style="display:flex; justify-content: space-between; align-items: center; background:#f4f4f4; padding:10px 20px; font-family: Arial;">
        <h2>Insert Daily Expenses</h2>
        <div>
            <button onclick="toggleBellModal()" style="padding: 8px 15px; cursor: pointer; font-weight: bold;">
                🔔 Bell (<?php echo count($notifications); ?>)
            </button>
            <a href="dashboard.php" style="margin-left: 15px; text-decoration: none; font-weight: bold; color: #007BFF;">Dashboard</a>
        </div>
    </div>

    <!-- Bell Dropdown Modal -->
    <div id="bellModal" class="bell-modal">
        <h4 style="margin-top: 0; margin-bottom: 10px;">Your Active Notifications</h4>
        <hr>
        <div style="max-height: 250px; overflow-y: auto;">
            <?php if(count($notifications) > 0): ?>
                <?php foreach($notifications as $notif): ?>
                    <div class="notification-item">
                        <a href="check.php?id=<?php echo $notif['id']; ?>" style="text-decoration:none; color:#333; width: 80%;">
                            <strong>ID: <?php echo $notif['id']; ?> | Date: <?php echo $notif['expense_date']; ?></strong><br>
                            <span style="font-size: 12px; color: #555;"><?php echo htmlspecialchars($notif['message']); ?></span>
                        </a>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="clear_notif_id" value="<?php echo $notif['id']; ?>">
                            <button type="submit" style="background:red; color:white; border:none; padding:4px 8px; border-radius:3px; cursor:pointer; font-size:11px;">Clear</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="font-size: 13px; color: #777;">Koi nayi notification nahi hai.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Form Block -->
    <div style="width: 60%; margin: 30px 20px; font-family: Arial;">
        <?php if(!empty($msg) && $msg !== "SUCCESS_ALERT"): ?>
            <p style="color:red; font-weight:bold;"><?php echo $msg; ?></p>
        <?php endif; ?>

        <form method="POST">
            
            <!-- 4 Automated Date Fields -->
            <div class="date-container">
                <div class="date-field">
                    <label>Select Date</label>
                    <input type="date" name="selected_date" id="selected_date" required onchange="autoFillDateParts()">
                </div>
                <div class="date-field">
                    <label>Day</label>
                    <input type="text" id="expense_day" readonly class="readonly-field" placeholder="Auto Day">
                </div>
                <div class="date-field">
                    <label>Month</label>
                    <input type="text" id="expense_month" readonly class="readonly-field" placeholder="Auto Month">
                </div>
                <div class="date-field">
                    <label>Year</label>
                    <input type="text" id="expense_year" readonly class="readonly-field" placeholder="Auto Year">
                </div>
            </div>

            <!-- Categories and Amount Input Fields -->
            <h3>Category Expense Items</h3>
            <?php foreach($categories as $cat): ?>
                <div style="margin-bottom: 12px; display: flex; gap: 10px;">
                    <input type="text" value="<?php echo htmlspecialchars($cat['category_name']); ?>" readonly style="padding: 8px; width: 200px; background: #e9ecef; border: 1px solid #ced4da;">
                    <input type="number" name="amounts[<?php echo $cat['id']; ?>]" placeholder="Enter Amount" step="0.01" style="padding: 8px; width: 150px; border: 1px solid #ced4da;">
                </div>
            <?php endforeach; ?>
            
            <?php if(count($categories) > 0): ?>
                <br>
                <button type="submit" name="submit_expenses" style="padding: 10px 20px; background: green; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Submit Expenses</button>
            <?php else: ?>
                <p>Koi category nahi mili. Pehle <a href="settings.php">Settings</a> me ja kar banayein.</p>
            <?php endif; ?>
        </form>
    </div>

    <!-- Alert Containers -->
    <div class="toast-container">
        <?php if($msg === "SUCCESS_ALERT"): ?>
            <div class="toast-alert" id="newInsertionAlert">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="clear_notif_id" value="<?php echo $alertId; ?>">
                    <button type="submit" class="close-toast">&times;</button>
                </form>
                <a href="check.php?id=<?php echo $alertId; ?>">
                    <strong style="color: #28a745;">✓ Data Inserted!</strong><br>
                    <span style="font-size: 12px;">ID: <?php echo $alertId; ?> | Date: <?php echo $alertDate; ?></span>
                </a>
            </div>
        <?php endif; ?>

        <?php foreach($notifications as $notif): ?>
            <?php if($notif['id'] != $alertId): ?>
                <div class="toast-alert" style="border-left: 5px solid #007BFF;">
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="clear_notif_id" value="<?php echo $notif['id']; ?>">
                        <button type="submit" class="close-toast">&times;</button>
                    </form>
                    <a href="check.php?id=<?php echo $notif['id']; ?>">
                        <strong>New Log Alert! (ID: <?php echo $notif['id']; ?>)</strong><br>
                        <span style="font-size: 12px; color:#ddd;"><?php echo htmlspecialchars($notif['message']); ?></span>
                    </a>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <script>
        function toggleBellModal() {
            var modal = document.getElementById('bellModal');
            modal.style.display = (modal.style.display === 'block') ? 'none' : 'block';
        }

        // JavaScript Auto Fill Logic for Day, Month, and Year
        function autoFillDateParts() {
            const dateInput = document.getElementById('selected_date').value;
            if(!dateInput) return;

            const dateObj = new Date(dateInput);
            
            // Days and Months Array Names mapping
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            // Fill Values instantly into readonly inputs
            document.getElementById('expense_day').value = days[dateObj.getDay()];
            document.getElementById('expense_month').value = months[dateObj.getMonth()];
            document.getElementById('expense_year').value = dateObj.getFullYear();
        }

        // Default set current date on page load
        window.onload = function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('selected_date').value = today;
            autoFillDateParts();
        }
    </script>
</body>
</html>