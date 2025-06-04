<?php
$host = 'localhost';
$db = 'deekenb';
$user = 'root';
$pass = ''; // Default for XAMPP; update if set

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}
?>