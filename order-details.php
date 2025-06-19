<?php
// ----- INITIALIZATION -----
include 'config.php';


// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, email, full_name, is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    header("Location: login.php");
    exit;
}

// Get cart count (assumed defined in config.php)
$cart_count = getCartCount($conn, $user);

// Get order ID from URL
$order_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order']) && $user) {
    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status IN ('pending', 'processing')");
    $stmt->bind_param("ii", $order_id, $user['id']);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Order cancelled successfully.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to cancel order. It may have already been shipped or delivered.'];
    }
    $stmt->close();
    header("Location: order_details.php?id=$order_id");
    exit;
}

// Fetch order details
$order = null;
$address = null;
$payment = null;
$items = [];
if ($order_id > 0) {
    // Get order
    $stmt = $conn->prepare("SELECT o.id, o.total, o.delivery_fee, o.status, o.created_at, o.address_id, o.payment_id 
                            FROM orders o 
                            WHERE o.id = ? AND o.user_id = ?");
    $stmt->bind_param("ii", $order_id, $user['id']);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        // Get address
        $stmt = $conn->prepare("SELECT full_name, street_address, city, state, country, postal_code, phone 
                                FROM addresses 
                                WHERE id = ?");
        $stmt->bind_param("i", $order['address_id']);
        $stmt->execute();
        $address = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Get payment (if exists)
        if ($order['payment_id']) {
            $stmt = $conn->prepare("SELECT amount, payment_method, payment_status, transaction_id 
                                    FROM payments 
                                    WHERE id = ?");
            $stmt->bind_param("i", $order['payment_id']);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        // Get order items
        $stmt = $conn->prepare("SELECT p.name, p.sku, p.image, oi.quantity, oi.price 
                                FROM order_items oi 
                                JOIN products p ON oi.product_id = p.id 
                                WHERE oi.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items_result = $stmt->get_result();
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }
        $stmt->close();
    }
}

if (!$order) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Order not found or you do not have permission to view it.'];
    header("Location: orders.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Deeken</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="hamburger.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #FFFFFF, #F6F6F6);
            min-height: 100vh;
            padding-top: 80px;
        }

        /* Navigation Styles (from orders.php, copied from cart.php) */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            transform: translateY(0);
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease;
        }

        .navbar.navbar-hidden {
            transform: translateY(-100%);
        }

        .navbar.navbar-visible {
            transform: translateY(0);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.15);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2A2AFF;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
            color: #1A1AFF;
        }

        .logo i {
            font-size: 1.6rem;
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .search-bar {
            display: flex;
            flex: 1;
            max-width: 500px;
            margin: 0 2rem;
            position: relative;
        }

        .search-bar input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .search-bar input:focus {
            border-color: #2A2AFF;
            box-shadow: 0 0 0 3px rgba(42, 42, 255, 0.1);
            transform: translateY(-1px);
        }

        .search-bar button {
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            color: white;
            cursor: pointer;
            margin-left: -50px;
            z-index: 1;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .search-bar button:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(42, 42, 255, 0.3);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .cart-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
        }

        .cart-link:hover {
            background: rgba(42, 42, 255, 0.1);
            color: #2A2AFF;
        }

        .cart-count {
            background: #FF6B35;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            margin-left: -5px;
        }

        .profile-dropdown {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
            background: white;
        }

        .profile-trigger:hover {
            background: #f8f9fa;
            border-color: #2A2AFF;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .profile-greeting {
            font-size: 12px;
            color: #666;
            line-height: 1.2;
        }

        .profile-account {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            line-height: 1.2;
        }

        .profile-account i {
            font-size: 10px;
            transition: transform 0.3s ease;
        }

        .profile-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            margin-top: 5px;
        }

        .profile-dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 12px 16px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            font-weight: 400;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 4px 8px;
        }

        .profile-dropdown-menu a:hover {
            background: rgba(42, 42, 255, 0.1);
            color: #2A2AFF;
        }

        .profile-dropdown-menu a i {
            width: 16px;
            color: #666;
        }

        .dropdown-divider {
            border: none;
            height: 1px;
            background: #e0e0e0;
            margin: 8px 16px;
        }

        .hamburger {
            display: none;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 0.01rem 3rem;
            }
            .logo{
               position: relative;
               right: 100px;
               bottom: 40px;
               
            }
            
            body {
                padding-top: 70px;
            }

            .nav-right {
                gap: 1rem;
                position: relative;
                left: 100px;
                top: 30px
            }

            .search-bar {
                display: none;
            }
        }

        /* Existing styles from orders.php */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2A2AFF;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .order-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .order-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-id {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #cce5ff;
            color: #004085;
        }

        .status-shipped {
            background: #d4edda;
            color: #155724;
        }

        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .order-body {
            padding: 1.5rem;
        }

        .order-items {
            margin-bottom: 1.5rem;
        }

        .order-items-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            background: #f8f9fa;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .item-sku {
            font-size: 0.85rem;
            color: #666;
        }

        .item-quantity {
            font-weight: 500;
            color: #555;
        }

        .item-price {
            font-weight: 600;
            color: #2A2AFF;
            font-size: 1.1rem;
        }

        .order-summary {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .summary-row.total {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
            border-top: 1px solid #ddd;
            padding-top: 0.5rem;
            margin-top: 1rem;
        }

        .order-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #2A2AFF;
            color: white;
        }

        .btn-primary:hover {
            background: #1a1aff;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .address-section, .payment-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .address-details, .payment-details {
            font-size: 0.9rem;
            color: #666;
        }

        .address-details p, .payment-details p {
            margin-bottom: 0.5rem;
        }

        .footer {
            background: linear-gradient(135deg, #1a1a1a, #2A2AFF);
            color: white;
            margin-top: 4rem;
            position: relative;
            overflow: hidden;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .footer-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #BDF3FF;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: #ddd;
            text-decoration: none;
            font-size: 14px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .social-links a {
            color: white;
            font-size: 1.5rem;
        }
        .notification-dot {
    display: inline-block;
    background-color: red;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    margin-left: 5px;
    vertical-align: middle;
}

        .contact-info p {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 14px;
            margin-bottom: 0.5rem;
            color: #ddd;
        }

        .footer-bottom {
            background: rgba(0, 0, 0, 0.2);
            padding: 1rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-bottom-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-bottom p {
            font-size: 14px;
            color: #ddd;
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-links a {
            color: #ddd;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar" id="navbar">
            <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search products..." onkeypress="if(event.key==='Enter') searchProducts()">
                <button type="button" onclick="searchProducts()"><i class="fas fa-search"></i></button>
            </div>
            <div class="nav-right">
               
              <div class="profile-dropdown">
    <?php if ($user): ?>
        <?php
        // Check for unread notifications
        require_once 'config.php'; // Include database connection
        $unread_count = 0;
        $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $unread_count = $result->fetch_assoc()['unread'];
        $stmt->close();
        ?>
        <div class="profile-trigger" onclick="toggleProfileDropdown()">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-info">
                <span class="profile-greeting">Hi, <?php echo htmlspecialchars($user['full_name'] ?? $user['email'] ?? 'User'); ?></span>
                <span class="profile-account">My Account <i class="fas fa-chevron-down"></i></span>
            </div>
        </div>
        <div class="profile-dropdown-menu" id="profileDropdown">
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
            <a href="inbox.php">
                <i class="fas fa-inbox"></i> Inbox
                <?php if ($unread_count > 0): ?>
                    <span class="notification-dot"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="index.php"><i class="fas fa-heart"></i> Home</a>
            <?php if (!empty($user['is_admin'])): ?>
                <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
            <?php endif; ?>
            <hr class="dropdown-divider">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    <?php else: ?>
        <div class="profile-trigger" onclick="toggleProfileDropdown()">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-info">
                <span class="profile-greeting">Hi, Guest</span>
                <span class="profile-account">Sign In <i class="fas fa-chevron-down"></i></span>
            </div>
        </div>
        <div class="profile-dropdown-menu" id="profileDropdown">
            <a href="login.php"><i class="fas fa-sign-in"></i> Sign In</a>
            <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
            <hr class="dropdown-divider">
            <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
        </div>
    <?php endif; ?>
</div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Order Details</h1>
                <p class="page-subtitle">Order #<?php echo htmlspecialchars($order['id']); ?></p>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type']; ?>">
                    <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div class="order-card">
                <div class="order-header">
                    <div class="order-header-row">
                        <span class="order-id">Order #<?php echo htmlspecialchars($order['id']); ?></span>
                        <span class="order-status status-<?php echo htmlspecialchars($order['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                        </span>
                    </div>
                    <div class="order-meta">
                        <div>
                            <strong>Date:</strong>
                            <?php echo htmlspecialchars(date('M d, Y', strtotime($order['created_at']))); ?>
                        </div>
                        <div>
                            <strong>Total:</strong>
                            $<?php echo number_format($order['total'] + $order['delivery_fee'], 2); ?>
                        </div>
                        <div>
                            <strong>Items:</strong>
                            <?php echo count($items); ?>
                        </div>
                    </div>
                </div>
                <div class="order-body">
                    <div class="order-items">
                        <h3 class="order-items-title">Items</h3>
                        <?php foreach ($items as $item): ?>
                            <div class="order-item">
                                <img src="<?php echo htmlspecialchars($item['image'] ?: 'https://via.placeholder.com/60'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                                <div class="item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="item-sku">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                                    <div class="item-quantity">Quantity: <?php echo htmlspecialchars($item['quantity']); ?></div>
                                </div>
                                <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>$<?php echo number_format($order['total'], 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Delivery Fee</span>
                            <span>$<?php echo number_format($order['delivery_fee'], 2); ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span>$<?php echo number_format($order['total'] + $order['delivery_fee'], 2); ?></span>
                        </div>
                    </div>
                    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                        <div class="order-actions">
                            <form method="POST" action="order_details.php?id=<?php echo $order_id; ?>">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="cancel_order" class="btn btn-danger" 
                                        onclick="return confirm('Are you sure you want to cancel this order?');">
                                    <i class="fas fa-times"></i> Cancel Order
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="address-section">
                <h2 class="section-title">Shipping Address</h2>
                <div class="address-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($address['full_name'] ?? 'N/A'); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($address['street_address'] ?? 'N/A'); ?></p>
                    <?php if (!empty($address['city']) || !empty($address['state']) || !empty($address['country'])): ?>
                        <p><strong>Location:</strong> 
                            <?php echo htmlspecialchars(implode(', ', array_filter([
                                $address['city'], 
                                $address['state'], 
                                $address['country']
                            ]))); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($address['postal_code'])): ?>
                        <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($address['postal_code']); ?></p>
                    <?php endif; ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($address['phone'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <div class="payment-section">
                <h2 class="section-title">Payment Information</h2>
                <div class="payment-details">
                    <?php if ($payment): ?>
                        <p><strong>Amount:</strong> $<?php echo number_format($payment['amount'], 2); ?></p>
                        <p><strong>Method:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($payment['payment_status'])); ?></p>
                        <?php if ($payment['transaction_id']): ?>
                            <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($payment['transaction_id']); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No payment information available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-actions">
                <a href="orders.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Orders</a>
            </div>
        </div>
    </main>

     <div class="footer-bottom">
            <p>Deeken © 2025, All Rights Reserved</p>
            <div class="payment-icons">
                <div class="payment-icon">💳</div>
                <div class="payment-icon">🏦</div>
                <div class="payment-icon">📱</div>
            </div>
        </div>

    <script src="hamburger.js"></script>
    <script src="utils.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle profile dropdown
            function toggleProfileDropdown() {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) {
                    dropdown.classList.toggle('show');
                    console.log('Dropdown toggled:', dropdown.classList.contains('show') ? 'visible' : 'hidden');
                } else {
                    console.error('Profile dropdown element not found');
                }
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const profileDropdown = document.querySelector('.profile-dropdown');
                const dropdown = document.getElementById('profileDropdown');
                if (profileDropdown && dropdown && !profileDropdown.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });

            // Ensure the toggle function is globally available
            window.toggleProfileDropdown = toggleProfileDropdown;

            // Navbar scroll hide/show functionality
            let lastScrollTop = 0;
            const navbar = document.getElementById('navbar');
            const scrollThreshold = 100;
            if (navbar) {
                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                    if (currentScroll < scrollThreshold) {
                        navbar.classList.remove('navbar-hidden');
                        navbar.classList.add('navbar-visible');
                        return;
                    }
                    if (currentScroll > lastScrollTop && currentScroll > scrollThreshold) {
                        navbar.classList.add('navbar-hidden');
                        navbar.classList.remove('navbar-visible');
                    } else if (currentScroll < lastScrollTop) {
                        navbar.classList.remove('navbar-hidden');
                        navbar.classList.add('navbar-visible');
                    }
                    lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
                });
            }

            // Search products
            function searchProducts() {
                const search = document.getElementById('searchInput').value.trim();
                if (search) {
                    window.location.href = `index.php?search=${encodeURIComponent(search)}`;
                }
            }
        });
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>