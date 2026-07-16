<?php
session_start();
if (!isset($_SESSION['operator_id'])) {
    http_response_code(403);
    exit();
}
include '../db/connection.php';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$results = [];

if (strlen($term) >= 1) {
    $searchTerm = "%" . $term . "%";
    $stmt = $conn->prepare("SELECT id, medicine_name, category, price, quantity, expiry_date 
                             FROM products 
                             WHERE medicine_name LIKE ? AND quantity > 0 
                             ORDER BY medicine_name ASC 
                             LIMIT 10");
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $isExpired = strtotime($row['expiry_date']) < strtotime(date('Y-m-d'));
        if (!$isExpired) {
            $results[] = $row;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($results);
?>
