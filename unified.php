<?php
require_once 'config.php'; // Include database connection from index.php

// Fetch content from database
$content = [];
try {
    $result = $conn->query("SELECT page_key, title, description, meta_description, sections FROM static_pages");
    while ($row = $result->fetch_assoc()) {
        $row['sections'] = json_decode($row['sections'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for page {$row['page_key']}: " . json_last_error_msg());
            $row['sections'] = [];
        }
        $content[$row['page_key']] = [
            'title' => $row['title'],
            'description' => $row['description'],
            'meta_description' => $row['meta_description'],
            'sections' => $row['sections']
        ];
    }
} catch (Exception $e) {
    error_log("Fetch static pages error: " . $e->getMessage());
}

// Fallback to default content if database query fails
if (empty($content)) {
    $content = [
        'about' => [
            'title' => 'About Deeken',
            'description' => 'Deeken is your one-stop shop for stylish clothing that matches your personality.',
            'sections' => [
                ['heading' => 'Our Mission', 'text' => 'To provide high-quality, fashionable clothing.'],
                ['heading' => 'Our Story', 'text' => 'Founded in 2020, Deeken started with a vision.']
            ],
            'meta_description' => 'Learn about Deeken, your destination for stylish clothing.'
        ],
        // Add other pages similarly for fallback
    ];
}

// Get the requested page and section from URL
$page = isset($_GET['page']) ? strtolower(trim($_GET['page'])) : 'about';
$section = isset($_GET['section']) ? strtolower(trim($_GET['section'])) : null;

// Validate page
if (!array_key_exists($page, $content)) {
    $page = 'about'; // Default to 'about' if invalid
}

// Fetch user and cart count
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

// Handle add to cart
if ($_POST['action'] ?? '' === 'add_to_cart') {
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Please login to add items to cart.']);
        exit;
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id > 0) {
        $product_check = $conn->prepare("SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE p.id = ?");
        $product_check->bind_param("i", $product_id);
        $product_check->execute();
        $product = $product_check->get_result()->fetch_assoc();
        $product_check->close();
        
        if ($product && ($product['stock_quantity'] > 0 || $product['stock_quantity'] === null)) {
            $cart_check = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $cart_check->bind_param("ii", $user['id'], $product_id);
            $cart_check->execute();
            $existing = $cart_check->get_result()->fetch_assoc();
            $cart_check->close();
            
            if ($existing) {
                $new_quantity = $existing['quantity'] + $quantity;
                $update_cart = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
                $update_cart->bind_param("iii", $new_quantity, $user['id'], $product_id);
                $success = $update_cart->execute();
                $update_cart->close();
            } else {
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
    <meta name="description" content="<?php echo htmlspecialchars($content[$page]['meta_description']); ?>">
    <title><?php echo htmlspecialchars($content[$page]['title']); ?> - Deeken</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="responsive-index.css">
    <style>
        /* Content Section */
        .content-section {
            padding: 80px 5%;
            background: var(--primary-white);
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease-out;
        }
        .content-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .content-header h1 {
            font-size: clamp(28px, 5vw, 40px);
            font-weight: 800;
            color: var(--primary-black);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        .content-header h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient);
            border-radius: 2px;
        }
        .content-header p {
            font-size: 18px;
            color: var(--text-gray);
            margin-top: 20px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .content-body {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }
        .content-item {
            background: var(--light-gray);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--medium-gray);
            transition: var(--transition);
        }
        .content-item:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-color);
        }
        .content-item h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-black);
            margin-bottom: 16px;
        }
        .content-item p {
            font-size: 16px;
            color: var(--text-gray);
            line-height: 1.6;
        }
        /* FAQ Specific Styling */
        .faq-item {
            cursor: pointer;
        }
        .faq-item.active {
            background: var(--primary-white);
        }
        /* Notification styles (from index.php) */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 3000;
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
        /* Responsive Design for Content Section */
        @media (max-width: 768px) {
            .content-section {
                padding: 40px 4%;
            }
            .content-header h1 {
                font-size: 28px;
            }
            .content-header p {
                font-size: 16px;
            }
            .content-item {
                padding: 20px;
            }
            .content-item h2 {
                font-size: 20px;
            }
        }
        @media (max-width: 480px) {
            .content-section {
                padding: 30px 4%;
            }
            .content-header h1 {
                font-size: 24px;
            }
            .content-item h2 {
                font-size: 18px;
            }
            .content-item p {
                font-size: 14px;
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
            <div class="profile-dropdown">
                <?php if ($user): ?>
                    <?php
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
            <div class="hamburger-menu" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    
    </nav>

    <!-- Content Section -->
    <section class="content-section">
        <div class="content-header">
            <h1><?php echo htmlspecialchars($content[$page]['title']); ?></h1>
            <p><?php echo htmlspecialchars($content[$page]['description']); ?></p>
        </div>
        <div class="content-body">
            <?php foreach ($content[$page]['sections'] as $section_data): ?>
                <div class="content-item <?php echo $page === 'faq' ? 'faq-item' : ''; ?>" <?php echo isset($section_data['id']) ? 'id="' . htmlspecialchars($section_data['id']) . '"' : ''; ?>>
                    <h2><?php echo htmlspecialchars($section_data['heading']); ?></h2>
                    <p><?php echo $section_data['text']; ?></p>
                </div>
            <?php endforeach; ?>
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
                const response = await fetch('unified.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
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

        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
        }

        async function markNotificationRead(notificationId, type, orderId) {
            try {
                const formData = new FormData();
                formData.append('action', 'mark_notification_read');
                formData.append('notification_id', notificationId);
                const response = await fetch('unified.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
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
                    if (orderId) window.location.href = `orders.php?id=${orderId}`;
                }
            } catch (error) {
                console.error('Mark Notification Error:', error);
            }
        }

        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('active');
        }

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
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.querySelector('.profile-trigger');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationTrigger = document.querySelector('.notification-btn');
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
            if (!notificationTrigger.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('active');
            }
        });

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }

        function closeBanner() {
            document.querySelector('.top-banner').style.display = 'none';
        }

        <?php if ($page === 'faq' && $section): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const section = document.getElementById('<?php echo htmlspecialchars($section); ?>');
                if (section) {
                    section.scrollIntoView({ behavior: 'smooth' });
                    section.classList.add('active');
                    setTimeout(() => section.classList.remove('active'), 2000);
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>