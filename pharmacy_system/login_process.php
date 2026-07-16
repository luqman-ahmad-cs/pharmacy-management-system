<?php
session_start();
include 'db/connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Fetch operator by username
    $stmt = $conn->prepare("SELECT id, name, username, password, role FROM operators WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $operator = $result->fetch_assoc();

        // Secure password check using password_hash/password_verify
        if (password_verify($password, $operator['password'])) {
            $_SESSION['operator_id'] = $operator['id'];
            $_SESSION['operator_name'] = $operator['name'];
            $_SESSION['role'] = $operator['role'];

            // Redirect based on role
            if ($operator['role'] === 'Admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: cashier/billing.php");
            }
            exit();
        } else {
            $_SESSION['login_error'] = "Incorrect password. Please try again.";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "Username not found.";
        header("Location: index.php");
        exit();
    }
}
?>
