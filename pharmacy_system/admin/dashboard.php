<?php
session_start();
if (!isset($_SESSION['operator_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}
include '../db/connection.php';

// Total products
$totalProducts = $conn->query("SELECT COUNT(*) as cnt FROM products")->fetch_assoc()['cnt'];

// Today's sales total
$today = date('Y-m-d');
$todaySalesResult = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE DATE(sale_date) = '$today'");
$todaySales = $todaySalesResult->fetch_assoc()['total'];

// Low stock count
$lowStockResult = $conn->query("SELECT COUNT(*) as cnt FROM products WHERE quantity <= low_stock_threshold");
$lowStockCount = $lowStockResult->fetch_assoc()['cnt'];

// Total operators
$totalOperators = $conn->query("SELECT COUNT(*) as cnt FROM operators")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Pharmacy System</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav>
    <h2>💊 Pharmacy Admin Panel</h2>
    <div>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="welcome">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['operator_name']); ?></h1>
        <p>Here's what's happening in your pharmacy today.</p>
    </div>

    <div class="stats">
        <div class="stat-card">
            <h3>Total Products</h3>
            <div class="value"><?php echo $totalProducts; ?></div>
        </div>
        <div class="stat-card">
            <h3>Today's Sales</h3>
            <div class="value">Rs. <?php echo number_format($todaySales, 2); ?></div>
        </div>
        <div class="stat-card alert">
            <h3>Low Stock Items</h3>
            <div class="value"><?php echo $lowStockCount; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Operators</h3>
            <div class="value"><?php echo $totalOperators; ?></div>
        </div>
    </div>

    <div class="menu">
        <a href="products.php">📦 Manage Products</a>
        <a href="sales_report.php">📊 Sales Report</a>
        <a href="operators.php">👤 Manage Operators</a>
        <a href="../cashier/billing.php">🧾 New Sale (Billing)</a>
    </div>
</div>

</body>
</html>
