<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user
$user = getCurrentUser();

// ----- CART HANDLING -----
if (isset($_POST['add_to_cart']) && $user) {
    $product_id = (int)$_POST['product_id'];
    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE quantity = quantity + 1");
    $stmt->bind_param("ii", $user['id'], $product_id);
    $stmt->execute();
    $stmt->close();
    header("Location: cart.php");
    exit;
}

// ----- CART QUANTITY UPDATE -----
if (isset($_POST['update_quantity']) && $user) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    if ($quantity < 1) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user['id'], $product_id);
    } else {
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $quantity, $user['id'], $product_id);
    }
    $stmt->execute();
    $stmt->close();
}

// ----- CART ITEM REMOVAL -----
if (isset($_POST['remove_from_cart']) && $user) {
    $product_id = (int)$_POST['product_id'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user['id'], $product_id);
    $stmt->execute();
    $stmt->close();
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
    <title>Deeken - Cart</title>
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

    <!-- ----- CART CONTENT ----- -->
    <main>
        <section class="cart">
            <h2><i class="fas fa-shopping-cart"></i> Your Cart</h2>
            <div id="cartItems">
                <?php
                $total = 0;
                if ($user):
                    $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.name, p.price, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                    $cart_items = $stmt->get_result();
                    while ($item = $cart_items->fetch_assoc()):
                        $item_total = $item['price'] * $item['quantity'];
                        $total += $item_total;
                ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <div class="cart-item-details">
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p>$<?php echo number_format($item['price'], 2); ?></p>
                            <form method="POST" onsubmit="updateQuantity(event, <?php echo $item['product_id']; ?>, this.quantity.value)">
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <input type="hidden" name="update_quantity">
                                <button type="submit"><i class="fas fa-sync"></i> Update</button>
                            </form>
                            <p>$<?php echo number_format($item_total, 2); ?></p>
                            <form method="POST" onsubmit="removeFromCart(event, <?php echo $item['product_id']; ?>)">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <button type="submit" name="remove_from_cart"><i class="fas fa-trash"></i> Remove</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; $stmt->close(); else: ?>
                    <p>Please <a href="login.php">login</a> to view your cart.</p>
                <?php endif; ?>
            </div>
            <div class="cart-footer">
                <p>Total: $<span id="cartTotal"><?php echo number_format($total, 2); ?></span></p>
                <a href="checkout.php" class="cta-button"><i class="fas fa-check"></i> Proceed to Checkout</a>
            </div>
        </section>
    </main>

    <!-- ----- FOOTER ----- -->
    <footer>
        <p><i class="fas fa-copyright"></i> 2025 Deeken. All rights reserved.</p>
    </footer>

    <!-- ----- JAVASCRIPT ----- -->
    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
    <script>
        // Update cart quantity via AJAX
        function updateQuantity(event, productId, quantity) {
            event.preventDefault();
            fetch('cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `update_quantity=1&product_id=${productId}&quantity=${quantity}`
            }).then(() => window.location.reload());
        }

        // Remove from cart via AJAX
        function removeFromCart(event, productId) {
            event.preventDefault();
            fetch('cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `remove_from_cart=1&product_id=${productId}`
            }).then(() => window.location.reload());
        }
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>