<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || count($input) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Empty dataset submission payload context']);
    exit();
}

try {
    $pdo->beginTransaction();

    $invoice_no = "INV-" . time() . rand(10, 99);
    $total_bill = 0;
    $current_date = date('Y-m-d');

    foreach($input as $name => $meta) {
        $total_bill += ($meta['price'] * $meta['qty']);
    }

    // Insert to sell_records
    $stmt = $pdo->prepare("INSERT INTO sell_records (invoice_no, total_amount, sell_date) VALUES (?, ?, ?)");
    $stmt->execute([$invoice_no, $total_bill, $current_date]);
    $master_id = $pdo->lastInsertId();

    // Insert breakdowns to sell_items
    $item_stmt = $pdo->prepare("INSERT INTO sell_items (sell_record_id, product_name, price, quantity) VALUES (?, ?, ?, ?)");
    foreach($input as $name => $meta) {
        $item_stmt->execute([$master_id, $name, $meta['price'], $meta['qty']]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'invoice' => $invoice_no]);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}
?>