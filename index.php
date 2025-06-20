<?php
require_once 'config.php';

// Get current user and cart count
$user = getCurrentUser();
$cartCount = getCartCount($conn, $user);

// Fetch categories for navigation
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch notifications for the user
$notifications = [];
$unread_count = 0;
if ($user) {
    $notifications_query = $conn->prepare("SELECT id, message, type, order_id, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $notifications_query->bind_param("i", $user['id']);
    $notifications_query->execute();
    $result = $notifications_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
        if (!$row['is_read']) {
            $unread_count++;
        }
    }
    $notifications_query->close();
}

// Fetch featured/new arrival products with stock
$new_arrivals_query = "SELECT p.*, c.name as category_name, 
                       COALESCE(AVG(r.rating), 0) AS avg_rating,
                       COUNT(r.id) AS review_count,
                       COALESCE(i.stock_quantity, 0) AS stock_quantity
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       LEFT JOIN reviews r ON p.id = r.product_id 
                       LEFT JOIN inventory i ON p.id = i.product_id 
                       GROUP BY p.id 
                       ORDER BY p.created_at DESC 
                       LIMIT 4";
$new_arrivals = $conn->query($new_arrivals_query);

// In index.php, fetch Hero Section data
$hero_section = $conn->query("SELECT title, description, button_text, main_image, sparkle_image_1, sparkle_image_2 FROM hero_section LIMIT 1")->fetch_assoc() ?? [];

// Fetch top selling products with stock
$top_selling_query = "SELECT p.*, c.name as category_name,
                       COALESCE(AVG(r.rating), 0) AS avg_rating,
                       COUNT(r.id) AS review_count,
                       COALESCE(SUM(oi.quantity), 0) AS total_sold,
                       COALESCE(i.stock_quantity, 0) AS stock_quantity
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       LEFT JOIN reviews r ON p.id = r.product_id 
                       LEFT JOIN order_items oi ON p.id = oi.product_id 
                       LEFT JOIN orders o ON oi.order_id = o.id 
                       LEFT JOIN inventory i ON p.id = i.product_id 
                       WHERE o.status != 'cancelled' OR o.status IS NULL 
                       GROUP BY p.id 
                       ORDER BY total_sold DESC, p.created_at DESC 
                       LIMIT 4";
$top_selling = $conn->query($top_selling_query);

// Fetch customer testimonials
$testimonials_query = "SELECT r.review_text, r.rating, u.full_name, r.created_at 
                      FROM reviews r 
                      JOIN users u ON r.user_id = u.id 
                      WHERE r.rating >= 4 
                      ORDER BY r.created_at DESC 
                      LIMIT 3";
$testimonials = $conn->query($testimonials_query);

// Handle add to cart
if ($_POST['action'] ?? '' === 'add_to_cart') {
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Please login to add items to cart.']);
        exit;
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id > 0) {
        // Check if product exists and has stock
        $product_check = $conn->prepare("SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE p.id = ?");
        $product_check->bind_param("i", $product_id);
        $product_check->execute();
        $product = $product_check->get_result()->fetch_assoc();
        $product_check->close();
        
        if ($product && ($product['stock_quantity'] > 0 || $product['stock_quantity'] === null)) {
            // Check if item already in cart
            $cart_check = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $cart_check->bind_param("ii", $user['id'], $product_id);
            $cart_check->execute();
            $existing = $cart_check->get_result()->fetch_assoc();
            $cart_check->close();
            
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                $update_cart = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
                $update_cart->bind_param("iii", $new_quantity, $user['id'], $product_id);
                $success = $update_cart->execute();
                $update_cart->close();
            } else {
                // Add new item
                $add_cart = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $add_cart->bind_param("iii", $user['id'], $product_id, $quantity);
                $success = $add_cart->execute();
                $add_cart->close();
            }
            
            if ($success) {
                $newCartCount = getCartCount($conn, $user);
                echo json_encode(['success' => true, 'message' => 'Item added to cart!', 'cartCount' => $newCartCount]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add item to cart.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Product is out of stock.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    }
    exit;
}

// Handle marking notification as read
if ($_POST['action'] ?? '' === 'mark_notification_read') {
    if ($user) {
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user['id']);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
    }
    exit;
}

