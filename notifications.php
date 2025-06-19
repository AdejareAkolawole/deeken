<?php
include 'config.php';
session_start();

if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $notification_id = (int)$_POST['notification_id'];
    $user_id = getCurrentUser()['id'];
    
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Mark notification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read.']);
    }
    exit;
}
?>