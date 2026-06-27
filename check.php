<?php
require_once 'auth_functions.php';
require_once 'db.php';
checkSession();

$msg = "";
// URL se expense_id uthayen jo hamne records page se bheja hai
$expense_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($expense_id > 0) {
    // Specific clicked row ka data extract karein
    $stmt = $pdo->prepare("
        SELECT e.*, c.category_name 
        FROM expenses e
        JOIN categories c ON e.category_id = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$expense_id]);
    $item = $stmt->fetch();

    if (!$item) {
        $msg = "Error: Is ID ka koi expense record nahi mila.";
    }
} else {
    $msg = "Error: Invalid Record ID.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoice View - Memon Biryani Software</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 40px 20px; background: #f8f9fa; }
        
        /* Main Invoice Card Styling matching image_83aa76.png */
        .invoice-card { 
            max-width: 650px; 
            margin: 0 auto; 
            background: white; 
            border: 1px solid #dee2e6; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            position: relative;
        }
        .logo-section { text-align: center; margin-bottom: 10px; }
        .logo-section h2 { margin: 5px 0; font-size: 24px; font-weight: bold; font-family: 'Georgia', serif; }
        .logo-section span { font-size: 12px; font-style: italic; color: #555; }
        .invoice-title { text-align: center; font-size: 20px; font-weight: bold; letter-spacing: 1px; margin-bottom: 25px; color: #111; }
        
        /* Grid Invoice Table System */
        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 15px; }
        .invoice-table td { border: 1px solid #ced4da; padding: 12px 15px; text-align: left; }
        .invoice-table td:first-child { font-weight: bold; background: #fafafa; width: 35%; }
        
        /* Signature & Actions Elements */
        .signature-container { text-align: center; margin-top: 40px; margin-bottom: 30px; }
        .signature-line { width: 200px; border-bottom: 2px solid #aaa; margin: 0 auto 8px auto; }
        .signature-text { font-size: 14px; font-weight: bold; color: #333; }
        
        /* Card Ke Andar Action Controls */
        .actions-bar { display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px; }
        .print-btn { background: #f8f9fa; border: 1px solid #ccc; padding: 8px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .print-btn:hover { background: #e9ecef; }
        
        /* Sirf Back Arrow Ka Link - No Text */
        .back-arrow-link { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            background: #f8f9fa; 
            border: 1px solid #ccc; 
            padding: 8px 15px; 
            border-radius: 4px; 
            color: #333; 
            text-decoration: none; 
            font-weight: bold; 
            font-size: 16px; 
            transition: background 0.2s;
        }
        .back-arrow-link:hover { background: #e9ecef; color: #007BFF; }
        
        @media print {
            body { background: white; padding: 0; }
            .invoice-card { border: none; box-shadow: none; padding: 0; }
            .actions-bar { display: none; }
        }
    </style>
</head>
<body>

    <?php if(!empty($msg)): ?>
        <div style="text-align:center; color:red; font-weight:bold; margin-top: 50px;">
            <p><?php echo $msg; ?></p>
            <a href="records.php" style="color: #007BFF; text-decoration: none;">←</a>
        </div>
    <?php exit(); endif; ?>

    <div class="invoice-card">
        
        <div class="logo-section">
            <h2>Memon Biryani</h2>
            <span>A Legacy of Flavor</span>
        </div>
        <div class="invoice-title">INVOICE</div>

        <table class="invoice-table">
            <tr>
                <td>Invoice ID:</td>
                <td><?php echo $item['id']; ?></td>
            </tr>
            <tr>
                <td>Name:</td>
                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
            </tr>
            <tr>
                <td>Amount:</td>
                <td>Rs. <?php echo number_format($item['amount'], 2); ?></td>
            </tr>
            <tr>
                <td>Date:</td>
                <td><?php echo $item['date']; ?></td>
            </tr>
            <tr>
                <td>Day:</td>
                <td><?php echo !empty($item['expense_day']) ? htmlspecialchars($item['expense_day']) : date('l', strtotime($item['date'])); ?></td>
            </tr>
            <tr>
                <td>Month:</td>
                <td><?php echo !empty($item['expense_month']) ? htmlspecialchars($item['expense_month']) : date('F', strtotime($item['date'])); ?></td>
            </tr>
            <tr>
                <td>Year:</td>
                <td><?php echo ($item['expense_year'] > 0) ? $item['expense_year'] : date('Y', strtotime($item['date'])); ?></td>
            </tr>
        </table>

        <div class="signature-container">
            <div class="signature-line"></div>
            <div class="signature-text">Authorized Signature</div>
        </div>

        <div class="actions-bar">
            <a href="records.php" class="back-arrow-link" title="Back to Records">←</a>
            
            <button class="print-btn" onclick="window.print()">Print Invoice</button>
        </div>

    </div>

</body>
</html>