<?php
session_start();
if (!isset($_SESSION['operator_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}
include '../db/connection.php';

// Handle Add Operator
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_operator'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    // Check if username already exists
    $check = $conn->prepare("SELECT id FROM operators WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['op_error'] = "Username already exists. Please choose another.";
        header("Location: operators.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO operators (name, username, password, role) VALUES (?, ?, ?, ?)");
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt->bind_param("ssss", $name, $username, $hashedPassword, $role);
    $stmt->execute();
    header("Location: operators.php?success=1");
    exit();
}

// Handle Delete Operator
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id === intval($_SESSION['operator_id'])) {
        $_SESSION['op_error'] = "You cannot delete your own account while logged in.";
        header("Location: operators.php");
        exit();
    }

    // Prevent deleting the last remaining Admin
    $adminCount = $conn->query("SELECT COUNT(*) as cnt FROM operators WHERE role = 'Admin'")->fetch_assoc()['cnt'];
    $target = $conn->query("SELECT role FROM operators WHERE id = $id")->fetch_assoc();

    if ($target && $target['role'] === 'Admin' && $adminCount <= 1) {
        $_SESSION['op_error'] = "Cannot delete the last remaining Admin account.";
        header("Location: operators.php");
        exit();
    }

    try {
        $conn->query("DELETE FROM operators WHERE id = $id");
        header("Location: operators.php?deleted=1");
        exit();
    } catch (mysqli_sql_exception $e) {
        $_SESSION['op_error'] = "This operator has existing sales records and cannot be removed (to preserve sales history). You can add a new operator instead if this person no longer works here.";
        header("Location: operators.php");
        exit();
    }
}

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $id = intval($_POST['operator_id']);
    $newPassword = trim($_POST['new_password']);
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE operators SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $id);
    $stmt->execute();

    $_SESSION['op_success'] = "Password reset successfully.";
    header("Location: operators.php");
    exit();
}

$operators = $conn->query("SELECT * FROM operators ORDER BY role ASC, name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Operators - Pharmacy System</title>
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
        <h1>Manage Operators</h1>
        <button class="btn-add" onclick="document.getElementById('addModal').classList.add('active')">+ Add New Operator</button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">Operator added successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert-success">Operator removed successfully!</div>
    <?php endif; ?>
    <?php if (isset($_SESSION['op_success'])): ?>
        <div class="alert-success"><?php echo $_SESSION['op_success']; unset($_SESSION['op_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['op_error'])): ?>
        <div class="alert-error"><?php echo $_SESSION['op_error']; unset($_SESSION['op_error']); ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Joined</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $operators->fetch_assoc()): ?>
        <tr>
            <td>
                <?php echo htmlspecialchars($row['name']); ?>
                <?php if ($row['id'] == $_SESSION['operator_id']): ?>
                    <span class="you-tag">(You)</span>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['username']); ?></td>
            <td>
                <span class="badge <?php echo $row['role'] === 'Admin' ? 'badge-admin' : 'badge-cashier'; ?>">
                    <?php echo $row['role']; ?>
                </span>
            </td>
            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
            <td>
                <button type="button" class="action-link" style="background:none; border:none; cursor:pointer; font-family:inherit;" onclick="openResetModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>')">Reset Password</button>
                <?php if ($row['id'] != $_SESSION['operator_id']): ?>
                <a class="action-link" href="operators.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Remove this operator?');" style="margin-left:10px;">Remove</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <h3>Add New Operator</h3>
        <form method="POST" action="operators.php">
            <label>Full Name</label>
            <input type="text" name="name" required>

            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="text" name="password" required>

            <label>Role</label>
            <select name="role" required>
                <option value="Cashier">Cashier</option>
                <option value="Admin">Admin</option>
            </select>

            <div class="modal-actions">
                <button type="submit" name="add_operator" class="btn-save">Save Operator</button>
                <a href="operators.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="resetModal">
    <div class="modal-box">
        <h3>Reset Password for <span id="resetOperatorName"></span></h3>
        <form method="POST" action="operators.php">
            <input type="hidden" name="operator_id" id="resetOperatorId">

            <label>New Password</label>
            <input type="text" name="new_password" required>

            <div class="modal-actions">
                <button type="submit" name="reset_password" class="btn-save">Reset Password</button>
                <a href="operators.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(id, name) {
    document.getElementById('resetOperatorId').value = id;
    document.getElementById('resetOperatorName').textContent = name;
    document.getElementById('resetModal').classList.add('active');
}
</script>

</body>
</html>