// Function to display star rating
function displayStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '★';
        } else {
            $stars .= '☆';
        }
    }
    return $stars;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Find Clothes That Matches Your Style</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="responsive-index.css">
    <style>
   /* Notification styles */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 5px;
    color: white;
    font-weight: 500;
    z-index: 3000; /* Increased to ensure it appears above navbar and marquee */
    opacity: 0;
    transform: translateX(100%);
    transition: opacity 0.3s ease, transform 0.3s ease;
    min-width: 300px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.notification.success {
    background-color: #28a745;
}
.notification.error {
    background-color: #dc3545;
}
.notification.show {
    opacity: 1;
    transform: translateX(0);
}
        /* Login prompt styles */
        .login-prompt {
            background: rgba(0,0,0,0.8);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .login-prompt .modal {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .btn-primary {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }

        /* Add to cart button styles */
        .add-to-cart-btn {
            background: rgb(46, 57, 153);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: background-color 0.3s;
            width: 100%;
        }
        .add-to-cart-btn:hover:not(:disabled) {
            background: rgb(78, 90, 197);
        }
        .add-to-cart-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .out-of-stock {
            color: #dc3545;
            font-weight: bold;
            padding: 5px 10px;
        }

        /* Marquee styles */
        .top-banner {
            background: #000;
            color: #fff;
            padding: 10px 0;
            position: relative;
            font-size: 14px;
            overflow: hidden;
        }
        .marquee-container {
            width: 100%;
            overflow: hidden;
        }
        .marquee-content {
            display: flex;
            animation: marquee 20s linear infinite;
            white-space: nowrap;
        }
        .marquee-text {
            margin-right: 40px;
        }
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        .close-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
        }

        /* Search bar and autocomplete styles */
        .search-bar {
            position: relative;
            width: 100%;
            max-width: 500px;
        }
        .search-bar input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .search-bar .fa-search {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
        }
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-top: 5px;
        }
        .autocomplete-suggestion {
            padding: 12px 15px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
        }
        .autocomplete-suggestion:hover {
            background: #f5f5f5;
        }
        .autocomplete-suggestion img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            margin-right: 10px;
            border-radius: 3px;
        }
        .autocomplete-highlight {
            font-weight: 600;
            color: #007bff;
        }

        /* Notification dropdown styles */
        .notification-dropdown {
            position: relative;
            display: inline-block;
        }
        .notification-btn {
            background: none;
            border: none;
            cursor: pointer;
            position: relative;
            padding: 10px;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ff6f61;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 300px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .notification-dropdown-menu.active {
            display: block;
        }
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .notification-item.unread {
            background: #f9f9f9;
        }
        .notification-item:hover {
            background: #f5f5f5;
        }
        .notification-icon {
            margin-right: 10px;
            font-size: 18px;
        }
        .notification-content {
            flex: 1;
        }
        .notification-time {
            font-size: 12px;
            color: #666;
        }

        /* Product grid styles */
        .new-arrivals-grid, .top-selling-grid {
            padding: 0 5%;
            text-align: center;
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
        }
        .scroll-container {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 20px 0;
            max-width: 100%;
            margin: 0 auto;
            justify-content: flex-start;
            flex-wrap: nowrap;
            gap: 20px;
            position: relative;
        }
        .scroll-container::-webkit-scrollbar {
            height: 8px;
        }
        .scroll-container::-webkit-scrollbar-thumb {
            background-color: var(--medium-gray, #ccc);
            border-radius: 4px;
        }
        .scroll-container::-webkit-scrollbar-track {
            background: var(--light-gray, #f5f5f5);
        }
        .scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            z-index: 10;
            border-radius: 50%;
            font-size: 18px;
        }
        .scroll-btn.left {
            left: 10px;
        }
        .scroll-btn.right {
            right: 10px;
        }
        .new-arrival-card, .top-selling-card {
            min-width: 250px;
            flex: 0 0 auto;
            background: var(--primary-white, #fff);
            border: 1px solid var(--medium-gray, #ddd);
            border-radius: 10px;
            overflow: hidden;
            text-align: center;
            padding: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .new-arrival-card:hover, .top-selling-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        .product-image-container {
            position: relative;
            height: 250px;
            overflow: hidden;
        }
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
        }
        .product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--accent-color, #ff6f61);
            color: var(--primary-white, #fff);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            text-transform: uppercase;
        }
        .product-details {
            padding: 15px;
        }
        .product-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .product-price {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
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
        .original-price {
            font-size: 14px;
            color: var(--text-gray, #666);
            text-decoration: line-through;
            margin-left: 5px;
        }
        .color-options {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-bottom: 10px;
        }
        .color-dot {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #ccc;
            cursor: pointer;
        }
        .color-dot.active {
            border: 2px solid var(--accent-color, #ff6f61);
        }
        .product-rating {
            margin-bottom: 10px;
        }
        .stars {
            color: #f4c430;
            font-size: 14px;
        }
        .rating-text {
            font-size: 12px;
            color: var(--text-gray, #666);
            margin-left: 5px;
        }

        /* Mobile-specific styles */
        @media (max-width: 767px) {
            .search-bar {
                width: 40px;
                transition: width 0.3s ease;
            }
            .search-bar.active {
                width: 100%;
            }
            .search-bar input {
                display: none;
            }
            .search-bar.active input {
                display: block;
            }
            .search-bar .fa-search {
                right: 10px;
            }
            .nav-icons .cart-btn {
                display: none;
            }
            .hamburger-menu {
                display: block;
                cursor: pointer;
                font-size: 24px;
                padding: 10px;
            }
            .mobile-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ddd;
                z-index: 1000;
                padding: 20px;
            }
            .mobile-menu.active {
                display: block;
            }
            .mobile-menu a {
                display: block;
                padding: 10px 0;
                color: #333;
                text-decoration: none;
                font-size: 16px;
            }
            .mobile-menu a:hover {
                color: #ff6f61;
            }
        }

        /* Responsive styles */
        @media (min-width: 768px) {
            .scroll-container {
                justify-content: flex-start;
            }
            .product-image-container {
                height: 300px;
            }
            .product-title {
                font-size: 18px;
            }
            .product-price {
                font-size: 20px;
            }
            .add-to-cart-btn {
                padding: 10px 20px;
            }
            .hamburger-menu {
                display: none;
            }
        }
        @media (min-width: 1024px) {
            .scroll-container {
                gap: 30px;
            }
            .product-image-container {
                height: 350px;
            }
            .product-title {
                font-size: 20px;
            }
            .product-price {
                font-size: 22px;
            }
            .add-to-cart-btn {
                padding: 12px 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Notification System -->
    <div id="notification" class="notification"></div>
    
    <!-- Login Prompt Modal -->
    <div id="loginPrompt" class="login-prompt">
        <div class="modal">
            <h3>Login Required</h3>
            <p>Please login to add items to your cart and make purchases.</p>
            <a href="login.php" class="btn-primary">Login</a>
            <a href="register.php" class="btn-secondary">Register</a>
            <button onclick="closeLoginPrompt()" class="btn-secondary">Cancel</button>
        </div>
    </div>

    <!-- Top Banner Marquee -->
    <div class="top-banner">
        <div class="marquee-container">
            <div class="marquee-content">
                <span class="marquee-text">🎉 Sign up and get 20% off to your first order. <a href="register.php" style="color: #ff6f61;">Sign Up Now</a></span>
                <span class="marquee-text">✨ Free shipping on orders over $50</span>
                <span class="marquee-text">🔥 Limited time offer - Don't miss out!</span>
                <span class="marquee-text">💫 New arrivals every week</span>
                <span class="marquee-text">🎉 Sign up and get 20% off to your first order. <a href="register.php" style="color: #ff6f61;">Sign Up Now</a></span>
            </div>
        </div>
        <button class="close-btn" onclick="closeBanner()">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo">Deeken</div>
        <div class="search-bar" id="searchBar">
            <i class="fas fa-search" id="searchIcon"></i>
            <input type="text" placeholder="Search for products..." id="searchInput">
            <div class="autocomplete-suggestions" id="autocompleteSuggestions"></div>
        </div>
        <div class="nav-icons">
         <div class="notification-dropdown">
    <button class="notification-btn" title="Notifications" onclick="toggleNotificationDropdown()">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="notification-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </button>
    <div class="notification-dropdown-menu" id="notificationDropdown">
        <?php if (empty($notifications)): ?>
            <div class="notification-item">No notifications</div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                     data-notification-id="<?php echo $notification['id']; ?>" 
                     onclick="markNotificationRead(<?php echo $notification['id']; ?>, '<?php echo $notification['type']; ?>', <?php echo $notification['order_id'] ?: 'null'; ?>)">
                    <i class="fas fa-<?php 
                        switch ($notification['type']) {
                            case 'order_received': echo 'check-circle'; break;
                            case 'ready_to_ship': echo 'box'; break;
                            case 'shipped': echo 'truck'; break;
                            case 'cart_added': echo 'shopping-cart'; break;
                            default: echo 'bell';
                        }
                    ?> notification-icon"></i>
                    <div class="notification-content">
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <span class="notification-time"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
            <button class="cart-btn" title="Shopping Cart" onclick="window.location.href='cart.php'">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge" id="cartBadge"><?php echo $cartCount; ?></span>
            </button>
           
            <!-- Profile Dropdown -->
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
        </div>
        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="shop.php"><i class="fas fa-store"></i> Shop</a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart <span class="cart-badge"><?php echo $cartCount; ?></span></a>
            <a href="orders.php"><i class="fas fa-box"></i> Orders</a>
            <?php if ($user): ?>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in"></i> Sign In</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1><?php echo $hero_section['title'] ?? 'FIND CLOTHES<br>THAT MATCHES<br>YOUR STYLE'; ?></h1>
            <p><?php echo htmlspecialchars($hero_section['description'] ?? 'Browse through our diverse range of meticulously crafted garments, designed to bring out your individuality and cater to your sense of style.'); ?></p>
            <button class="cta-button" onclick="window.location.href='shop.php'"><?php echo htmlspecialchars($hero_section['button_text'] ?? 'Shop Now'); ?></button>
        </div>
        <div class="hero-image">
            <div class="hero-couple" style="background-image: url('<?php echo htmlspecialchars($hero_section['main_image'] ?? 'images/hero-couple.png'); ?>');"></div>
            <div class="sparkle sparkle-1" style="background-image: url('<?php echo htmlspecialchars($hero_section['sparkle_image_1'] ?? 'images/sparkle-1.png'); ?>');"></div>
            <div class="sparkle sparkle-2" style="background-image: url('<?php echo htmlspecialchars($hero_section['sparkle_image_2'] ?? 'images/sparkle-2.png'); ?>');"></div>
        </div>
    </section>

    <!-- Stats -->
    <section class="stats">
        <div class="stat-item">
            <h3><?php echo count($categories); ?>+</h3>
            <p>Product Categories</p>
        </div>
        <div class="stat-item">
            <h3>2,000+</h3>
            <p>High-Quality Products</p>
        </div>
        <div class="stat-item">
            <h3>30,000+</h3>
            <p>Happy Customers</p>
        </div>
    </section>

    <!-- Categories -->
    <div class="categories">
        <div class="categories-text">
            <h2>Shop by Category</h2>
            <p>Choose from a variety of options tailored just for you</p>
        </div>
        <?php foreach ($categories as $category): ?>
            <div class="category-item" onclick="window.location.href='shop.php?category=<?php echo $category['id']; ?>'">
                <div class="category-circle"></div>
                <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- New Arrivals Section -->
    <section class="new-arrivals">
        <h2 class="section-title">New Arrivals</h2>
        <div class="new-arrivals-grid product-grid">
            <div class="scroll-container">
                <button class="scroll-btn left" onclick="scrollContainer('new-arrivals', -1)">&lt;</button>
                <?php
                $new_arrivals->data_seek(0);
                while ($product = $new_arrivals->fetch_assoc()):
                    $avg_rating = round($product['avg_rating'], 1);
                    $review_count = $product['review_count'];
                    $stock = $product['stock_quantity'];
                ?>
                    <div class="new-arrival-card product-card" data-product-id="<?php echo $product['id']; ?>">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/400x300'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                            <?php if ($stock == 0): ?>
                                <div class="product-badge out-of-stock">Out of Stock</div>
                            <?php else: ?>
                                <div class="product-badge">New</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-details">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price">
                                <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                                <?php if (isset($product['original_price']) && $product['original_price'] > $product['price']): ?>
                                    <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="color-options">
                                <div class="color-dot active"></div>
                                <div class="color-dot"></div>
                                <div class="color-dot"></div>
                                <div class="color-dot"></div>
                            </div>
                            <div class="product-rating">
                                <span class="stars"><?php echo displayStars($avg_rating); ?></span>
                                <span class="rating-text"><?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)</span>
                            </div>
                            <button class="add-to-cart-btn" onclick="addToCart(<?php echo $product['id']; ?>)" <?php echo $stock == 0 ? 'disabled' : ''; ?>>Add to Cart</button>
                        </div>
                    </div>
                <?php endwhile; ?>
                <button class="scroll-btn right" onclick="scrollContainer('new-arrivals', 1)">&gt;</button>
            </div>
        </div>
        <div class="view-all">
            <button class="view-all-btn" onclick="window.location.href='shop.php?filter=new'">View All</button>
        </div>
    </section>

    <!-- Top Selling Section -->
    <section class="top-selling">
        <h2 class="section-title">Top Selling</h2>
        <div class="top-selling-grid product-grid">
            <div class="scroll-container">
                <button class="scroll-btn left" onclick="scrollContainer('top-selling', -1)">&lt;</button>
                <?php
                $top_selling->data_seek(0);
                while ($product = $top_selling->fetch_assoc()):
                    $avg_rating = round($product['avg_rating'], 1);
                    $review_count = $product['review_count'];
                    $stock = $product['stock_quantity'];
                ?>
                    <div class="top-selling-card product-card" data-product-id="<?php echo $product['id']; ?>">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/400x300'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                            <?php if ($stock == 0): ?>
                                <div class="product-badge out-of-stock">Out of Stock</div>
                            <?php else: ?>
                                <div class="product-badge">Hot</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-details">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price">
                                <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                                <?php if (isset($product['original_price']) && $product['original_price'] > $product['price']): ?>
                                    <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="color-options">
                                <div class="color-dot active"></div>
                                <div class="color-dot"></div>
                                <div class="color-dot"></div>
                                <div class="color-dot"></div>
                            </div>
                            <div class="product-rating">
                                <span class="stars"><?php echo displayStars($avg_rating); ?></span>
                                <span class="rating-text"><?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)</span>
                            </div>
                            <button class="add-to-cart-btn" onclick="addToCart(<?php echo $product['id']; ?>)" <?php echo $stock == 0 ? 'disabled' : ''; ?>>Add to Cart</button>
                        </div>
                    </div>
                <?php endwhile; ?>
                <button class="scroll-btn right" onclick="scrollContainer('top-selling', 1)">&gt;</button>
            </div>
        </div>
        <div class="view-all">
            <button class="view-all-btn" onclick="window.location.href='shop.php?filter=popular'">View All</button>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials">
        <h2 class="section-title">OUR HAPPY CUSTOMERS</h2>
        <div class="testimonial-grid">
            <?php if ($testimonials->num_rows > 0): ?>
                <?php while ($testimonial = $testimonials->fetch_assoc()): ?>
                    <div class="testimonial-card">
                        <div class="rating">
                            <span class="stars"><?php echo displayStars($testimonial['rating']); ?></span>
                        </div>
                        <div class="testimonial-header">
                            <span class="customer-name"><?php echo htmlspecialchars($testimonial['full_name']); ?></span>
                            <span class="verified">✓</span>
                        </div>
                        <p class="testimonial-text"><?php echo htmlspecialchars($testimonial['review_text']); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="testimonial-card">
                    <div class="rating">
                        <span class="stars">★★★★★</span>
                    </div>
                    <div class="testimonial-header">
                        <span class="customer-name">Sarah M.</span>
                        <span class="verified">✓</span>
                    </div>
                    <p class="testimonial-text">I'm blown away by the quality and style of the clothes I received from Deeken. From casual wear to elegant dresses every piece I've bought has exceeded my expectations.</p>
                </div>
                <div class="testimonial-card">
                    <div class="rating">
                        <span class="stars">★★★★★</span>
                    </div>
                    <div class="testimonial-header">
                        <span class="customer-name">Alex K.</span>
                        <span class="verified">✓</span>
                    </div>
                    <p class="testimonial-text">Finding clothes that align with my personal style used to be a challenge until I discovered Deeken. The range of options they offer is truly remarkable.</p>
                </div>
                <div class="testimonial-card">
                    <div class="rating">
                        <span class="stars">★★★★★</span>
                    </div>
                    <div class="testimonial-header">
                        <span class="customer-name">James L.</span>
                        <span class="verified">✓</span>
                    </div>
                    <p class="testimonial-text">As someone who's always on the lookout for unique fashion pieces, I'm thrilled to have stumbled upon Deeken. The selection is diverse and on-point with trends.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
   <footer class="footer">
    <div class="footer-content">
        <div class="footer-brand">
            <h3>DEEKEN</h3>
            <p>We have clothes that suits your style and which you're proud to wear. From women to men.</p>
            <div class="social-icons">
                <div class="social-icon">f</div>
                <div class="social-icon">t</div>
                <div class="social-icon">in</div>
                <div class="social-icon">ig</div>
            </div>
        </div>
        <div class="footer-column">
            <h4>Company</h4>
            <ul>
                <li><a href="unified.php?page=about">About</a></li>
                <li><a href="unified.php?page=contact">Contact</a></li>
                <li><a href="unified.php?page=careers">Careers</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Help</h4>
            <ul>
                <li><a href="unified.php?page=support">Customer Support</a></li>
                <li><a href="unified.php?page=shipping">Delivery Details</a></li>
                <li><a href="unified.php?page=terms">Terms & Conditions</a></li>
                <li><a href="unified.php?page=privacy">Privacy Policy</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>FAQ</h4>
            <ul>
                <li><a href="unified.php?page=faq&section=account">Account</a></li>
                <li><a href="unified.php?page=faq&section=delivery">Manage Deliveries</a></li>
                <li><a href="unified.php?page=faq&section=orders">Orders</a></li>
                <li><a href="unified.php?page=faq&section=payments">Payments</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Resources</h4>
            <ul>
                <li><a href="unified.php?page=blog">Blog</a></li>
                <li><a href="unified.php?page=size-guide">Size Guide</a></li>
                <li><a href="unified.php?page=care-instructions">Care Instructions</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>Deeken © 2025, All Rights Reserved</p>
        <div class="payment-icons">
            <div class="payment-icon">💳</div>
            <div class="payment-icon">🏦</div>
            <div class="payment-icon">📱</div>
        </div>
    </div>
</footer>

    <script>
        const isLoggedIn = <?php echo $user ? 'true' : 'false'; ?>;

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.className = 'notification';
            notification.textContent = message;
            notification.classList.add(type, 'show');
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        function showLoginPrompt() {
            document.getElementById('loginPrompt').style.display = 'flex';
        }

        function closeLoginPrompt() {
            document.getElementById('loginPrompt').style.display = 'none';
        }

        async function addToCart(productId) {
            if (!isLoggedIn) {
                showLoginPrompt();
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('cartBadge').textContent = data.cartCount;
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Add to Cart Error:', error);
                showNotification('An error occurred while adding to cart. Please try again.', 'error');
            }
        }

        // Notification dropdown toggle
        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
        }

        async function markNotificationRead(notificationId, orderId) {
            try {
                const formData = new FormData();
                formData.append('action', 'mark_notification_read');
                formData.append('notification_id', notificationId);

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    const item = document.querySelector(`.notification-item[data-notification-id="${notificationId}"]`);
                    item.classList.remove('unread');
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        let count = parseInt(badge.textContent) - 1;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.textContent = count;
                        }
                    }
                    // Redirect to order details
                    window.location.href = `orders.php?id=${orderId}`;
                }
            } catch (error) {
                console.error('Mark Notification Error:', error);
            }
        }

        // Mobile menu toggle
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('active');
        }

        // Search and autocomplete functionality
        const searchBar = document.getElementById('searchBar');
        const searchInput = document.getElementById('searchInput');
        const searchIcon = document.getElementById('searchIcon');
        const autocompleteSuggestions = document.getElementById('autocompleteSuggestions');

        function highlightMatch(text, query) {
            const regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<span class="autocomplete-highlight">$1</span>');
        }

        function performSearch() {
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = `shop.php?q=${encodeURIComponent(query)}`;
            }
        }

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        searchIcon.addEventListener('click', function() {
            if (window.innerWidth <= 767) {
                searchBar.classList.toggle('active');
                if (searchBar.classList.contains('active')) {
                    searchInput.focus();
                }
            } else {
                performSearch();
            }
        });

        let debounceTimeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            const query = this.value.trim();

            if (query.length >= 2) {
                debounceTimeout = setTimeout(() => {
                    fetch(`autocomplete.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        autocompleteSuggestions.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'autocomplete-suggestion';
                                div.innerHTML = `
                                    <img src="${item.image || 'https://via.placeholder.com/40'}" alt="${item.name}">
                                    <span>${highlightMatch(item.name, query)}</span>
                                `;
                                div.addEventListener('click', () => {
                                    searchInput.value = item.name;
                                    autocompleteSuggestions.style.display = 'none';
                                    window.location.href = `shop.php?q=${encodeURIComponent(item.name)}`;
                                });
                                autocompleteSuggestions.appendChild(div);
                            });
                            autocompleteSuggestions.style.display = 'block';
                        } else {
                            autocompleteSuggestions.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Autocomplete error:', error);
                        autocompleteSuggestions.style.display = 'none';
                    });
                }, 300);
            } else {
                autocompleteSuggestions.style.display = 'none';
            }
        });

        document.addEventListener('click', function(e) {
            if (!searchBar.contains(e.target) && !autocompleteSuggestions.contains(e.target)) {
                autocompleteSuggestions.style.display = 'none';
                if (window.innerWidth <= 767) {
                    searchBar.classList.remove('active');
                }
            }
        });

        // Profile dropdown toggle
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown on outside click
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.querySelector('.profile-trigger');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationTrigger = document.querySelector('.notification-btn');
            if (!trigger.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
            if (!notificationTrigger.contains(event.target) && !notificationDropdown.contains(event.target)) {
                notificationDropdown.classList.remove('active');
            }
        });

        // Close banner
        function closeBanner() {
            document.querySelector('.top-banner').style.display = 'none';
        }

        // Auto-scroll and manual scroll for product grids
        document.addEventListener('DOMContentLoaded', () => {
            function setupScroll(sectionClass) {
                const container = document.querySelector(`.${sectionClass} .scroll-container`);
                if (!container) return;

                const cards = container.querySelectorAll('.product-card');
                if (cards.length <= 3) {
                    container.style.justifyContent = 'center';
                    container.style.overflowX = 'hidden';
                    return;
                }

                let scrollAmount = 0;
                const scrollSpeed = 1;
                const cardWidth = cards[0].offsetWidth + 20;
                const maxScroll = container.scrollWidth - container.clientWidth;

                let animationFrameId;

                function autoScroll() {
                    if (scrollAmount >= maxScroll) {
                        scrollAmount = 0;
                        container.scrollLeft = 0;
                    } else {
                        scrollAmount += scrollSpeed;
                        container.scrollLeft = scrollAmount;
                    }
                    animationFrameId = requestAnimationFrame(autoScroll);
                }

                setTimeout(() => {
                    animationFrameId = requestAnimationFrame(autoScroll);
                }, 2000);

                container.addEventListener('mouseenter', () => {
                    cancelAnimationFrame(animationFrameId);
                });

                container.addEventListener('mouseleave', () => {
                    animationFrameId = requestAnimationFrame(autoScroll);
                });
            }

            function scrollContainer(sectionClass, direction) {
                const container = document.querySelector(`.${sectionClass} .scroll-container`);
                const cardWidth = container.querySelector('.product-card').offsetWidth + 20;
                container.scrollBy({
                    left: cardWidth * direction,
                    behavior: 'smooth'
                });
            }

            setupScroll('new-arrivals');
            setupScroll('top-selling');
            window.scrollContainer = scrollContainer; // Expose for onclick handlers
        });
    </script>
</body>
</html>