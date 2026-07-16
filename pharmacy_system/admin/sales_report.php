<?php
session_start();
if (!isset($_SESSION['operator_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}
include '../db/connection.php';

// Last 7 days sales (for line chart)
$dailySales = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $result = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE DATE(sale_date) = '$date'");
    $total = $result->fetch_assoc()['total'];
    $dailySales[] = ['date' => date('d M', strtotime($date)), 'total' => floatval($total)];
}

// Top 5 selling medicines (all time)
$topMedicines = $conn->query("
    SELECT p.medicine_name, SUM(si.quantity_sold) as total_sold
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    GROUP BY si.product_id
    ORDER BY total_sold DESC
    LIMIT 5
");
$topMedNames = [];
$topMedQty = [];
while ($row = $topMedicines->fetch_assoc()) {
    $topMedNames[] = $row['medicine_name'];
    $topMedQty[] = intval($row['total_sold']);
}

// Payment method breakdown
$paymentBreakdown = $conn->query("
    SELECT payment_method, COUNT(*) as cnt, SUM(total_amount) as total
    FROM sales
    GROUP BY payment_method
");
$paymentLabels = [];
$paymentTotals = [];
while ($row = $paymentBreakdown->fetch_assoc()) {
    $paymentLabels[] = $row['payment_method'];
    $paymentTotals[] = floatval($row['total']);
}

// Summary stats
$totalRevenue = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM sales")->fetch_assoc()['t'];
$totalOrders = $conn->query("SELECT COUNT(*) as c FROM sales")->fetch_assoc()['c'];
$totalDiscount = $conn->query("SELECT COALESCE(SUM(discount),0) as d FROM sales")->fetch_assoc()['d'];
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Recent transactions table
$recentSales = $conn->query("
    SELECT s.id, s.sale_date, s.total_amount, s.payment_method, s.customer_name, o.name as operator_name
    FROM sales s
    JOIN operators o ON s.operator_id = o.id
    ORDER BY s.sale_date DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Report - Pharmacy System</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav>
    <h2>💊 Pharmacy Admin Panel</h2>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <h1>📊 Sales Report</h1>

    <div class="stats">
        <div class="stat-card">
            <h3>Total Revenue</h3>
            <div class="value">Rs. <?php echo number_format($totalRevenue, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Orders</h3>
            <div class="value"><?php echo $totalOrders; ?></div>
        </div>
        <div class="stat-card">
            <h3>Avg Order Value</h3>
            <div class="value">Rs. <?php echo number_format($avgOrderValue, 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Discounts Given</h3>
            <div class="value">Rs. <?php echo number_format($totalDiscount, 2); ?></div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <h3>Last 7 Days Sales</h3>
            <canvas id="dailySalesChart" height="100"></canvas>
        </div>
        <div class="chart-card">
            <h3>Payment Methods</h3>
            <canvas id="paymentChart" height="100"></canvas>
        </div>
    </div>

    <div class="chart-card" style="margin-bottom:25px;">
        <h3>Top 5 Selling Medicines</h3>
        <canvas id="topMedChart" height="80"></canvas>
    </div>

    <div class="chart-card">
        <h3>Recent Transactions</h3>
        <table>
            <tr>
                <th>Receipt #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Operator</th>
                <th>Payment</th>
                <th>Amount</th>
            </tr>
            <?php while ($row = $recentSales->fetch_assoc()): ?>
            <tr>
                <td>PH-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo date('d M, h:i A', strtotime($row['sale_date'])); ?></td>
                <td><?php echo $row['customer_name'] ? htmlspecialchars($row['customer_name']) : '—'; ?></td>
                <td><?php echo htmlspecialchars($row['operator_name']); ?></td>
                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                <td>Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script>
// Daily Sales Line Chart
new Chart(document.getElementById('dailySalesChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($dailySales, 'date')); ?>,
        datasets: [{
            label: 'Sales (Rs.)',
            data: <?php echo json_encode(array_column($dailySales, 'total')); ?>,
            borderColor: '#2D6A4F',
            backgroundColor: 'rgba(45,106,79,0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

// Payment Method Pie Chart
new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($paymentLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($paymentTotals); ?>,
            backgroundColor: ['#2D6A4F', '#40916C', '#74C69D', '#95D5B2']
        }]
    },
    options: { responsive: true }
});

// Top Medicines Bar Chart
new Chart(document.getElementById('topMedChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($topMedNames); ?>,
        datasets: [{
            label: 'Units Sold',
            data: <?php echo json_encode($topMedQty); ?>,
            backgroundColor: '#2D6A4F'
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>

</body>
</html>
