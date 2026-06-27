<?php
require_once 'auth_functions.php';
require_once 'expense_functions.php'; 
require_once 'db.php';
checkSession();

$msg = "";
$msg_color = "green";

// 1. DELETE RECORD LOGIC
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $msg = "Record kamyabi se delete kar diya gaya!";
            $msg_color = "green";
        } else {
            $msg = "Error: Record delete nahi ho saka.";
            $msg_color = "red";
        }
    } catch (Exception $e) {
        $msg = "Error: " . $e->getMessage();
        $msg_color = "red";
    }
}

// 2. INLINE UPDATE AMOUNT LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expense_amount'])) {
    $expense_id = intval($_POST['expense_id']);
    $new_amount = floatval($_POST['amount']);
    
    $stmt = $pdo->prepare("UPDATE expenses SET amount = ? WHERE id = ?");
    if ($stmt->execute([$new_amount, $expense_id])) {
        $msg = "Amount kamyabi se update ho gayi!";
        $msg_color = "green";
    } else {
        $msg = "Error: Amount update nahi ho saki.";
        $msg_color = "red";
    }
}

// 3. FETCH ALL RECORDS
$query = "
    SELECT 
        e.id AS expense_row_id,
        c.category_name,
        e.amount,
        e.date,
        e.expense_day,
        e.expense_month,
        e.expense_year
    FROM expenses e
    JOIN categories c ON e.category_id = c.id
    ORDER BY e.date DESC, e.id DESC
