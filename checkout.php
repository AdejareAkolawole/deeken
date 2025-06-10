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
    $name = sanitize($conn, $_POST['name']);
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

    // Update user name, address, and phone
    $stmt = $conn->prepare("UPDATE users SET name = ?, address = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $address, $phone, $user['id']);
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
    <link rel="stylesheet" href="checkout.css">
    <link rel="stylesheet" href="hamburger.css">
    <style>
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
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.logo {
    font-size: 1.8rem;
    font-weight: 600;
    color: #2A2AFF;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.logo:hover {
    transform: scale(1.05);
    color: #1A1AFF;
}

.logo i {
    font-size: 1.6rem;
    background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
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
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.9);
}

.search-bar input:focus {
    border-color: #2A2AFF;
    box-shadow: 0 0 0 3px rgba(42, 42, 255, 0.1);
    transform: translateY(-1px);
}

.search-bar button {
    background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
    border: none;
    padding: 12px 20px;
    border-radius: 50px;
    color: white;
    cursor: pointer;
    margin-left: -50px;
    z-index: 1;
    transition: all 0.3s ease;
    font-size: 14px;
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

/* Cart Link */
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
    color: #2A2AFF;
}

.cart-count {
    background: #FF6B35;
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

/* Admin Link */
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
    color: #2A2AFF;
}

/* Profile Dropdown */
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
    border-color: #2A2AFF;
}

.profile-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
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

/* Profile Dropdown Menu */
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
    color: #2A2AFF;
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
</style>
    </style>
</head>
<body>
    <!-- ----- NAVIGATION ----- -->
    <header>
        <nav class="navbar">
            <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
            
            
            <div class="nav-right">
                <!-- Cart Link -->
                <a href="cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-text">Cart</span>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>    
                <!-- Profile Dropdown -->
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
                            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
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

    <!-- ----- CHECKOUT CONTENT ----- -->
    <main>
        <section class="checkout">
            <h2><i class="fas fa-check-circle"></i> Checkout</h2>
            <?php if ($cart_count == 0): ?>
                <div class="empty-cart">
                    <p>Your cart is empty.</p>
                    <a href="index.php" class="btn"><i class="fas fa-shopping-cart"></i> Start Shopping</a>
                </div>
            <?php else: ?>
                <form id="checkoutForm" method="POST">
                    <h3>Delivery Information</h3>
                    <input type="text" name="name" class="input-field" placeholder="Enter your full name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                    <input type="text" name="address" class="input-field" placeholder="Enter your address" value="<?php echo htmlspecialchars($user['address']); ?>" required>
                    <input type="text" name="phone" class="input-field" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    <h3>Order Summary</h3>
                    <div id="orderSummary">
                        <?php
                        $total = 0;
                        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.name, p.price, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
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

    <!-- ----- FOOTER ----- -->
    <footer>
        <p><i class="fas fa-copyright"></i> 2025 Deeken. All rights reserved.</p>
    </footer>

    <!-- ----- JAVASCRIPT ----- -->
    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
    <script>
        // Search products
        function searchProducts() {
            const search = document.getElementById('searchInput').value;
            window.location.href = `index.php?search=${encodeURIComponent(search)}`;
        }

        // Toggle profile dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileDropdown = document.querySelector('.profile-dropdown');
            const dropdown = document.getElementById('profileDropdown');
            if (!profileDropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>