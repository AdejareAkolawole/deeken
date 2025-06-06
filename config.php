<?php
// ----- SESSION MANAGEMENT -----
session_start();

// ----- DATABASE CONNECTION -----
$conn = new mysqli("localhost", "root", "", "deeken_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----- PHPMailer CONFIGURATION -----
require 'vendor/autoload.php'; // Assuming PHPMailer is installed via Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendResetEmail($email, $token) {
    $mail = new PHPMailer(true);
    try {
        // SMTP settings (example for Gmail)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your_email@gmail.com'; // Your SMTP email
        $mail->Password = 'your_app_password'; // Your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email settings
        $mail->setFrom('your_email@gmail.com', 'Deeken Support');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Deeken Password Reset';
        $reset_link = "http://localhost/deeken/reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token);
        $mail->Body = "
            <h2>Password Reset Request</h2>
            <p>You requested to reset your password for your Deeken account. Click the link below to reset your password:</p>
            <p><a href='$reset_link'>Reset Password</a></p>
            <p>This link is valid for 1 hour. If you did not request this, please ignore this email.</p>
            <p>Best regards,<br>Deeken Team</p>
        ";
        $mail->AltBody = "You requested to reset your password for your Deeken account. Visit this link to reset your password: $reset_link\nThis link is valid for 1 hour. If you did not request this, please ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send reset email: {$mail->ErrorInfo}");
        return false;
    }
}

// ----- UTILITY FUNCTIONS -----
// Get the current logged-in user
function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Get cart item count for the logged-in user
function getCartCount($conn, $user) {
    if (!$user) return 0;
    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['count'] ?? 0;
}

// Sanitize input for security
function sanitize($conn, $input) {
    return htmlspecialchars($conn->real_escape_string($input));
}

// Authenticate user
function authenticateUser($conn, $email, $password) {
    $stmt = $conn->prepare("SELECT id, email, password, address, phone, is_admin FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

// Check if user is admin
function isAdmin($user) {
    return $user && $user['is_admin'];
}

// Redirect if not logged in
function requireLogin() {
    if (!getCurrentUser()) {
        header("Location: login.php");
        exit;
    }
}

// Redirect if not admin
function requireAdmin() {
    $user = getCurrentUser();
    if (!$user || !isAdmin($user)) {
        header("Location: index.php");
        exit;
    }
}

// Generate secure token for password reset
function generateResetToken() {
    return bin2hex(random_bytes(32));
}
?>