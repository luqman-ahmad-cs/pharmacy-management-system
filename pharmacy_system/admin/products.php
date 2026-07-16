<?php
session_start();
if (!isset($_SESSION['operator_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}
include '../db/connection.php';

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['medicine_name']);
    $category = trim($_POST['category']);
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $expiry = $_POST['expiry_date'];
    $threshold = $_POST['low_stock_threshold'];

    $stmt = $conn->prepare("INSERT INTO products (medicine_name, category, price, quantity, expiry_date, low_stock_threshold) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdisi", $name, $category, $price, $quantity, $expiry, $threshold);
    $stmt->execute();
    header("Location: products.php?success=1");
    exit();
}

// Handle Delete Product
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM products WHERE id = $id");
    header("Location: products.php?deleted=1");
    exit();
}

// Fetch all products
$products = $conn->query("SELECT * FROM products ORDER BY medicine_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Products - Pharmacy System</title>
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
    <div class="top-bar">
        <h1>Manage Products</h1>
        <button class="btn-add" onclick="document.getElementById('addModal').classList.add('active')">+ Add New Medicine</button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">Medicine added successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert-success">Medicine deleted successfully!</div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Medicine Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Expiry Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $products->fetch_assoc()):
            $isLowStock = $row['quantity'] <= $row['low_stock_threshold'];
            $isExpired = strtotime($row['expiry_date']) < strtotime(date('Y-m-d'));
            $rowClass = $isExpired ? 'expired' : ($isLowStock ? 'low-stock' : '');
        ?>
        <tr class="<?php echo $rowClass; ?>">
            <td><?php echo htmlspecialchars($row['medicine_name']); ?></td>
            <td><?php echo htmlspecialchars($row['category']); ?></td>
            <td>Rs. <?php echo number_format($row['price'], 2); ?></td>
            <td><?php echo $row['quantity']; ?></td>
            <td><?php echo $row['expiry_date']; ?></td>
            <td>
                <?php if ($isExpired): ?>
                    <span class="badge badge-expired">Expired</span>
                <?php elseif ($isLowStock): ?>
                    <span class="badge badge-low">Low Stock</span>
                <?php else: ?>
                    <span style="color:#2D6A4F; font-size:13px;">OK</span>
                <?php endif; ?>
            </td>
            <td>
                <a class="action-link" href="products.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this medicine?');">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<!-- Add Product Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <h3>Add New Medicine</h3>
        <form method="POST" action="products.php">
            <label>Medicine Name</label>
            <input type="text" name="medicine_name" required>

            <label>Category</label>
            <input type="text" name="category" placeholder="e.g. Tablet, Syrup, Injection" required>

            <label>Price (Rs.)</label>
            <input type="number" step="0.01" name="price" required>

            <label>Quantity</label>
            <input type="number" name="quantity" required>

            <label>Expiry Date</label>
            <input type="date" name="expiry_date" required>

            <label>Low Stock Alert Threshold</label>
            <input type="number" name="low_stock_threshold" value="10" required>

            <div class="modal-actions">
                <button type="submit" name="add_product" class="btn-save">Save Medicine</button>
                <a href="products.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
