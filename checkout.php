<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireLogin();

$user = getCurrentUser();

// ----- HELPER FUNCTIONS FOR DELIVERY FEES -----
function getDeliveryFees($conn, $total_amount) {
    $stmt = $conn->prepare("SELECT id, name, fee, min_order_amount, description FROM delivery_fees WHERE is_active = 1 ORDER BY fee ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $fees = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_applicable'] = ($row['min_order_amount'] === null || $total_amount >= $row['min_order_amount']);
        $fees[] = $row;
    }
    $stmt->close();
    return $fees;
}

function getDeliveryFeeById($conn, $delivery_fee_id, $total_amount) {
    $stmt = $conn->prepare("SELECT id, name, fee, min_order_amount FROM delivery_fees WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $delivery_fee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['min_order_amount'] === null || $total_amount >= $row['min_order_amount']) {
            $stmt->close();
            return $row;
        }
    }
    $stmt->close();
    return null;
}

// ----- ORDER SUBMISSION -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && $user) {
    $name = sanitize($conn, $_POST['name']);
    $street_address = sanitize($conn, $_POST['address']);
    $phone = sanitize($conn, $_POST['phone']);
    $delivery_fee_id = isset($_POST['delivery_fee_id']) ? (int)$_POST['delivery_fee_id'] : 0;
    $subtotal = 0;

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Calculate subtotal and validate stock
        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.price, p.name, i.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN inventory i ON p.id = i.product_id WHERE c.user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $cart_items = $stmt->get_result();
        $items = [];
        while ($item = $cart_items->fetch_assoc()) {
            if ($item['stock_quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock for {$item['name']}. Available: {$item['stock_quantity']}, Requested: {$item['quantity']}");
            }
            $subtotal += $item['price'] * $item['quantity'];
            $items[] = $item;
        }
        if (empty($items)) {
            throw new Exception("Cart is empty.");
        }
        $stmt->close();

        // Validate delivery fee
        $delivery_fee_data = $delivery_fee_id ? getDeliveryFeeById($conn, $delivery_fee_id, $subtotal) : null;
        if (!$delivery_fee_data) {
            // Fallback to cheapest applicable fee
            $delivery_fees = getDeliveryFees($conn, $subtotal);
            foreach ($delivery_fees as $fee) {
                if ($fee['is_applicable']) {
                    $delivery_fee_data = $fee;
                    break;
                }
            }
            if (!$delivery_fee_data) {
                throw new Exception("No valid delivery options available for your order subtotal of $" . number_format($subtotal, 2));
            }
        }
        $delivery_fee = $delivery_fee_data['fee'];
        $delivery_fee_id = $delivery_fee_data['id'];
        $total = $subtotal + $delivery_fee;

        // Insert or update address
        $city = '';
        $state = '';
        $country = '';
        $postal_code = '';
        $is_default = 1;

        // Fixed bind_param type string to match 12 variables
        $stmt = $conn->prepare("INSERT INTO addresses (user_id, full_name, street_address, city, state, country, postal_code, phone, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE full_name = ?, street_address = ?, phone = ?, updated_at = NOW()");
        $stmt->bind_param("issssssissss", $user['id'], $name, $street_address, $city, $state, $country, $postal_code, $phone, $is_default, $name, $street_address, $phone);
        $stmt->execute();
        $address_id = $conn->insert_id;
        $stmt->close();

        // Fetch address_id if updated
        if (!$address_id) {
            $stmt = $conn->prepare("SELECT id FROM addresses WHERE user_id = ? LIMIT 1");
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
        $stmt = $conn->prepare("INSERT INTO orders (user_id, address_id, delivery_fee_id, total, delivery_fee, status, created_at) VALUES (?, ?, ?, ?, ?, 'processing', NOW())");
        $stmt->bind_param("iiidd", $user['id'], $address_id, $delivery_fee_id, $total, $delivery_fee);
        $stmt->execute();
        $order_id = $conn->insert_id;
        $stmt->close();

        // Insert order items and update stock
        $order_item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stock_stmt = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        foreach ($items as $item) {
            $order_item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $order_item_stmt->execute();

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
            echo json_encode(['status' => 'success', 'message' => 'Order placed successfully', 'order_id' => $order_id]);
            exit;
        } else {
            header('Location: order_success.php?order_id=' . $order_id . '&success=' . urlencode('Order placed successfully!'));
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

// ----- CALCULATE CURRENT SUBTOTAL AND DELIVERY FEES -----
$current_subtotal = 0;
$delivery_fees = [];
$current_delivery_fee = 0;
$current_delivery_fee_id = 0;
if ($user && $cart_count > 0) {
    $stmt = $conn->prepare("SELECT c.quantity, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $current_subtotal += $row['price'] * $row['quantity'];
    }
    $stmt->close();

    // Fetch all delivery fees
    $delivery_fees = getDeliveryFees($conn, $current_subtotal);
    // Select default fee (cheapest applicable)
    foreach ($delivery_fees as $fee) {
        if ($fee['is_applicable']) {
            $current_delivery_fee = $fee['fee'];
            $current_delivery_fee_id = $fee['id'];
            break;
        }
    }
}
$current_total = $current_subtotal + $current_delivery_fee;
?>

<!DOCTYPE html>
<html lang="en">
<head>
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

        .input-field, select {
            width: 100%;
            padding: 12px;
            margin: 0.5rem 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
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

        .order-totals {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 0.5rem 0;
        }

        .total-row.final {
            border-top: 2px solid #2a2aff;
            padding-top: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: #2a2aff;
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
            width: 100%;
            justify-content: center;
        }

        .empty-cart {
            text-align: center;
            padding: 2rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .stock-info.out-of-stock {
            color: #ef4444;
            font-weight: bold;
        }

        footer {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            color: #666;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
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
            <?php elseif (empty($delivery_fees)): ?>
                <div class="alert-error">No delivery options available. Please contact support.</div>
            <?php else: ?>
                <form id="checkoutForm" method="POST">
                    <h3>Delivery Information</h3>
                    <input type="text" name="name" class="input-field" placeholder="Enter your full name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    <input type="text" name="address" class="input-field" placeholder="Enter your street address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required>
                    <input type="text" name="phone" class="input-field" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>

                    <h3>Delivery Options</h3>
                    <select name="delivery_fee_id" id="deliverySelect" class="input-field" required onchange="updateTotals()">
                        <?php foreach ($delivery_fees as $fee): ?>
                            <option value="<?php echo htmlspecialchars($fee['id']); ?>"
                                    <?php echo $fee['id'] == $current_delivery_fee_id ? 'selected' : ''; ?>
                                    <?php echo !$fee['is_applicable'] ? 'disabled' : ''; ?>
                                    data-fee="<?php echo htmlspecialchars($fee['fee']); ?>">
                                <?php echo htmlspecialchars($fee['name']); ?> - $<?php echo number_format($fee['fee'], 2); ?>
                                <?php if ($fee['min_order_amount']): ?>
                                    (Min. order: $<?php echo number_format($fee['min_order_amount'], 2); ?>)
                                <?php endif; ?>
                                <?php if (!$fee['is_applicable']): ?>
                                    (Subtotal too low)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <h3>Order Summary</h3>
                    <div id="orderSummary">
                        <?php
                        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.name, p.price, p.image, i.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN inventory i ON p.id = i.product_id WHERE c.user_id = ?");
                        $stmt->bind_param("i", $user['id']);
                        $stmt->execute();
                        $cart_items = $stmt->get_result();
                        while ($item = $cart_items->fetch_assoc()):
                            $item_total = $item['price'] * $item['quantity'];
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

                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">$<?php echo number_format($current_subtotal, 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Delivery Fee:</span>
                            <span id="deliveryFee">$<?php echo number_format($current_delivery_fee, 2); ?></span>
                        </div>
                        <div class="total-row final">
                            <span>Total:</span>
                            <span id="total">$<?php echo number_format($current_total, 2); ?></span>
                        </div>
                    </div>

                    <button type="submit" name="place_order" class="btn">
                        <i class="fas fa-credit-card"></i> Place Order - $<span id="buttonTotal"><?php echo number_format($current_total, 2); ?></span>
                    </button>
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
        // Toggle profile dropdown
        function toggleProfileDropdown(event) {
            event?.preventDefault();
            event?.stopPropagation();
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        // Initialize dropdown
        document.addEventListener('DOMContentLoaded', () => {
            const profileTrigger = document.querySelector('.profile-trigger');
            if (profileTrigger) profileTrigger.addEventListener('click', toggleProfileDropdown);

            // Close dropdown on outside click
            document.addEventListener('click', (event) => {
                const dropdown = document.getElementById('profileDropdown');
                if (!event.target.closest('.profile-dropdown') && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            });
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

        // Search products
        function searchProducts() {
            const query = document.getElementById('searchInput').value.trim();
            if (query) {
                window.location.href = `index.php?search=${encodeURIComponent(query)}`;
            }
        }

        // Update totals on delivery selection
        function updateTotals() {
            const select = document.getElementById('deliverySelect');
            const fee = parseFloat(select.options[select.selectedIndex].dataset.fee) || 0;
            const subtotal = <?php echo $current_subtotal; ?>;
            const total = subtotal + fee;

            document.getElementById('deliveryFee').textContent = `$${fee.toFixed(2)}`;
            document.getElementById('total').textContent = `$${total.toFixed(2)}`;
            document.getElementById('buttonTotal').textContent = total.toFixed(2);
        }

        // Form submission
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('checkoutForm');
            if (form) {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const formData = new FormData(form);
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            window.location.href = `order_success.php?order_id=${data.order_id}&success=${encodeURIComponent(data.message)}`;
                        } else {
                            alert(`Error: ${data.message}`);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('An error occurred while placing your order. Please try again.');
                    });
                });
            }
        });
    </script>
</body>
</html>