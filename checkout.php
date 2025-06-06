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
if (isset($_POST['place_order']) && $user) {
    $address = sanitize($conn, $_POST['address']);
    $phone = sanitize($conn, $_POST['phone']);
    $delivery_fee = 5.00;
    $total = 0;

    // Calculate total
    $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $cart_items = $stmt->get_result();
    while ($item = $cart_items->fetch_assoc()) {
        $total += $item['price'] * $item['quantity'];
    }
    $total += $delivery_fee;
    $stmt->close();

    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total, delivery_fee, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("idd", $user['id'], $total, $delivery_fee);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // Insert order items
    $cart_stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $cart_stmt->bind_param("i", $user['id']);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    $order_item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    while ($item = $cart_result->fetch_assoc()) {
        $order_item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
        $order_item_stmt->execute();
    }
    $cart_stmt->close();
    $order_item_stmt->close();

    // Update user address and phone
    $stmt = $conn->prepare("UPDATE users SET address = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $address, $phone, $user['id']);
    $stmt->execute();
    $stmt->close();

    // Clear cart
    $conn->query("DELETE FROM cart WHERE user_id = {$user['id']}");

    header("Location: profile.php");
    exit;
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
    <link rel="stylesheet" href="NEW.css">
    <link rel="stylesheet" href="hamburger.css">
</head>
<body>
    <!-- ----- NAVIGATION ----- -->
    <header>
        <nav class="navbar">
            <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
            <ul class="nav-links">
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart (<span id="cartCount"><?php echo $cart_count; ?></span>)</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="admin.php"><i class="fas fa-cog"></i> Admin</a></li>
                <li><a href="login.php" id="authLink"><i class="fas fa-sign-in-alt"></i> <?php echo $user ? 'Logout' : 'Login'; ?></a></li>
            </ul>
            <button class="hamburger" id="hamburger" aria-label="Open navigation menu" aria-expanded="false">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        </nav>
        <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
        <div class="mobile-nav" id="mobileNav" aria-hidden="true" role="navigation" aria-label="Mobile navigation">
            <div class="mobile-nav-header">
                <h2 class="mobile-nav-title">Menu</h2>
                <button class="mobile-nav-close" id="mobileNavClose" aria-label="Close navigation menu">✕</button>
            </div>
            <ul class="mobile-nav-links">
                <li><a href="index.php"><span class="nav-icon">🏠</span>Home</a></li>
                <li><a href="cart.php"><span class="nav-icon">🛒</span>Cart</a></li>
                <li><a href="profile.php"><span class="nav-icon">👤</span>Profile</a></li>
                <li><a href="admin.php"><span class="nav-icon">⚙️</span>Admin</a></li>
                <li><a href="login.php"><span class="nav-icon">🔐</span><?php echo $user ? 'Logout' : 'Login'; ?></a></li>
            </ul>
        </div>
    </header>

    <!-- ----- CHECKOUT CONTENT ----- -->
    <main>
        <section class="checkout">
            <h2><i class="fas fa-check-circle"></i> Checkout</h2>
            <form id="checkoutForm" method="POST">
                <h3>Delivery Address</h3>
                <input type="text" name="address" placeholder="Enter your address" value="<?php echo htmlspecialchars($user['address']); ?>" required>
                <input type="text" name="phone" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                <h3>Order Summary</h3>
                <div id="orderSummary">
                    <?php
                    $total = 0;
                    $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.name, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                    $cart_items = $stmt->get_result();
                    while ($item = $cart_items->fetch_assoc()):
                        $item_total = $item['price'] * $item['quantity'];
                        $total += $item_total;
                    ?>
                        <div class="cart-item">
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p>$<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?> = $<?php echo number_format($item_total, 2); ?></p>
                        </div>
                    <?php endwhile; $stmt->close(); ?>
                </div>
                <p>Delivery Fee: $<span id="deliveryFee">5.00</span></p>
                <p>Total: $<span id="orderTotal"><?php echo number_format($total + 5.00, 2); ?></span></p>
                <button type="submit" name="place_order"><i class="fas fa-credit-card"></i> Place Order</button>
            </form>
        </section>
    </main>

    <!-- ----- FOOTER ----- -->
    <footer>
        <p><i class="fas fa-copyright"></i> 2025 Deeken. All rights reserved.</p>
    </footer>

    <!-- ----- JAVASCRIPT ----- -->
    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>