<?php
require_once 'config.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) >= 2) {
    $stmt = $conn->prepare("SELECT id, name, image FROM products WHERE name LIKE ? LIMIT 10");
    $searchTerm = "%$query%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'image' => $row['image']
        ];
    }
    $stmt->close();
    echo json_encode($products);
} else {
    echo json_encode([]);
}
?>