";
$records = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Records - Memon Biryani Software</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #fdfdfd; }
        
        .top-navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: white; border-bottom: 1px solid #eef0f2; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .brand-section { display: flex; align-items: center; gap: 15px; }
        .brand-section h2 { margin: 0; font-family: 'Georgia', serif; font-size: 22px; font-weight: bold; }
        .brand-section span.user-tag { color: #d4af37; font-weight: bold; font-style: italic; font-size: 15px; }
        .nav-links-wrapper { display: flex; align-items: center; gap: 20px; }
        .nav-links { display: flex; gap: 20px; font-size: 14px; align-items: center; }
        .nav-links a { text-decoration: none; color: #444; }
        .nav-links a.active { font-weight: bold; color: #000; border-bottom: 2px solid #000; padding-bottom: 3px; }
        
        .container { padding: 40px; max-width: 1300px; margin: 0 auto; }
        h1.page-title { font-size: 28px; font-weight: 700; margin: 0 0 5px 0; color: #111; }
        p.page-subtitle { color: #666; margin: 0 0 30px 0; font-size: 14px; }
        
        .msg-box { padding: 12px; border-radius: 4px; font-weight: bold; margin-bottom: 20px; font-size: 14px; }
        
        .records-table { width: 100%; border-collapse: collapse; background: white; border: 1px solid #e6e6e6; font-size: 14px; }
        .records-table th { background-color: #f8f9fa; border-bottom: 2px solid #dddddd; padding: 12px 15px; text-align: left; font-weight: bold; color: #000; }
        .records-table td { border-bottom: 1px solid #eeeeee; padding: 15px; color: #333; vertical-align: middle; transition: background-color 0.5s ease; }
        .records-table tr:hover { background-color: #fafafa; }
        
        .action-flex { display: flex; gap: 6px; align-items: center; }
        .action-btn { text-decoration: none; border: none; width: 32px; height: 32px; border-radius: 4px; display: flex; justify-content: center; align-items: center; font-size: 15px; cursor: pointer; color: white; transition: background 0.2s; }
        .btn-view { background: #00a2b8; }
        .btn-edit { background: #ffbc00; color: black; }
        .btn-delete { background: #dc3545; }
        
        .inline-input { width: 90px; padding: 5px; border: 1px solid #ccc; border-radius: 3px; font-size: 13px; }
        .inline-save-btn { background: #28a745; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer; margin-left: 3px; font-size: 12px; }
        .inline-cancel-btn { background: #6c757d; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer; margin-left: 2px; font-size: 12px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content-box { background: #f8f9fa; width: 90%; max-width: 700px; max-height: 85vh; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; animation: fadeIn 0.3s ease-out; display: flex; flex-direction: column; }
        .modal-header { padding: 15px 25px; background: white; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .modal-header h3 { margin: 0; font-size: 18px; color: #333; }
        .close-modal-btn { background: none; border: none; font-size: 24px; font-weight: bold; color: #aaa; cursor: pointer; line-height: 1; }
        .close-modal-btn:hover { color: #dc3545; }
        .modal-body { padding: 20px; overflow-y: auto; flex-grow: 1; }
        
        /* Highlight Specific Row Styling Rule */
        .highlighted-row-blink {
            background-color: #fff3cd !important; /* Premium Warm Yellow Highlight Hint */
            border: 2px solid #ffc107 !important;
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <div class="top-navbar">
        <div class="brand-section">
            <h2>Memon Biryani</h2>
            <span class="user-tag"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Muhammad Hamza'); ?></span>
        </div>
        <div class="nav-links-wrapper">
            <div class="nav-links">
                <a href="insert_data.php">Insert</a>
                <a href="records.php" class="active">Records</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="settings.php">Settings</a>
                
                <?php include 'notifications_panel.php'; ?>
                
                <a href="logout.php" style="color: #dc3545; margin-left: 10px;">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">All Records</h1>
        <p class="page-subtitle">Open the Records Panel to View and Manage All Records</p>

        <?php if(!empty($msg)): ?>
            <div class="msg-box" style="border-left: 5px solid <?php echo $msg_color; ?>; background: #f4f4f4; color: <?php echo $msg_color; ?>;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <table class="records-table">
            <thead>
                <tr>
                    <th style="width: 6%;">S.No</th>
                    <th style="width: 24%;">Name</th>
                    <th style="width: 15%;">Amount</th>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 12%;">Day</th>
                    <th style="width: 12%;">Month</th>
                    <th style="width: 8%;">Year</th>
                    <th style="width: 8%;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($records) > 0): $sno = 1; ?>
                    <?php foreach($records as $row): ?>
                        <tr id="row-<?php echo $row['expense_row_id']; ?>">
                            <td><b><?php echo $sno++; ?></b></td>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td>
                                <span id="amt-text-<?php echo $row['expense_row_id']; ?>">
                                    Rs. <?php echo number_format($row['amount'], 2); ?>
                                </span>
                                
                                <form method="POST" id="amt-form-<?php echo $row['expense_row_id']; ?>" style="display:none; margin:0;">
                                    <input type="hidden" name="expense_id" value="<?php echo $row['expense_row_id']; ?>">
                                    <input type="number" name="amount" step="0.01" class="inline-input" value="<?php echo $row['amount']; ?>" required>
                                    <button type="submit" name="update_expense_amount" class="inline-save-btn">✔</button>
                                    <button type="button" onclick="cancelInlineEdit(<?php echo $row['expense_row_id']; ?>)" class="inline-cancel-btn">X</button>
                                </form>
                            </td>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo !empty($row['expense_day']) ? htmlspecialchars($row['expense_day']) : date('l', strtotime($row['date'])); ?></td>
                            <td><?php echo !empty($row['expense_month']) ? htmlspecialchars($row['expense_month']) : date('F', strtotime($row['date'])); ?></td>
                            <td><?php echo ($row['expense_year'] > 0) ? $row['expense_year'] : date('Y', strtotime($row['date'])); ?></td>
                            <td>
                                <div class="action-flex">
                                    <button type="button" class="action-btn btn-view" title="Preview Invoice" onclick="openInvoiceModal(<?php echo $row['expense_row_id']; ?>)">👁</button>
                                    <button type="button" class="action-btn btn-edit" title="Edit Amount" onclick="triggerInlineEdit(<?php echo $row['expense_row_id']; ?>)">✏</button>
                                    <a href="records.php?delete_id=<?php echo $row['expense_row_id']; ?>" class="action-btn btn-delete" title="Delete Record" onclick="return confirm('Kya aap waqai is expense row ko delete karna chahte hain?')">🗑</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #777; padding: 30px;">Koi records nahi mile.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="invoiceModalOverlay" class="modal-overlay" onclick="closeInvoiceModal(event)">
        <div class="modal-content-box" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>📄 Invoice Preview Panel</h3>
                <button type="button" class="close-modal-btn" onclick="hideModal()">&times;</button>
            </div>
            <div id="modalBodyContainer" class="modal-body">
                <p style="text-align: center; color: #666;">Loading Invoice Details...</p>
            </div>
        </div>
    </div>

    <script>
        function triggerInlineEdit(id) {
            document.getElementById('amt-text-' + id).style.display = 'none';
            document.getElementById('amt-form-' + id).style.display = 'inline-block';
        }

        function cancelInlineEdit(id) {
            document.getElementById('amt-text-' + id).style.display = 'inline';
            document.getElementById('amt-form-' + id).style.display = 'none';
        }

        function openInvoiceModal(expenseId) {
            const overlay = document.getElementById('invoiceModalOverlay');
            const bodyContainer = document.getElementById('modalBodyContainer');
            overlay.style.display = 'flex';
            bodyContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">🔄 Fetching Data From check.php...</p>';

            fetch('check.php?id=' + expenseId)
                .then(response => response.text())
                .then(htmlContent => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(htmlContent, 'text/html');
                    const invoiceCard = doc.querySelector('.invoice-card');
                    if(invoiceCard) {
                        bodyContainer.innerHTML = '';
                        bodyContainer.appendChild(invoiceCard);
                    } else {
                        bodyContainer.innerHTML = '<p style="text-align: center; color: red; padding: 20px;">⚠️ Format Error: Invoice layout not found.</p>';
                    }
                })
                .catch(error => {
                    bodyContainer.innerHTML = '<p style="text-align: center; color: red; padding: 20px;">⚠️ Error loading data from check.php</p>';
                });
        }

        function hideModal() { document.getElementById('invoiceModalOverlay').style.display = 'none'; }
        function closeInvoiceModal(event) { hideModal(); }

        // AUTOMATIC SCROLL AND HIGHLIGHT TRUGGER LOGIC ENGINE
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const highlightId = urlParams.get('highlight_id');
            
            if (highlightId) {
                const targetRow = document.getElementById('row-' + highlightId);
                if (targetRow) {
                    // Smoothly scroll element to center viewport perspective 
                    targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Inject styling class components 
                    targetRow.classList.add('highlighted-row-blink');
                    
                    // Fade out highlight back to normal state smoothly after 4 seconds
                    setTimeout(() => {
                        targetRow.classList.remove('highlighted-row-blink');
                    }, 4000);
                }
            }
        });
    </script>
</body>
</html>