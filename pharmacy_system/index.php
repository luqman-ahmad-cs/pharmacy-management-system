<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pharmacy Management System - Login</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-wrapper">
<div class="login-box">
    <h2>💊 Pharmacy System</h2>

    <?php
    session_start();
    if (isset($_SESSION['login_error'])) {
        echo '<div class="error-msg">' . $_SESSION['login_error'] . '</div>';
        unset($_SESSION['login_error']);
    }
    ?>

    <form action="login_process.php" method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
</div>
</div>

</body>
</html>
