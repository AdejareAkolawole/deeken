<?php
header('Content-Type: application/json');
session_start();

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => true,
        'user' => [
            'email' => $_SESSION['email'],
            'isAdmin' => $_SESSION['isAdmin']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
}
?>