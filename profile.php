<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireLogin();

$user = getCurrentUser();

// ----- UPDATE PROFILE -----
if (isset($_POST['update_profile'])) {
    $address = sanitize($conn, $_POST['address']);
    $phone = sanitize($conn, $_POST['phone']);
    $stmt = $conn->prepare("UPDATE users SET address = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $address, $phone, $user['id']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['user']['address'] = $address;
    $_SESSION['user']['phone'] = $phone;
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
    <title>Deeken - Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="responsive.css">
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
                <li><a href="login.php" id="authLink"><i class="fas fa-sign-in-alt"></i> Logout</a></li>
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
                <li><a href="login.php"><span class="nav-icon">🔐</span>Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- ----- PROFILE CONTENT ----- -->
    <main>
        <section class="profile">
            <h2><i class="fas fa-user"></i> Your Profile</h2>
            <div id="profileInfo">
                <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
                <form method="POST">
                    <label>Address:</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>" required>
                    <label>Phone:</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    <button type="submit" name="update_profile"><i class="fas fa-save"></i> Update Profile</button>
                </form>
            </div>
            <h3><i class="fas fa-box"></i> Your Orders</h3>
            <div id="orderHistory">
                <?php
                $stmt = $conn->prepare("SELECT o.*, GROUP_CONCAT(p.name) as products FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE o.user_id = ? GROUP BY o.id");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $orders = $stmt->get_result();
                while ($order = $orders->fetch_assoc()):
                ?>
                    <div class="order-item">
                        <p>Order #<?php echo $order['id']; ?> - $<?php echo number_format($order['total'], 2); ?> (<?php echo $order['status']; ?>)</p>
                        <p>Products: <?php echo htmlspecialchars($order['products']); ?></p>
                        <p>Date: <?php echo $order['created_at']; ?></p>
                    </div>
                <?php endwhile; $stmt->close(); ?>
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
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>