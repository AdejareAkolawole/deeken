<?php
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$query = isset($_GET['q']) ? sanitize($conn, $_GET['q']) : '';
$results = [];

if (strlen($query) >= 2) {
    $stmt = $conn->prepare("SELECT id, name FROM products WHERE name LIKE ? LIMIT 10");
    $search_term = "%$query%";
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
    $stmt->close();
}

echo json_encode($results);
$conn->close();
?>