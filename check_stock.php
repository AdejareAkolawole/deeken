<?php
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    
    $stmt = $conn->prepare("SELECT stock_quantity FROM inventory WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock = $result->fetch_assoc()['stock_quantity'] ?? 0;
    $stmt->close();
    
    echo json_encode(['stock' => $stock]);
} else {
    echo json_encode(['error' => 'Invalid request']);
}
$conn->close();
?>