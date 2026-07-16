<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['operator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

include '../db/connection.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit();
}

$items = $data['items'];
$discount = floatval($data['discount']);
$paymentMethod = $data['payment_method'];
$customerName = trim($data['customer_name']);
$operatorId = $_SESSION['operator_id'];

$allowedMethods = ['Cash', 'Bank Transfer', 'Easypaisa', 'JazzCash'];
if (!in_array($paymentMethod, $allowedMethods)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
    exit();
}

$conn->begin_transaction();

try {
    $subtotal = 0;

    // Step 1: Validate stock for every item first
    foreach ($items as $item) {
        $productId = intval($item['id']);
        $qty = intval($item['qty']);

        $stmt = $conn->prepare("SELECT quantity, price, medicine_name FROM products WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        if (!$product) {
            throw new Exception("Medicine not found.");
        }
        if ($product['quantity'] < $qty) {
            throw new Exception("Not enough stock for " . $product['medicine_name'] . ". Only " . $product['quantity'] . " left.");
        }

        $subtotal += $product['price'] * $qty;
    }

    $total = $subtotal - $discount;
    if ($total < 0) $total = 0;

    // Step 2: Insert into sales table
    $stmt = $conn->prepare("INSERT INTO sales (total_amount, discount, payment_method, operator_id, customer_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ddsis", $total, $discount, $paymentMethod, $operatorId, $customerName);
    $stmt->execute();
    $saleId = $conn->insert_id;

    // Step 3: Insert sale_items and update stock
    foreach ($items as $item) {
        $productId = intval($item['id']);
        $qty = intval($item['qty']);
        $price = floatval($item['price']);

        $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity_sold, price_at_sale) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $saleId, $productId, $qty, $price);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
        $stmt->bind_param("ii", $qty, $productId);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'sale_id' => $saleId]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
