<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireLogin();

$user = getCurrentUser();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ----- UPDATE PROFILE -----
if (isset($_POST['update_profile']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $full_name = sanitize($conn, $_POST['full_name']);
    $phone = sanitize($conn, $_POST['phone']);

    // Validate phone (basic regex for digits, 7-15 characters)
    if (!preg_match('/^\d{7,15}$/', $phone)) {
        header("Location: profile.php?error=" . urlencode("Invalid phone number"));
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $full_name, $phone, $user['id']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['user']['full_name'] = $full_name;
    $_SESSION['user']['phone'] = $phone;
    error_log("Profile updated for user ID {$user['id']}");
    header("Location: profile.php?message=" . urlencode("Profile updated successfully"));
    exit;
}

// ----- ADD/UPDATE ADDRESS -----
if (isset($_POST['update_address']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $address_id = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
    $full_name = sanitize($conn, $_POST['address_full_name']);
    $street_address = sanitize($conn, $_POST['street_address']);
    $city = sanitize($conn, $_POST['city']);
    $state = sanitize($conn, $_POST['state']);
    $country = sanitize($conn, $_POST['country']);
    $postal_code = sanitize($conn, $_POST['postal_code']);
    $phone = sanitize($conn, $_POST['address_phone']);

    // Validate inputs
    if (strlen($street_address) > 255 || strlen($full_name) > 255) {
        header("Location: profile.php?error=" . urlencode("Name or address too long"));
        exit;
    }
    if (!preg_match('/^\d{4,10}$/', $postal_code)) {
        header("Location: profile.php?error=" . urlencode("Invalid postal code"));
        exit;
    }
    if (!preg_match('/^\d{7,15}$/', $phone)) {
        header("Location: profile.php?error=" . urlencode("Invalid phone number"));
        exit;
    }

    // Check if user already has an address
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM addresses WHERE user_id = ?");
    $stmt_check->bind_param("i", $user['id']);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($address_id > 0) {
        // Update existing address
        $stmt = $conn->prepare("UPDATE addresses SET full_name = ?, street_address = ?, city = ?, state = ?, country = ?, postal_code = ?, phone = ?, is_default = 1, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssssssii", $full_name, $street_address, $city, $state, $country, $postal_code, $phone, $address_id, $user['id']);
    } else {
        // Check if user already has an address
        if ($result['count'] > 0) {
            header("Location: profile.php?error=" . urlencode("You can only have one address. Please edit your existing address."));
            exit;
        }
        // Insert new address (always default)
        $stmt = $conn->prepare("INSERT INTO addresses (user_id, full_name, street_address, city, state, country, postal_code, phone, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param("isssssssi", $user['id'], $full_name, $street_address, $city, $state, $country, $postal_code, $phone, 1);
    }

    $stmt->execute();
    $stmt->close();
    error_log("Address " . ($address_id > 0 ? "updated" : "added") . " for user ID {$user['id']}");
    header("Location: profile.php?message=" . urlencode("Address saved successfully"));
    exit;
}

// ----- DELETE ADDRESS -----
if (isset($_GET['delete_address']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $address_id = (int)$_GET['delete_address'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE address_id = ?");
    $stmt->bind_param("i", $address_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] == 0) {
        $stmt = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $address_id, $user['id']);
        $stmt->execute();
        error_log("Address ID $address_id deleted for user ID {$user['id']}");
        header("Location: profile.php?message=" . urlencode("Address deleted successfully"));
    } else {
        error_log("Attempt to delete address ID $address_id failed: in use");
        header("Location: profile.php?error=" . urlencode("Address in use, cannot be deleted"));
    }
    $stmt->close();
    exit;
}

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #fff;
            font-size: clamp(14px, 2.5vw, 16px);
        }

        /* Variables */
        :root {
            --primary-blue: #2a2aff;
            --light-blue: #bdf3ff;
            --accent-orange: #ff6b35;
            --text-gray: #666;
            --light-gray: #f8f9fa;
            --medium-gray: #e0e0e0;
            --primary-black: #333;
            --primary-white: #fff;
            --gradient: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
            --transition: all 0.3s ease;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --border-radius-sm: 8px;
            --border-radius-lg: 12px;
        }

        /* Notification Modal */
        .notification-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            transition: opacity var(--transition);
        }

        .notification-modal.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: var(--primary-white);
            padding: 1.5rem;
            border-radius: var(--border-radius-sm);
            max-width: 90%;
            width: 400px;
            text-align: center;
            box-shadow: var(--shadow);
            animation: slideIn 0.3s ease;
        }

        .modal-content p {
            margin-bottom: 1rem;
            font-size: clamp(14px, 2.5vw, 16px);
        }

        .modal-content p i {
            color: #e74c3c;
            margin-right: 0.5rem;
        }

        .modal-content button {
            background: #3498db;
            color: var(--primary-white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(12px, 2vw, 14px);
            transition: var(--transition);
        }

        .modal-content button:hover {
            background: #2980b9;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: transform var(--transition);
        }

        .navbar.hidden {
            transform: translateY(-100%);
        }

        .logo {
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            font-weight: 600;
            color: var(--primary-blue);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Search Bar */
        .search-bar {
            flex: 1;
            max-width: 100%;
            margin: 0.5rem 0;
            position: relative;
            display: none;
        }

        .search-bar.show {
            display: flex;
        }

        .search-bar input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid var(--medium-gray);
            border-radius: 50px;
            font-size: clamp(12px, 2vw, 14px);
            outline: none;
            background: var(--primary-white);
        }

        .search-bar button {
            background: var(--gradient);
            border: none;
            padding: 0.75rem;
            border-radius: 50px;
            color: var(--primary-white);
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        /* Nav Right */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .cart-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--primary-black);
            font-weight: 500;
            padding: 0.5rem;
            border-radius: 25px;
        }

        .cart-count {
            background: var(--accent-orange);
            color: var(--primary-white);
            border-radius: 50%;
            width: 1.25rem;
            height: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--medium-gray);
            background: var(--primary-white);
        }

        .profile-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: var(--gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-white);
        }

        .profile-info {
            display: none;
        }

        .profile-greeting,
        .profile-account {
            font-size: clamp(10px, 1.5vw, 12px);
        }

        .profile-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--primary-white);
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            min-width: 180px;
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
        }

        .profile-dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: var(--primary-black);
            font-size: clamp(12px, 2vw, 14px);
        }

        .profile-dropdown-menu a:hover {
            background: var(--light-gray);
        }

        /* Hamburger Menu */
        .hamburger {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 1.5rem;
            height: 1.2rem;
            cursor: pointer;
            background: none;
            border: none;
        }

        .hamburger span {
            display: block;
            width: 100%;
            height: 2px;
            background: var(--primary-black);
            transition: var(--transition);
        }

        body[data-mobile-nav-open="true"] .hamburger span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }

        body[data-mobile-nav-open="true"] .hamburger span:nth-child(2) {
            opacity: 0;
        }

        body[data-mobile-nav-open="true"] .hamburger span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
        }

        /* Mobile Nav */
        .mobile-nav-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        body[data-mobile-nav-open="true"] .mobile-nav-overlay {
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav {
            position: fixed;
            top: 0;
            right: 0;
            width: 80%;
            max-width: 300px;
            height: 100%;
            background: var(--primary-white);
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
            transform: translateX(100%);
            transition: transform var(--transition);
            z-index: 1000;
        }

        body[data-mobile-nav-open="true"] .mobile-nav {
            transform: translateX(0);
        }

        .mobile-nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--medium-gray);
        }

        .mobile-nav-title {
            font-size: clamp(1rem, 2.5vw, 1.2rem);
        }

        .mobile-nav-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .mobile-nav-links {
            list-style: none;
            padding: 1rem;
        }

        .mobile-nav-links li a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            text-decoration: none;
            color: var(--primary-black);
            font-size: clamp(14px, 2.5vw, 16px);
        }

        .mobile-nav-links li a:hover {
            background: var(--light-gray);
        }

        /* Profile Section */
        .profile {
            max-width: 100%;
            margin: 1rem;
            padding: 0 0.5rem;
        }

        .profile h2,
        .profile h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-black);
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            margin-bottom: 1rem;
        }

        /* Forms and Inputs */
        .input-field,
        textarea,
        select {
            width: 100%;
            padding: 0.75rem;
            margin: 0.5rem 0;
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius-sm);
            font-size: clamp(12px, 2vw, 14px);
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
            transition: border-color var(--transition);
        }

        .input-field:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary-blue);
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--gradient);
            color: var(--primary-white);
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: clamp(14px, 2.5vw, 16px);
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background: #6c757d;
        }

        /* Messages */
        .success,
        .error {
            padding: 0.75rem;
            border-radius: var(--border-radius-sm);
            margin-bottom: 1rem;
            font-size: clamp(12px, 2vw, 14px);
        }

        .success {
            background: #e6ffed;
            color: #1a7e3a;
            border: 1px solid #4caf50;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f44336;
        }

        /* Addresses */
        .address-item,
        .order-item {
            background: var(--light-gray);
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--medium-gray);
            font-size: clamp(12px, 2vw, 14px);
        }

        .address-item.default {
            border-color: var(--primary-blue);
            background: #f0f8ff;
        }

        .address-item a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: clamp(12px, 2vw, 14px);
            margin-right: 0.75rem;
        }

        .address-item a:hover {
            text-decoration: underline;
        }

        .no-addresses {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-gray);
            background: var(--light-gray);
            border-radius: var(--border-radius-sm);
            margin: 1rem 0;
        }

        .no-addresses i {
            font-size: 2rem;
            color: #ccc;
            margin-bottom: 0.5rem;
        }

        /* Orders Section */
        .orders-section {
            background: var(--light-gray);
            padding: 1rem;
            border-radius: var(--border-radius-lg);
            margin: 1rem 0;
            border: 1px solid var(--medium-gray);
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

        /* Footer */
        .footer {
            background: var(--light-gray);
            padding: 2rem 1rem;
            text-align: center;
            color: var(--text-gray);
            font-size: clamp(12px, 2vw, 14px);
            margin-top: 2rem;
        }

        /* Animations */
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Tablet and Desktop Styles */
        @media (min-width: 768px) {
            .navbar {
                padding: 1rem 2rem;
            }

            .search-bar {
                display: flex;
                max-width: 500px;
                margin: 0 1rem;
            }

            .profile-info {
                display: flex;
                flex-direction: column;
            }

            .profile {
                max-width: 800px;
                margin: 2rem auto;
                padding: 0 1rem;
            }

            .hamburger {
                display: none;
            }

            .mobile-nav,
            .mobile-nav-overlay {
                display: none;
            }
        }

        @media (min-width: 1024px) {
            .footer {
                padding: 3rem 5%;
            }
        }
    </style>
</head>
<body data-mobile-nav-open="false">
    <!-- Notification Modal -->
    <div id="profileIncompleteModal" class="notification-modal" style="display: none;">
        <div class="modal-content">
            <p><i class="fas fa-exclamation-circle"></i> Please complete your profile information to continue.</p>
            <button onclick="closeModal()">OK</button>
        </div>
    </div>

    <header>
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
                    <span class="cart-count"><?php echo htmlspecialchars($cart_count); ?></span>
                </a>
                <div class="profile-dropdown">
                    <?php if ($user): ?>
                        <div class="profile-trigger">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-info">
                                <span class="profile-greeting">Hi, <?php echo htmlspecialchars($user['full_name'] ?? $user['email'] ?? 'User'); ?></span>
                                <span class="profile-account">My Account <i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="profile-trigger">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-info">
                                <span class="profile-greeting">Hi, Guest</span>
                                <span class="profile-account">Sign In <i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="profile-dropdown-menu" id="profileDropdown">
                        <?php if ($user): ?>
                            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
                            <a href="index.php"><i class="fas fa-home"></i> Home</a>
                            <?php if ($user['is_admin']): ?>
                                <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
                            <?php endif; ?>
                            <hr class="dropdown-divider">
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        <?php else: ?>
                            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign In</a>
                            <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
                            <hr class="dropdown-divider">
                            <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="hamburger" id="mobileNavToggle" aria-label="Toggle mobile navigation">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </nav>
        <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
        <div class="mobile-nav" id="mobileNav" aria-hidden="true" role="navigation" aria-label="Mobile navigation">
            <div class="mobile-nav-header">
                <h2 class="mobile-nav-title">Menu</h2>
                <button class="mobile-nav-close" id="mobileNavClose" aria-label="Close navigation menu">✕</button>
            </div>
            <ul class="mobile-nav-links">
                <li><a href="index.php"><span class="nav-icon">🏠</span>Home</a></li>
                <li><a href="cart.php"><span class="nav-icon">🛒</span>Cart (<span class="cart-count"><?php echo htmlspecialchars($cart_count); ?></span>)</a></li>
                <li><a href="profile.php"><span class="nav-icon">👤</span>Profile</a></li>
                <?php if ($user['is_admin']): ?>
                    <li><a href="admin.php"><span class="nav-icon">⚙️</span>Admin</a></li>
                <?php endif; ?>
                <li><a href="logout.php"><span class="nav-icon">🔐</span>Logout</a></li>
            </ul>
        </div>
    </header>

    <main>
        <section class="profile">
            <?php if (isset($_GET['message'])): ?>
                <div class="success"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="error"><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></div>
            <?php endif; ?>

            <!-- Profile Information Section -->
            <h2><i class="fas fa-user"></i> Your Profile</h2>
            <div id="profileInfo">
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="full_name">Full Name:</label>
                    <input type="text" id="full_name" name="full_name" class="input-field" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    <label for="phone">Phone:</label>
                    <input type="text" id="phone" name="phone" class="input-field" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="\d{7,15}" title="Phone number must be 7-15 digits" required>
                    <button type="submit" name="update_profile" class="btn"><i class="fas fa-save"></i> Update Profile</button>
                </form>
            </div>

            <!-- Orders Section -->
            <div class="orders-section">
                <h3><i class="fas fa-box"></i> Recent Orders</h3>
                <?php
                $stmt = $conn->prepare("
                    SELECT o.*, df.name AS delivery_fee_name, df.fee AS delivery_fee,
                           GROUP_CONCAT(CONCAT(p.name, ' (Qty: ', oi.quantity, ')')) AS products
                    FROM orders o
                    LEFT JOIN delivery_fees df ON o.delivery_fee_id = df.id
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    WHERE o.user_id = ?
                    GROUP BY o.id
                    ORDER BY o.created_at DESC
                    LIMIT 3
                ");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $orders = $stmt->get_result();
                if ($orders->num_rows == 0): ?>
                    <p>No orders found. Start shopping to place your first order!</p>
                <?php else: ?>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <div class="order-item">
                            <p><strong>Order #<?php echo $order['id']; ?></strong> - $<?php echo number_format($order['total'], 2); ?> (<?php echo ucfirst($order['status']); ?>)</p>
                            <p>Products: <?php echo htmlspecialchars($order['products']); ?></p>
                            <p>Delivery: <?php echo htmlspecialchars($order['delivery_fee_name'] ?? 'N/A'); ?> ($<?php echo number_format($order['delivery_fee'] ?? 0, 2); ?>)</p>
                            <p>Date: <?php echo $order['created_at']; ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <a href="orders.php" class="btn"><i class="fas fa-list"></i> View All Orders</a>
                <?php $stmt->close(); ?>
            </div>

            <!-- Addresses Section -->
            <h3><i class="fas fa-map-marker-alt"></i> Your Address</h3>
            <div id="addressList">
                <?php
                $stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $addresses = $stmt->get_result();
                if ($addresses->num_rows == 0): ?>
                    <div class="no-addresses">
                        <i class="fas fa-map-marker-alt" style="font-size: 2rem; color: #ccc; margin-bottom: 1rem;"></i>
                        <p>No address found. Add your address to start shopping!</p>
                        <button class="btn" onclick="toggleAddressForm(true)"><i class="fas fa-plus"></i> Add Address</button>
                    </div>
                <?php else: ?>
                    <?php $address = $addresses->fetch_assoc(); ?>
                    <div class="address-item default">
                        <p><strong><?php echo htmlspecialchars($address['full_name']); ?></strong>
                            <span style="background: #2a2aff; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 8px;">Default</span></p>
                        <p><?php echo htmlspecialchars($address['street_address']); ?></p>
                        <p><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?>, <?php echo htmlspecialchars($address['country']); ?> <?php echo htmlspecialchars($address['postal_code']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($address['phone']); ?></p>
                        <div style="margin-top: 0.5rem;">
                            <a href="?edit_address=<?php echo $address['id']; ?>&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><i class="fas fa-edit"></i> Edit Address</a>
                            <a href="?delete_address=<?php echo $address['id']; ?>&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" onclick="return confirm('Are you sure you want to delete this address?');"><i class="fas fa-trash"></i> Delete Address</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php $stmt->close(); ?>
            </div>

            <!-- Address Form -->
            <div id="addressForm" style="display:<?php echo isset($_GET['edit_address']) ? 'block' : 'none'; ?>;">
                <h3><?php echo isset($_GET['edit_address']) ? 'Edit Address' : 'Add New Address'; ?></h3>
                <?php
                $edit_address = [];
                if (isset($_GET['edit_address'])) {
                    $address_id = (int)$_GET['edit_address'];
                    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
                        header("Location: profile.php?error=" . urlencode("Invalid CSRF token"));
                        exit;
                    }
                    $stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $address_id, $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $edit_address = $result->fetch_assoc();
                    } else {
                        error_log("Invalid address_id: $address_id for user_id: {$user['id']}");
                        header("Location: profile.php?error=" . urlencode("Invalid address"));
                        exit;
                    }
                    $stmt->close();
                }
                ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($edit_address['id'] ?? '0'); ?>">
                    <label for="address_full_name">Full Name:</label>
                    <input type="text" id="address_full_name" name="address_full_name" class="input-field" value="<?php echo htmlspecialchars($edit_address['full_name'] ?? ''); ?>" maxlength="255" required>
                    <label for="street_address">Street Address:</label>
                    <textarea id="street_address" name="street_address" class="input-field" maxlength="255" required><?php echo htmlspecialchars($edit_address['street_address'] ?? ''); ?></textarea>
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city" class="input-field" value="<?php echo htmlspecialchars($edit_address['city'] ?? ''); ?>" maxlength="100" required>
                    <label for="state">State/Province:</label>
                    <input type="text" id="state" name="state" class="input-field" value="<?php echo htmlspecialchars($edit_address['state'] ?? ''); ?>" maxlength="100" required>
                    <label for="country">Country:</label>
                    <input type="text" id="country" name="country" class="input-field" value="<?php echo htmlspecialchars($edit_address['country'] ?? ''); ?>" maxlength="100" required>
                    <label for="postal_code">Postal Code:</label>
                    <input type="text" id="postal_code" name="postal_code" class="input-field" value="<?php echo htmlspecialchars($edit_address['postal_code'] ?? ''); ?>" pattern="\d{4,10}" title="Postal code must be 4-10 digits" required>
                    <label for="address_phone">Phone:</label>
                    <input type="text" id="address_phone" name="address_phone" class="input-field" value="<?php echo htmlspecialchars($edit_address['phone'] ?? ''); ?>" pattern="\d{7,15}" title="Phone number must be 7-15 digits" required>
                    <div class="form-buttons">
                        <button type="submit" name="update_address" class="btn"><i class="fas fa-save"></i> Save Address</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleAddressForm(false)">Cancel</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
    <footer class="footer">
        <p><i class="fas fa-copyright"></i> 2025 Deeken. All rights reserved.</p>
    </footer>

    <script>
        // Toggle profile dropdown
        function toggleProfileDropdown(event) {
            event?.preventDefault();
            event?.stopPropagation();
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        // Search products
        function searchProducts() {
            const query = document.getElementById('searchInput').value.trim();
            if (query) {
                window.location.href = `index.php?search=${encodeURIComponent(query)}`;
            }
        }

        // Toggle address form
        function toggleAddressForm(show) {
            const form = document.getElementById('addressForm');
            form.style.display = show ? 'block' : 'none';
            if (!show) window.history.replaceState({}, '', 'profile.php');
        }

        // Toggle mobile nav
        function toggleMobileNav() {
            const body = document.body;
            const isOpen = body.getAttribute('data-mobile-nav-open') === 'true';
            body.setAttribute('data-mobile-nav-open', !isOpen);
            document.getElementById('mobileNav').setAttribute('aria-hidden', isOpen);
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', () => {
            // Profile dropdown
            const profileTrigger = document.querySelector('.profile-trigger');
            if (profileTrigger) profileTrigger.addEventListener('click', toggleProfileDropdown);

            // Close dropdown on outside click
            document.addEventListener('click', (event) => {
                const dropdown = document.getElementById('profileDropdown');
                if (!event.target.closest('.profile-dropdown') && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            });

            // Mobile nav toggle
            const mobileNavToggle = document.getElementById('mobileNavToggle');
            const mobileNavClose = document.getElementById('mobileNavClose');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            if (mobileNavToggle) mobileNavToggle.addEventListener('click', toggleMobileNav);
            if (mobileNavClose) mobileNavClose.addEventListener('click', toggleMobileNav);
            if (mobileNavOverlay) mobileNavOverlay.addEventListener('click', toggleMobileNav);

            // Navbar scroll behavior
            let lastScrollTop = 0;
            const navbar = document.querySelector('.navbar');
            window.addEventListener('scroll', () => {
                let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                if (currentScroll > lastScrollTop && currentScroll > 100) {
                    navbar.classList.add('hidden');
                } else {
                    navbar.classList.remove('hidden');
                }
                lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
            });

            // Check if profile is incomplete
            const user = <?php echo json_encode($user); ?>;
            if (!user.full_name || !user.phone) {
                const modal = document.getElementById('profileIncompleteModal');
                modal.style.display = 'flex';
                modal.classList.add('show');
                setTimeout(() => {
                    modal.classList.remove('show');
                    setTimeout(() => modal.style.display = 'none', 300);
                }, 5000);
            }

            // Add search bar toggle for mobile
            const searchButton = document.querySelector('.search-bar button');
            if (searchButton) {
                searchButton.addEventListener('click', () => {
                    const searchBar = document.querySelector('.search-bar');
                    if (window.innerWidth < 768 && !searchBar.classList.contains('show')) {
                        searchBar.classList.add('show');
                        document.getElementById('searchInput').focus();
                        event.preventDefault();
                    }
                });
            }
        });

        // Close modal
        function closeModal() {
            const modal = document.getElementById('profileIncompleteModal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>