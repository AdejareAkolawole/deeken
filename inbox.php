<?php
require_once 'config.php'; // Your database connection file

// Fetch the current user
$user = getCurrentUser();

// Check if user is logged in
if (!$user || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark notifications as read when viewing inbox
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// Fetch notifications
$stmt = $conn->prepare("
    SELECT n.*, o.status as order_status
    FROM notifications n
    LEFT JOIN orders o ON n.order_id = o.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch cart count for navigation
$cartCount = getCartCount($conn, $user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - Deeken</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Import styles from index.css */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        :root {
            --primary-black: #1a1a1a;
            --primary-white: #ffffff;
            --accent-color: #6366f1;
            --accent-secondary: #8b5cf6;
            --text-gray: #6b7280;
            --light-gray: #f8fafc;
            --medium-gray: #e5e7eb;
            --dark-gray: #374151;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-accent: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            --border-radius-sm: 8px;
            --border-radius: 12px;
            --border-radius-lg: 20px;
            --border-radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Custom Scrollbars */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-gray);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gradient-accent);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gradient);
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: var(--accent-color) var(--light-gray);
        }

        body {
            line-height: 1.6;
            color: var(--primary-black);
            overflow-x: hidden;
            background: var(--primary-white);
        }

        /* Animations from index.css */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
        }

        /* Top Banner */
        .top-banner {
            background: var(--gradient);
            color: white;
            padding: 16px 0;
            font-size: 14px;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            box-shadow: var(--shadow);
        }

        .marquee-container {
            display: flex;
            align-items: center;
            height: 100%;
        }

        .marquee-content {
            display: flex;
            animation: marquee 25s linear infinite;
            white-space: nowrap;
        }

        .marquee-text {
            padding-right: 120px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        @keyframes marquee {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        .top-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 4s infinite;
            z-index: 1;
        }

        .close-btn {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-btn:hover {
            transform: translateY(-50%) rotate(90deg) scale(1.1);
            background: rgba(255, 255, 255, 0.2);
        }

        /* Navigation */
        .navbar {
            padding: 20px 5%;
            border-bottom: 1px solid var(--medium-gray);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }

        .navbar.scrolled {
            box-shadow: var(--shadow-md);
            padding: 16px 5%;
        }

        .logo {
            font-size: 32px;
            font-weight: 900;
            color: var(--primary-black);
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            cursor: pointer;
            transition: var(--transition);
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .search-container {
            width: 100%;
            max-width: 500px;
            position: relative;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: var(--light-gray);
            border-radius: 50px;
            padding: 14px 24px;
            width: 400px;
            transition: var(--transition);
            border: 2px solid transparent;
            margin: 0 auto;
        }

        .search-bar:focus-within {
            box-shadow: var(--shadow-md);
            border-color: var(--accent-color);
            background: var(--primary-white);
        }

        .search-bar i {
            color: var(--text-gray);
            margin-right: 12px;
            font-size: 18px;
        }

        .search-bar input {
            border: none;
            background: none;
            outline: none;
            flex: 1;
            font-size: 16px;
            color: var(--primary-black);
        }

        .search-bar input::placeholder {
            color: var(--text-gray);
        }

        .nav-icons {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .nav-icons button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 12px;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-icons button:hover {
            background: var(--light-gray);
            transform: translateY(-3px) scale(1.05);
            box-shadow: var(--shadow);
        }

        .nav-icons button i {
            font-size: 20px;
            color: var(--primary-black);
        }

        .cart-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--gradient-accent);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
            box-shadow: var(--shadow);
            border: 2px solid var(--primary-white);
        }

        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            cursor: pointer;
            padding: 12px;
            border-radius: 50%;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            background: var(--accent-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-white);
            font-size: 18px;
            margin-right: 8px;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .profile-greeting {
            font-size: 14px;
            color: var(--primary-black);
            font-weight: 500;
        }

        .profile-account {
            font-size: 12px;
            color: var(--text-gray);
            font-weight: 400;
        }

        .profile-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--primary-white);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-md);
            padding: 8px 0;
            min-width: 200px;
            z-index: 1000;
            border: 1px solid var(--medium-gray);
        }

        .profile-dropdown-menu.active {
            display: block;
        }

        .profile-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            color: var(--primary-black);
            text-decoration: none;
            font-weight: 500;
        }

        .profile-dropdown-menu a:hover {
            background: var(--light-gray);
            color: var(--accent-color);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--medium-gray);
            margin: 4px 0;
        }

        .notification-dot {
            background: var(--error-color);
            color: var(--primary-white);
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 12px;
            margin-left: 5px;
        }

        /* Inbox Section */
        .inbox-section {
            padding: 80px 5%;
            background: var(--light-gray);
            min-height: 70vh;
        }

        .inbox-container {
            max-width: 1000px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease-out;
        }

        .inbox-title {
            font-size: clamp(28px, 5vw, 40px);
            font-weight: 800;
            text-align: center;
            margin-bottom: 60px;
            color: var(--primary-black);
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
        }

        .inbox-title::after {
            content: '';
            position: absolute;
            bottom: -16px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient);
            border-radius: 2px;
        }

        .notification {
            background: var(--primary-white);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--medium-gray);
            position: relative;
            overflow: hidden;
        }

        .notification:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-color);
        }

        .notification::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(99,102,241,0.1), transparent);
            transition: var(--transition);
        }

        .notification:hover::before {
            left: 100%;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .notification-type {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-black);
            text-transform: capitalize;
        }

        .notification-date {
            font-size: 14px;
            color: var(--text-gray);
            font-weight: 400;
        }

        .notification-message {
            font-size: 16px;
            color: var(--text-gray);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .notification-order {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
        }

        .notification-order:hover {
            background: rgba(99, 102, 241, 0.05);
            transform: translateY(-2px);
        }

        .no-notifications {
            text-align: center;
            color: var(--text-gray);
            font-size: 18px;
            padding: 40px;
            background: var(--primary-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--medium-gray);
        }

        /* Footer */
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
            cursor: pointer;
            transition: var(--transition);
        }

        .social-icon:hover {
            background: var(--accent-color);
            transform: translateY(-3px) scale(1.1);
        }

        .footer-column h4 {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-black);
            margin-bottom: 20px;
            text-transform: uppercase;
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
            .inbox-section {
                padding: 60px 5%;
            }

            .search-bar {
                width: 300px;
            }

            .footer-content {
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 16px 5%;
            }

            .search-container {
                display: none;
            }

            .inbox-title {
                font-size: 28px;
            }

            .notification {
                padding: 16px;
            }

            .notification-type {
                font-size: 16px;
            }

            .notification-message {
                font-size: 14px;
            }

            .notification-order {
                font-size: 14px;
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                padding: 12px 4%;
            }

            .logo {
                font-size: 24px;
            }

            .inbox-section {
                padding: 40px 4%;
            }

            .inbox-title {
                font-size: 24px;
            }

            .no-notifications {
                font-size: 16px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Banner -->
    <div class="top-banner">
        <div class="marquee-container">
            <div class="marquee-content">
                <span class="marquee-text">🎉 Sign up and get 20% off your first order. <a href="register.php" style="color: #ff6f61;">Sign Up Now</a></span>
                <span class="marquee-text">✨ Free shipping on orders over $50</span>
                <span class="marquee-text">🔥 Limited time offer - Don't miss out!</span>
                <span class="marquee-text">💫 New arrivals every week</span>
            </div>
        </div>
        <button class="close-btn" onclick="closeBanner()">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo" onclick="window.location.href='index.php'">Deeken</div>
        <div class="search-container">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search for products...">
            </div>
        </div>
        <div class="nav-icons">
            <button class="cart-btn" title="Shopping Cart" onclick="window.location.href='cart.php'">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge"><?php echo $cartCount; ?></span>
            </button>
            <div class="profile-dropdown">
                <?php if ($user): ?>
                    <?php
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

    <!-- Inbox Section -->
    <section class="inbox-section">
        <h2 class="inbox-title">Inbox</h2>
        <div class="inbox-container">
            <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    No notifications at this time.
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification">
                        <div class="notification-header">
                            <span class="notification-type"><?php echo ucfirst($notification['type']); ?></span>
                            <span class="notification-date">
                                <?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?>
                            </span>
                        </div>
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        <?php if ($notification['order_id']): ?>
                            <a href="order_details.php?id=<?php echo $notification['order_id']; ?>" 
                               class="notification-order">
                                <i class="fas fa-box"></i> View Order #<?php echo $notification['order_id']; ?> (Status: <?php echo ucfirst($notification['order_status']); ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
      <div class="footer-bottom">
            <p>Deeken © 2025, All Rights Reserved</p>
            <div class="payment-icons">
                <div class="payment-icon">💳</div>
                <div class="payment-icon">🏦</div>
                <div class="payment-icon">📱</div>
            </div>
        </div>
   

    <script>
        // Toggle profile dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown on outside click
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.querySelector('.profile-trigger');
            if (!trigger.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Close banner
        function closeBanner() {
            document.querySelector('.top-banner').style.display = 'none';
        }

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>