<?php
// Database configuration
$host = "localhost";
$db_username = "root";
$db_password = "";
$db_name = "pharmacy_db";

// Create connection
$conn = new mysqli($host, $db_username, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>