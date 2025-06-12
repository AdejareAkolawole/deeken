<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireLogin();

$user = getCurrentUser();

// ----- ORDER SUBMISSION -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && $user) {
    $name = sanitize($conn, $_POST['name']);
    $street_address = sanitize($conn, $_POST['address']);
    $phone = sanitize($conn, $_POST['phone']);
    $delivery_fee = 5.00;
    $total = 0;

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Calculate total and validate stock
        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.price, i.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN inventory i ON p.id = i.product_id WHERE c.user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $cart_items = $stmt->get_result();
        $items = [];
        while ($item = $cart_items->fetch_assoc()) {
            if ($item['stock_quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock for product ID {$item['product_id']}. Available: {$item['stock_quantity']}, Requested: {$item['quantity']}");
            }
            $total += $item['price'] * $item['quantity'];
            $items[] = $item;
        }
        $total += $delivery_fee;
        $stmt->close();

        // Insert or update address
        $city = ''; // Placeholder
        $state = ''; // Placeholder
        $country = ''; // Placeholder
        $postal_code = ''; // Placeholder
        $is_default = 1;

        $stmt = $conn->prepare("INSERT INTO addresses (user_id, full_name, street_address, city, state, country, postal_code, phone, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE full_name = ?, street_address = ?, phone = ?, updated_at = NOW()");
        $stmt->bind_param("isssssssisss", $user['id'], $name, $street_address, $city, $state, $country, $postal_code, $phone, $is_default, $name, $street_address, $phone);
        $stmt->execute();
        $address_id = $conn->insert_id;
        $stmt->close();

        // Fetch address_id if updated
        if (!$address_id) {
            $stmt = $conn->prepare("SELECT id FROM addresses WHERE user_id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $address_id = $row['id'];
            }
            $stmt->close();
        }

        if (!$address_id) {
            throw new Exception("Failed to save or retrieve address");
        }

        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, address_id, total, delivery_fee, status, created_at) VALUES (?, ?, ?, ?, 'processing', NOW())");
        $stmt->bind_param("iidd", $user['id'], $address_id, $total, $delivery_fee);
        $stmt->execute();
        $order_id = $conn->insert_id;
        $stmt->close();

        // Insert order items and update stock
        $order_item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stock_stmt = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        foreach ($items as $item) {
            // Insert order item
            $order_item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $order_item_stmt->execute();

            // Update stock
            $stock_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $stock_stmt->execute();
        }
        $order_item_stmt->close();
        $stock_stmt->close();

        // Update user info
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, address = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $street_address, $phone, $user['id']);
        $stmt->execute();
        $stmt->close();

        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        // Response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Order placed successfully']);
            exit;
        } else {
            header('Location: order_success.php?success=' . urlencode('Order placed successfully!'));
            exit;
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to place order: " . $e->getMessage();
        error_log($e->getMessage());
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $error]);
            exit;
        } else {
            header('Location: checkout.php?error=' . urlencode($error));
            exit;
        }
    }
}

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and CSS -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Checkout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="stylesheet.css">
    <link rel="stylesheet" href="hamburger.css">
    <style>
        /* ===== NAVIGATION ===== */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 100%;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
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
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
            color: #1a1aff;
        }

        .logo i {
            font-size: 1.6rem;
            color: #2a2aff;
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
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            border-color: #2a2aff;
            box-shadow: 0 0 8px rgba(42, 42, 255, 0.1);
            transform: translateY(-1px);
        }

        .search-bar button {
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            border: none;
            padding: 12px;
            border-radius: 50px;
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-bar button:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(42, 42, 255, 0.3);
        }

        /* ===== NAVIGATION RIGHT SECTION ===== */
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
            color: #2a2aff;
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
            font-weight: 600;
            margin-left: -5px;
        }

        .admin-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .admin-link:hover {
            background: rgba(42, 42, 255, 0.1);
            color: #2a2aff;
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
            border-color: #2a2aff;
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
            color: #2a2aff;
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

        .checkout {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .checkout h2 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333;
        }

        .input-field {
            width: 100%;
            padding: 12px;
            margin: 0.5rem 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .input-field:focus {
            outline: none;
            border-color: #2a2aff;
            box-shadow: 0 0 8px rgba(42, 42, 255, 0.1);
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        .cart-item-details h3 {
            font-size: 1.2rem;
            margin: 0;
            color: #333;
        }

        .cart-item-details p {
            margin: 0.5rem 0;
            color: #666;
        }

        .btn,
        button[type="submit"] {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 12px 24px;
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover,
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(42, 42, 255, 0.3);
        }

        .empty-cart {
            text-align: center;
            padding: 2rem;
        }

        footer {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            color: #666;
            margin-top: 2rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 14px;
        }

        .stock-info {
            font-size: 0.9rem;
            color: #666;
        }

        .out-of-stock {
            color: #ef4444;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
            <div class="nav-right">
                <a href="cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-text">Cart</span>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>    
                <div class="profile-dropdown">
                    <?php if ($user): ?>
                        <div class="profile-trigger" onclick="toggleProfileDropdown()">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-info">
                                <span class="profile-greeting">Hi, <?php echo htmlspecialchars($user['full_name'] ?? $user['email'] ?? 'User'); ?></span>
                                <span class="profile-account">My Account <i class="fas fa-chevron-down"></i></span>
                            </div>
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
                    <?php endif; ?>
                    <div class="profile-dropdown-menu" id="profileDropdown">
                        <?php if ($user): ?>
                            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
                            <a href="index.php"><i class="fas fa-heart"></i> Home</a>
                            <hr class="dropdown-divider">
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        <?php else: ?>
                            <a href="login.php"><i class="fas fa-sign-in"></i> Sign In</a>
                            <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
                            <hr class="dropdown-divider">
                            <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="checkout">
            <h2><i class="fas fa-check-circle"></i> Checkout</h2>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            <?php if ($cart_count == 0): ?>
                <div class="empty-cart">
                    <p>Your cart is empty.</p>
                    <a href="index.php" class="btn"><i class="fas fa-shopping-cart"></i> Start Shopping</a>
                </div>
            <?php else: ?>
                <form id="checkoutForm" method="POST" action="">
                    <h3>Delivery Information</h3>
                    <input type="text" name="name" class="input-field" placeholder="Enter your full name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    <input type="text" name="address" class="input-field" placeholder="Enter your street address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required>
                    <input type="text" name="phone" class="input-field" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    <h3>Order Summary</h3>
                    <div id="orderSummary">
                        <?php
                        $total = 0;
                        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.name, p.price, p.image, i.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN inventory i ON p.id = i.product_id WHERE c.user_id = ?");
                        $stmt->bind_param("i", $user['id']);
                        $stmt->execute();
                        $cart_items = $stmt->get_result();
                        while ($item = $cart_items->fetch_assoc()):
                            $item_total = $item['price'] * $item['quantity'];
                            $total += $item_total;
                        ?>
                            <div class="cart-item">
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
                                <div class="cart-item-details">
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p>$<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?> = $<?php echo number_format($item_total, 2); ?></p>
                                    <p class="stock-info <?php echo $item['stock_quantity'] < $item['quantity'] ? 'out-of-stock' : ''; ?>">
                                        Stock: <?php echo $item['stock_quantity']; ?>
                                        <?php if ($item['stock_quantity'] < $item['quantity']): ?>
                                            (Insufficient stock)
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; $stmt->close(); ?>
                    </div>
                    <p>Delivery Fee: $<span id="deliveryFee">5.00</span></p>
                    <p>Total: $<span id="orderTotal"><?php echo number_format($total + 5.00, 2); ?></span></p>
                    <button type="submit" name="place_order"><i class="fas fa-credit-card"></i> Place Order</button>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p><i class="fas fa-copyright"></i> 2025 Deeken. All rights reserved.</p>
    </footer>

    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle profile dropdown
            function toggleProfileDropdown() {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) {
                    dropdown.classList.toggle('show');
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

            // Navbar scroll behavior
            let lastScrollTop = 0;
            const navbar = document.querySelector('.navbar');
            if (navbar) {
                window.addEventListener('scroll', function() {
                    let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                    if (currentScroll > lastScrollTop) {
                        navbar.classList.add('hidden');
                    } else {
                        navbar.classList.remove('hidden');
                    }
                    if (currentScroll <= 0) {
                        navbar.classList.remove('hidden');
                    }
                    lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
                });
            }

            // Handle checkout form submission
            const form = document.getElementById('checkoutForm');
            if (form) {
                console.log('Checkout form found, attaching event listener');
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    console.log('Form submission intercepted');

                    const formData = new FormData(form);
                    console.log('Sending fetch request');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        console.log('Fetch response received', response);
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Parsed JSON data', data);
                        if (data.status === 'success') {
                            console.log('Success status received, redirecting to order_success.php');
                            window.location.href = 'order_success.php?success=' + encodeURIComponent('Order placed successfully!');
                        } else {
                            console.error('Error status received', data.message);
                            alert(data.message || 'An error occurred while placing your order. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('An error occurred while placing your order. Please try again.');
                    });
                });
            } else {
                console.error('Checkout form not found');
            }
        });
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>