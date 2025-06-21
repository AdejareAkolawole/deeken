<?php
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user and cart count
$user = getCurrentUser();
$cart_count = getCartCount($conn, $user);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            position: relative;
            flex: 1;
            max-width: 500px;
            margin: 0.5rem 1rem;
        }

        .search-bar input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 1px solid var(--medium-gray);
            border-radius: 5px;
            font-size: clamp(12px, 2vw, 14px);
            outline: none;
            background: var(--primary-white);
        }

        .search-bar .fa-search {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            cursor: pointer;
        }

        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--primary-white);
            border: 1px solid var(--medium-gray);
            border-radius: 5px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: var(--shadow);
            margin-top: 5px;
        }

        .autocomplete-suggestion {
            padding: 12px 15px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            transition: background-color var(--transition);
        }

        .autocomplete-suggestion:hover {
            background: var(--light-gray);
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
            color: var(--primary-blue);
        }

        /* Nav Right */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
            position: relative;
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

        .profile-notification-dot {
            position: absolute;
            top: 0;
            right: 0;
            background: #ff6f61;
            color: var(--primary-white);
            border-radius: 50%;
            width: 0.75rem;
            height: 0.75rem;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
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
            min-width: 200px;
            z-index: 1001;
            display: none;
            transition: var(--transition);
        }

        .profile-dropdown-menu.active {
            display: block;
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

        .cart-count,
        .notification-dot {
            display: inline-block;
            background: var(--accent-orange);
            color: var(--primary-white);
            border-radius: 50%;
            width: 1.25rem;
            height: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            margin-left: 5px;
        }

        .notification-dot {
            background: #ff6f61;
        }

        /* Notification Dropdown */
        .notification-dropdown-menu {
            background: var(--primary-white);
            border: 1px solid var(--medium-gray);
            border-radius: 5px;
            min-width: 300px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1002;
            display: none;
            box-shadow: var(--shadow);
            position: relative;
            margin-top: 0.5rem;
        }

        .notification-dropdown-menu.active {
            display: block;
        }

        .notification-item {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .notification-item.unread {
            background: #f9f9f9;
        }

        .notification-item:hover {
            background: var(--light-gray);
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
            color: var(--text-gray);
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

        body[data-toggle="true"] .hamburger span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }

        body[data-toggle="true"] .hamburger span:nth-child(2) {
            opacity: 0;
        }

        body[data-toggle="true"] .hamburger span:nth-child(3) {
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

        body[data-toggle="true"] .mobile-nav-overlay {
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

        body[data-toggle="true"] .mobile-nav {
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
            }

            .profile-info {
                display: flex;
                flex-direction: column;
            }

            .hamburger {
                display: none;
            }

            .mobile-nav,
            .mobile-nav-overlay {
                display: none;
            }
        }

        /* Mobile-specific styles */
        @media (max-width: 767px) {
            .search-bar {
                width: 40px;
                transition: width var(--transition);
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

            .profile-info {
                display: none !important;
            }
        }
    </style>
</head>
<body data-toggle="false">
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
            <div class="search-bar" id="searchBar">
                <input type="text" id="searchInput" placeholder="Search products...">
                <i class="fas fa-search" id="searchIcon"></i>
                <div class="autocomplete-suggestions" id="autocompleteSuggestions"></div>
            </div>
            <div class="nav-right">
                <div class="profile-dropdown">
                    <?php if ($user): ?>
                        <div class="profile-trigger" id="profileTrigger">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <?php if ($unread_count > 0): ?>
                                <span class="profile-notification-dot"></span>
                            <?php endif; ?>
                            <div class="profile-info">
                                <span class="profile-greeting">Hi, <?php echo htmlspecialchars($user['full_name'] ?? $user['email'] ?? 'User'); ?></span>
                                <span class="profile-account">My Account <i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="profile-dropdown-menu" id="profileDropdown">
                            <a href="cart.php">
                                <i class="fas fa-shopping-cart"></i> Cart
                                <span class="cart-count"><?php echo htmlspecialchars($cart_count); ?></span>
                            </a>
                            <a href="#" onclick="toggleNotificationDropdown(event)">
                                <i class="fas fa-bell"></i> Notifications
                                <?php if ($unread_count > 0): ?>
                                    <span class="notification-dot"><?php echo htmlspecialchars($unread_count); ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="notification-dropdown-menu" id="notificationDropdown">
                                <?php if (empty($notifications)): ?>
                                    <div class="notification-item">No notifications</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                             data-notification-id="<?php echo htmlspecialchars($notification['id']); ?>" 
                                             onclick="markNotificationRead(<?php echo htmlspecialchars($notification['id']); ?>, '<?php echo htmlspecialchars($notification['type']); ?>', <?php echo htmlspecialchars($notification['order_id'] ?: 'null'); ?>)">
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
                            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
                            <a href="inbox.php">
                                <i class="fas fa-inbox"></i> Inbox
                                <?php if ($unread_count > 0): ?>
                                    <span class="notification-dot"><?php echo htmlspecialchars($unread_count); ?></span>
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
                        <div class="profile-trigger" id="profileTrigger">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-info">
                                <span class="profile-greeting">Hi, Guest</span>
                                <span class="profile-account">Sign In <i class="fas fa-chevron-down"></i></span>
                            </div>
                        </div>
                        <div class="profile-dropdown-menu" id="profileDropdown">
                            <a href="cart.php">
                                <i class="fas fa-shopping-cart"></i> Cart
                                <span class="cart-count"><?php echo htmlspecialchars($cart_count); ?></span>
                            </a>
                            <a href="login.php"><i class="fas fa-sign-in"></i> Sign In</a>
                            <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
                            <hr class="dropdown-divider">
                            <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
                        </div>
                    <?php endif; ?>
                </div>
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
                <li><a href="shop.php"><span class="nav-icon">🏬</span>Shop</a></li>
                <li><a href="cart.php"><span class="nav-icon">🛒</span>Cart (<span class="cart-count"><?php echo htmlspecialchars($cart_count); ?></span>)</a></li>
                <li><a href="orders.php"><span class="nav-icon">📦</span>Orders</a></li>
                <?php if ($user): ?>
                    <li><a href="profile.php"><span class="nav-icon">👤</span>Profile</a></li>
                    <li><a href="inbox.php"><span class="nav-icon">📥</span>Inbox<?php if ($unread_count > 0): ?> <span class="notification-dot"><?php echo htmlspecialchars($unread_count); ?></span><?php endif; ?></a></li>
                    <li><a href="logout.php"><span class="nav-icon">🔐</span>Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php"><span class="nav-icon">🔑</span>Sign In</a></li>
                    <li><a href="register.php"><span class="nav-icon">➕</span>Create Account</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <script>
        // Toggle profile dropdown
        function toggleProfileDropdown(event) {
            event.preventDefault();
            event.stopPropagation();
            const dropdown = document.getElementById('profileDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
            if (notificationDropdown.classList.contains('active')) {
                notificationDropdown.classList.remove('active');
            }
        }

        // Toggle notification dropdown (nested in profile dropdown)
        function toggleNotificationDropdown(event) {
            event.preventDefault();
            event.stopPropagation();
            const notificationDropdown = document.getElementById('notificationDropdown');
            notificationDropdown.classList.toggle('active');
        }

        // Search products
        function searchProducts() {
            const query = document.getElementById('searchInput').value.trim();
            if (query) {
                window.location.href = `search.php?q=${encodeURIComponent(query)}`;
            }
        }

        // Toggle mobile nav
        function toggleMobileNav() {
            const body = document.body;
            const isOpen = body.getAttribute('data-toggle') === 'true';
            body.setAttribute('data-toggle', !isOpen);
            document.getElementById('mobileNav').setAttribute('aria-hidden', isOpen);
        }

        // Close modal
        function closeModal() {
            const modal = document.getId('profileIncompleteModal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        // Mark notification as read
        async function markNotificationRead(notificationId, type, orderId) {
            try {
                const formData = new FormData();
                formData.append('action', 'mark_notification_read');
                formData.append('notification_id', notificationId);

                const response = await fetch('notifications.php', {
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
                    const badges = document.querySelectorAll('.notification-dot');
                    badges.forEach(badge => {
                        let count = parseInt(badge.textContent) - 1;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.textContent = count;
                        }
                    });
                    const profileDot = document.querySelector('.profile-notification-dot');
                    if (profileDot && parseInt(badges[0]?.textContent || 0) <= 0) {
                        profileDot.remove();
                    }
                    if (orderId) {
                        window.location.href = `orders.php?id=${orderId}`;
                    }
                }
            } catch (error) {
                console.error('Mark Notification Error:', error);
            }
        }

        // Search and autocomplete functionality
        function highlightMatch(text, query) {
            const regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<span class="autocomplete-highlight">$1</span>');
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', () => {
            // Profile dropdown
            const profileTrigger = document.getElementById('profileTrigger');
            if (profileTrigger) {
                profileTrigger.addEventListener('click', toggleProfileDropdown);
            } else {
                console.error('Profile trigger not found');
            }

            // Search functionality
            const searchBar = document.getElementById('searchBar');
            const searchInput = document.getElementById('searchInput');
            const searchIcon = document.getElementById('searchIcon');
            const autocompleteSuggestions = document.getElementById('autocompleteSuggestions');

            if (searchIcon) {
                searchIcon.addEventListener('click', (event) => {
                    if (window.innerWidth <= 767) {
                        searchBar.classList.toggle('active');
                        if (searchBar.classList.contains('active')) {
                            searchInput.focus();
                            event.preventDefault();
                        }
                    } else {
                        searchProducts();
                    }
                });
            } else {
                console.error('Search icon not found');
            }

            if (searchInput) {
                searchInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        searchProducts();
                    }
                });

                let debounceTimeout = null;
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimeout);
                    const query = this.value.trim();

                    if (query.length >= 2) {
                        debounceTimeout = setTimeout(() => {
                            fetch(`autocomplete.php?q=${encodeURIComponent(query)}`)
                                .then(response => {
                                    if (!response.ok) throw new Error('Network response was not ok');
                                    return response.json();
                                })
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
                                                window.location.href = `search.php?q=${encodeURIComponent(item.name)}`;
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
            } else {
                console.error('Search input not found');
            }

            // Close autocomplete and search bar on outside click
            document.addEventListener('click', (e) => {
                if (!searchBar.contains(e.target) && !autocompleteSuggestions.contains(e.target)) {
                    autocompleteSuggestions.style.display = 'none';
                    if (window.innerWidth <= 767) {
                        searchBar.classList.remove('active');
                    }
                }
            });

            // Close profile and notification dropdowns on outside click
            document.addEventListener('click', (event) => {
                const profileDropdown = document.getElementById('profileDropdown');
                const notificationDropdown = document.getElementById('notificationDropdown');
                if (!event.target.closest('.profile-dropdown') && profileDropdown.classList.contains('active')) {
                    profileDropdown.classList.remove('active');
                    if (notificationDropdown.classList.contains('active')) {
                        notificationDropdown.classList.remove('active');
                    }
                }
                if (!event.target.closest('.notification-dropdown-menu') && !event.target.closest('a[onclick*="toggleNotificationDropdown"]') && notificationDropdown.classList.contains('active')) {
                    notificationDropdown.classList.remove('active');
                }
            });

            // Mobile nav toggle
            const mobileNavToggle = document.getElementById('mobileNavToggle');
            const mobileNavClose = document.getElementById('mobileNavClose');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            if (mobileNavToggle) {
                mobileNavToggle.addEventListener('click', toggleMobileNav);
            } else {
                console.error('Mobile nav toggle not found');
            }
            if (mobileNavClose) {
                mobileNavClose.addEventListener('click', toggleMobileNav);
            }
            if (mobileNavOverlay) {
                mobileNavOverlay.addEventListener('click', toggleMobileNav);
            }

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
        });
    </script>
</body>
</html>