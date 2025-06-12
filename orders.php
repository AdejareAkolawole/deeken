<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle order cancellation
if (isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT id, status FROM orders WHERE id = ? AND user_id = ? AND status IN ('pending', 'processing')");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if ($order) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            $message = "Order #$order_id has been cancelled successfully.";
        } else {
            $error = "Failed to cancel order. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Order not found or cannot be cancelled.";
    }
}

// Get user info for navigation
$stmt = $conn->prepare("SELECT id, email, full_name, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get cart count for navigation
$cart_count = getCartCount($conn, $user);

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query conditions
$where_conditions = ["o.user_id = ?"];
$params = [$user_id];
$types = "i";

if ($status_filter && $status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(o.created_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $where_conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM orders WHERE user_id = ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_orders = $stmt->get_result()->fetch_row()[0];
$stmt->close();
$total_pages = ceil($total_orders / $limit);

// Get orders with address and payment info
$query = "
    SELECT o.*, 
           a.full_name as delivery_name,
           a.street_address, a.city, a.state, a.postal_code,
           p.payment_method, p.payment_status, p.transaction_id
    FROM orders o
    LEFT JOIN addresses a ON o.address_id = a.id
    LEFT JOIN payments p ON o.payment_id = p.id
    WHERE $where_clause
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
$types .= "ii";
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get order items for each order
$order_items = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    
    $stmt = $conn->prepare("
        SELECT oi.*, p.name as product_name, p.image, p.sku
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_id, oi.id
    ");
    $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($items as $item) {
        $order_items[$item['order_id']][] = $item;
    }
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'status-pending';
        case 'processing': return 'status-processing';
        case 'shipped': return 'status-shipped';
        case 'delivered': return 'status-delivered';
        case 'cancelled': return 'status-cancelled';
        default: return 'status-pending';
    }
}

// Helper function to check if order can be cancelled
function canCancelOrder($status) {
    return in_array($status, ['pending', 'processing']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Deeken</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        }

        .navbar {
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .navbar.hidden {
            transform: translateY(-100%);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            background: rgba(255, 255, 255, 0.9);
        }

        .search-bar button {
            background: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            color: #2A2AFF;
            cursor: pointer;
            margin-left: -50px;
            z-index: 1;
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
            color: white;
            font-weight: 500;
            paddingeson: 8px 16px;
            border-radius: 25px;
            position: relative;
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
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.2);
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2A2AFF;
            font-size: 16px;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .profile-greeting {
            font-size: 12px;
            color: white;
            line-height: 1;
        }

        .profile-account {
            font-size: 14px;
            font-weight: 500;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            line-height: 1.2;
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
            margin-top: 8px;
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
            border-radius: 8px;
            margin: 4px 8px;
        }

        .dropdown-divider {
            border: none;
            height: 1px;
            background: #e0e0e0;
            margin: 8px 16px;
        }

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

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #555;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            background: white;
        }

        .filter-btn {
            background: #2A2AFF;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 1.5rem;
        }

        .filter-btn:hover {
            background: #1a1aff;
            transform: translateY(-1px);
        }

        .orders-summary {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .summary-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            text-align: center;
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2A2AFF;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
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

        .btn-outline {
            background: transparent;
            color: #2A2AFF;
            border: 1px solid #2A2AFF;
        }

        .btn-outline:hover {
            background: #2A2AFF;
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            text-decoration: none;
            color: #2A2AFF;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #2A2AFF;
            color: white;
        }

        .pagination .current {
            background: #2A2AFF;
            color: white;
            border-color: #2A2AFF;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 1.5rem;
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
    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search products...">
            <button onclick="searchProducts()"><i class="fas fa-search"></i></button>
        </div>
        
        <div class="nav-right">
            <a href="cart.php" class="cart-link">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-text">Cart</span>
                <span class="cart-count"><?php echo (int)$cart_count; ?></span>
            </a>
            
            <div class="profile-dropdown">
                <div class="profile-trigger">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-info">
                        <span class="profile-greeting">Hi, <?php echo htmlspecialchars($user['full_name'] ?? $user['email'] ?? ''); ?></span>
                        <span class="profile-account">My Account <i class="fas fa-chevron-down"></i></span>
                    </div>
                </div>
                
                <div class="profile-dropdown-menu" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
                    <a href="index.php"><i class="fas fa-heart"></i> Home</a>
                    <?php if (!empty($user['is_admin'])): ?>
                        <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
                    <?php endif; ?>
                    <hr class="dropdown-divider">
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <p class="page-subtitle">Track and manage your orders</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="filters-section">
            <h3 class="filters-title">Filter Orders</h3>
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All Orders</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Order Date</label>
                        <select name="date" id="date">
                            <option value="" <?php echo $date_filter === '' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>Last Year</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="filter-btn">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </form>
        </div>

        <?php if ($total_orders > 0): ?>
            <div class="orders-summary">
                <h3 class="summary-title">Order Summary</h3>
                <div class="summary-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo (int)$total_orders; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <?php
                    $status_counts = [];
                    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM orders WHERE user_id = ? GROUP BY status");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $status_counts[$row['status']] = $row['count'];
                    }
                    $stmt->close();
                    ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo (int)($status_counts['pending'] ?? 0); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo (int)($status_counts['delivered'] ?? 0); ?></div>
                        <div class="stat-label">Delivered</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo (int)($status_counts['cancelled'] ?? 0); ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="orders-list">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Orders Found</h3>
                    <p>You haven't placed any orders yet or no orders match your filters.</p>
                    <a href="index.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-header-row">
                                <div class="order-id">Order #<?php echo (int)$order['id']; ?></div>
                                <span class="order-status <?php echo getStatusBadgeClass($order['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                </span>
                            </div>
                            <div class="order-meta">
                                <div>
                                    <strong>Order Date:</strong>
                                    <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                                </div>
                                <div>
                                    <strong>Total:</strong>
                                    $<?php echo number_format($order['total'], 2); ?>
                                </div>
                                <div>
                                    <strong>Payment Status:</strong>
                                    <?php echo htmlspecialchars($order['payment_status'] ?? 'N/A'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="order-items">
                                <h4 class="order-items-title">Items</h4>
                                <?php if (!empty($order_items[$order['id']])): ?>
                                    <?php foreach ($order_items[$order['id']] as $item): ?>
                                        <div class="order-item">
                                            <img src="<?php echo htmlspecialchars($item['image'] ?? 'assets/images/placeholder.jpg'); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?>" 
                                                 class="item-image">
                                            <div class="item-details">
                                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                <div class="item-sku">SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></div>
                                                <div class="item-quantity">Quantity: <?php echo (int)$item['quantity']; ?></div>
                                            </div>
                                            <div class="item-price">
                                                $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No items found for this order.</p>
                                <?php endif; ?>
                            </div>

                            <div class="order-delivery">
                                <h4 class="order-details-title">Delivery Details</h4>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['delivery_name'] ?? 'N/A'); ?></p>
                                <p><strong>Address:</strong> 
                                    <?php echo htmlspecialchars(
                                        trim(
                                            ($order['street_address'] ?? '') . ', ' . 
                                            ($order['city'] ?? '') . ', ' . 
                                            ($order['state'] ?? '') . ' ' . 
                                            ($order['postal_code'] ?? ''),
                                            ', '
                                        )
                                    ); ?>
                                </p>
                            </div>

                            <div class="order-summary">
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span>$<?php echo number_format($order['total'] - ($order['delivery_fee'] ?? 0), 2); ?></span>
                                </div>
                                <?php if (!empty($order['delivery_fee']) && $order['delivery_fee'] > 0): ?>
                                    <div class="summary-row">
                                        <span>Delivery Fee:</span>
                                        <span>$<?php echo number_format($order['delivery_fee'], 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span>$<?php echo number_format($order['total'], 2); ?></span>
                                </div>
                            </div>

                            <div class="order-actions">
                                <?php if (canCancelOrder($order['status'])): ?>
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                        <input type="hidden" name="action" value="cancel_order">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <button type="submit" class="btn btn-danger">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo esc_attr($page - 1); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo (int)$i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo esc_attr($i); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                            <?php echo (int)$i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo esc_attr($page + 1); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Deeken</h3>
                <ul>
                    <li><a href="about.php">Our Story</a></li>
                    <li><a href="careers.php">Careers</a></li>
                    <li><a href="press.php">Press</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="returns.php">Returns</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Connect With Us</h3>
                <div class="social-links">
                    <a href="index.php"><i class="fab fa-facebook"></i></a>
                    <a href="index.php"><i class="fab fa-twitter"></i></a>
                    <a href="index.php"><i class="fab fa-instagram"></i></a>
                </div>
                <div class="contact-info">
                    <p><i class="fas fa-envelope"></i> support@deeken.com</p>
                    <p><i class="fas fa-phone"></i> +1-800-123-4567</p>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p>© <?php echo date('Y'); ?> Deeken. All rights reserved.</p>
                <div class="footer-links">
                    <a href="terms.php">Terms of Service</a>
                    <a href="privacy.php">Privacy Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="hamburger.js"></script>
    <script src="utils.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.querySelector('.profile-trigger');

            // Debugging: Check if elements exist
            if (!dropdown || !trigger) {
                console.error('Dropdown or trigger element not found:', { dropdown, trigger });
                return;
            }

            // Toggle dropdown function
            function toggleProfileDropdown(event) {
                event.stopPropagation(); // Prevent click from bubbling to document
                console.log('Toggling dropdown'); // Debug
                dropdown.classList.toggle('show');
            }

            // Attach click handler to trigger
            trigger.addEventListener('click', toggleProfileDropdown);

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!trigger.contains(event.target) && !dropdown.contains(event.target)) {
                    console.log('Closing dropdown'); // Debug
                    dropdown.classList.remove('show');
                }
            });
        });

        function searchProducts() {
            const query = document.getElementById('searchInput').value.trim();
            if (query) {
                window.location.href = `index.php?search=${encodeURIComponent(query)}`;
            }
        }

        // Navbar scroll behavior
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');

        window.addEventListener('scroll', function() {
            let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            
            if (currentScroll > lastScrollTop) {
                // Scrolling down
                navbar.classList.add('hidden');
            } else {
                // Scrolling up
                navbar.classList.remove('hidden');
            }
            
            // Show navbar when at the top
            if (currentScroll <= 0) {
                navbar.classList.remove('hidden');
            }
            
            lastScrollTop = currentScroll <= 0 ? 0 : currentScrollTop;
        });
    </script>
</body>
</html>