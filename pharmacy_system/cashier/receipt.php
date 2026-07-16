<?php
session_start();
if (!isset($_SESSION['operator_id'])) {
    header("Location: ../index.php");
    exit();
}
include '../db/connection.php';

$saleId = intval($_GET['id']);

$stmt = $conn->prepare("SELECT s.*, o.name as operator_name FROM sales s 
                         JOIN operators o ON s.operator_id = o.id 
                         WHERE s.id = ?");
$stmt->bind_param("i", $saleId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    die("Sale not found.");
}

$stmt = $conn->prepare("SELECT si.*, p.medicine_name FROM sale_items si 
                         JOIN products p ON si.product_id = p.id 
                         WHERE si.sale_id = ?");
$stmt->bind_param("i", $saleId);
$stmt->execute();
$items = $stmt->get_result();

$subtotal = $sale['total_amount'] + $sale['discount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?php echo $saleId; ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; font-family: 'Courier New', monospace; }
    body { background: #F1F5F3; padding: 30px; }
    .no-print { text-align:center; margin-bottom: 20px; }
    .no-print a, .no-print button {
        display:inline-block; margin: 0 5px; padding: 10px 20px; background:#2D6A4F; color:#fff;
        text-decoration:none; border:none; border-radius:6px; cursor:pointer; font-size:14px; font-family: 'Segoe UI', Arial, sans-serif;
    }
    .receipt {
        max-width: 380px; margin: 0 auto; background: #fff; padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 6px;
    }
    .receipt h2 { text-align:center; color:#1B4332; margin-bottom: 5px; }
    .receipt .sub { text-align:center; font-size:12px; color:#666; margin-bottom: 15px; }
    .divider { border-top: 1px dashed #999; margin: 12px 0; }
    .info-row { display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px; }
    table { width:100%; font-size:13px; margin: 10px 0; }
    th, td { text-align:left; padding: 4px 0; }
    th { border-bottom: 1px dashed #999; }
    .totals .info-row { font-size: 14px; }
    .grand-total { font-size:16px; font-weight:bold; color:#1B4332; }
    .footer-note { text-align:center; font-size:12px; color:#888; margin-top:15px; }

    @media print {
        .no-print { display:none; }
        body { background:#fff; padding:0; }
        .receipt { box-shadow:none; }
    }
</style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">🖨️ Print Receipt</button>
    <a href="billing.php">+ New Sale</a>
</div>

<div class="receipt">
    <h2>💊 City Pharmacy</h2>
    <div class="sub">Sale Receipt</div>
    <div class="divider"></div>

    <div class="info-row"><span>Receipt #</span><span>PH-<?php echo str_pad($saleId, 5, '0', STR_PAD_LEFT); ?></span></div>
    <div class="info-row"><span>Date</span><span><?php echo date('d-M-Y h:i A', strtotime($sale['sale_date'])); ?></span></div>
    <div class="info-row"><span>Operator</span><span><?php echo htmlspecialchars($sale['operator_name']); ?></span></div>
    <?php if ($sale['customer_name']): ?>
    <div class="info-row"><span>Customer</span><span><?php echo htmlspecialchars($sale['customer_name']); ?></span></div>
    <?php endif; ?>

    <div class="divider"></div>

    <table>
        <tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
        <?php while ($item = $items->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($item['medicine_name']); ?></td>
            <td><?php echo $item['quantity_sold']; ?></td>
            <td><?php echo number_format($item['price_at_sale'], 2); ?></td>
            <td><?php echo number_format($item['price_at_sale'] * $item['quantity_sold'], 2); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <div class="divider"></div>

    <div class="totals">
        <div class="info-row"><span>Subtotal</span><span>Rs. <?php echo number_format($subtotal, 2); ?></span></div>
        <div class="info-row"><span>Discount</span><span>Rs. <?php echo number_format($sale['discount'], 2); ?></span></div>
        <div class="info-row"><span>Payment Method</span><span><?php echo htmlspecialchars($sale['payment_method']); ?></span></div>
        <div class="divider"></div>
        <div class="info-row grand-total"><span>Total</span><span>Rs. <?php echo number_format($sale['total_amount'], 2); ?></span></div>
    </div>

    <div class="footer-note">Thank you for your purchase!</div>
</div>

</body>
</html>
