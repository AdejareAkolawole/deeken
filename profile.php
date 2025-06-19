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
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="profile-styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="hamburger.css">
    <style>
        .navbar {
            background: rgba(255, 255, 255, 0.95);
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
            color: #2A2aff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        /* Enhanced Footer */
.footer {
    background: var(--light-gray);
    padding: 60px 5% 30px;
}

.footer-content {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.footer-brand h3 {
    font-size: 32px;
    font-weight: 900;
    color: var(--primary-black);
    margin-bottom: 16px;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.footer-brand p {
    font-size: 16px;
    color: var(--text-gray);
    line-height: 1.6;
    margin-bottom: 24px;
    max-width: 300px;
}

.social-icons {
    display: flex;
    gap: 16px;
}

.social-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-black);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
}

.social-icon:hover {
    background: var(--accent-color);
    transform: translateY(-3px) scale(1.1);
    box-shadow: var(--shadow);
}

.footer-column h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-black);
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.footer-column ul {
    list-style: none;
}

.footer-column ul li {
    margin-bottom: 12px;
}

.footer-column ul li a {
    color: var(--text-gray);
    text-decoration: none;
    transition: var(--transition);
    font-weight: 500;
}

.footer-column ul li a:hover {
    color: var(--accent-color);
    transform: translateX(4px);
}

.footer-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 30px;
    border-top: 1px solid var(--medium-gray);
    flex-wrap: wrap;
    gap: 20px;
}

.footer-bottom p {
    color: var(--text-gray);
    font-weight: 500;
}

.payment-icons {
    display: flex;
    gap: 12px;
}

.payment-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-white);
    border: 1px solid var(--medium-gray);
    border-radius: var(--border-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: var(--transition);
}

.payment-icon:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--accent-color);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .hero {
        flex-direction: column;
        gap: 40px;
        text-align: center;
        padding: 60px 5%;
    }
    
    .hero-image {
        order: -1;
    }
    
    .hero-couple {
        max-width: 400px;
        height: 500px;
    }
    
    .nav-links {
        display: none;
    }
    
    .hamburger {
        display: flex;
    }
    
    .search-bar {
        width: 300px;
    }
    
    .stats {
        flex-wrap: wrap;
        gap: 40px;
    }
    
    .footer-content {
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
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
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            border: none;
            padding: 12px;
            border-radius: 50px;
            color: white;
            cursor: pointer;
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
        }

        .cart-count {
            background: #ff6b35;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
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
            border: 1px solid #e0e0e0;
            background: white;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-greeting {
            font-size: 12px;
            color: #666;
        }

        .profile-account {
            font-size: 14px;
            font-weight: 500;
            color: #333;
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
        }

        .profile {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .profile h2, .profile h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333;
        }

        .input-field, textarea, select {
            width: 100%;
            padding: 12px;
            margin: 0.5rem 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .address-item, .order-item {
            background: #f8f9fa;
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .address-item.default {
            border-color: #2a2aff;
            background: #f0f8ff;
        }

        .address-item a {
            margin-right: 1rem;
            color: #2a2aff;
            text-decoration: none;
            font-size: 14px;
        }

        .address-item a:hover {
            text-decoration: underline;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 12px 24px;
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin: 0.5rem 0;
            text-decoration: none;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .success, .error {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 1rem;
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

        .orders-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
            border: 1px solid #e0e0e0;
        }

        .order-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .notification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .notification-modal.show {
            opacity: 1;
        }

        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
        }

        .modal-content p {
            margin: 0 0 15px;
            font-size: 16px;
            color: #333;
        }

        .modal-content p i {
            color: #e74c3c;
            margin-right: 8px;
        }

        .modal-content button {
            background: #3498db;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        footer {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            color: #666;
            margin-top: 2rem;
        }

        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .no-addresses {
            text-align: center;
            padding: 2rem;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
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
    <footer>
        <p><i class="fas fa-copyright"></i> 2025 Deeken. All rights reserved.</p>
    </footer>

    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
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

            // Navbar scroll behavior
            let lastScrollTop = 0;
            const navbar = document.querySelector('.navbar');
            window.addEventListener('scroll', () => {
                let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                if (currentScroll > lastScrollTop) {
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