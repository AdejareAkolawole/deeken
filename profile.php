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
    $full_name = sanitize($conn, $_POST['full_name']);
    $phone = sanitize($conn, $_POST['phone']);
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $full_name, $phone, $user['id']);
    $stmt->execute();
    $stmt->close();
    $_SESSION['user']['full_name'] = $full_name;
    $_SESSION['user']['phone'] = $phone;
    header("Location: profile.php?message=Profile+updated+successfully");
    exit;
}

// ----- ADD/UPDATE ADDRESS -----
if (isset($_POST['update_address'])) {
    $address_id = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
    $full_name = sanitize($conn, $_POST['address_full_name']);
    $street_address = sanitize($conn, $_POST['street_address']);
    $city = sanitize($conn, $_POST['city']);
    $state = sanitize($conn, $_POST['state']);
    $country = sanitize($conn, $_POST['country']);
    $postal_code = sanitize($conn, $_POST['postal_code']);
    $phone = sanitize($conn, $_POST['address_phone']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if ($is_default) {
        $conn->query("UPDATE addresses SET is_default = 0 WHERE user_id = {$user['id']}");
    }

    if ($address_id > 0) {
        $stmt = $conn->prepare("UPDATE addresses SET full_name = ?, street_address = ?, city = ?, state = ?, country = ?, postal_code = ?, phone = ?, is_default = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssssssiii", $full_name, $street_address, $city, $state, $country, $postal_code, $phone, $is_default, $address_id, $user['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO addresses (user_id, full_name, street_address, city, state, country, postal_code, phone, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssi", $user['id'], $full_name, $street_address, $city, $state, $country, $postal_code, $phone, $is_default);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: profile.php?message=Address+saved+successfully");
    exit;
}

// ----- DELETE ADDRESS -----
if (isset($_GET['delete_address'])) {
    $address_id = (int)$_GET['delete_address'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE address_id = ?");
    $stmt->bind_param("i", $address_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] == 0) {
        $stmt = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $address_id, $user['id']);
        $stmt->execute();
        header("Location: profile.php?message=Address+deleted+successfully");
    } else {
        header("Location: profile.php?error=Address+in+use+cannot+be+deleted");
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
</head>
<body>
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
        <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
        <div class="mobile-nav" id="mobileNav" aria-hidden="true" role="navigation" aria-label="Mobile navigation">
            <div class="mobile-nav-header">
                <h2 class="mobile-nav-title">Menu</h2>
                <button class="mobile-nav-close" id="mobileNavClose" aria-label="Close navigation menu">✕</button>
            </div>
            <ul class="mobile-nav-links">
                <li><a href="index.php"><span class="nav-icon">🏠</span>Home</a></li>
                <li><a href="cart.php"><span class="nav-icon">🛒</span>Cart (<span class="cart-count"><?php echo $cart_count; ?></span>)</a></li>
                <li><a href="profile.php"><span class="nav-icon">👤</span>Profile</a></li>
                <li><a href="admin.php"><span class="nav-icon">⚙️</span>Admin</a></li>
                <li><a href="logout.php"><span class="nav-icon">🔐</span>Logout</a></li>
            </ul>
        </div>
    </header>

    <main>
        <section class="profile">
            <?php if (isset($_GET['message'])): ?>
                <p class="success"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></p>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <p class="error"><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></p>
            <?php endif; ?>

            <h2><i class="fas fa-user"></i> Your Profile</h2>
            <div id="profileInfo">
                <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
                <form method="POST">
                    <label>Full Name:</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    <label>Phone:</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    <button type="submit" name="update_profile"><i class="fas fa-save"></i> Update Profile</button>
                </form>
            </div>

            <h3><i class="fas fa-map-marker-alt"></i> Your Addresses</h3>
            <div id="addressList">
                <?php
                $stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $addresses = $stmt->get_result();
                while ($address = $addresses->fetch_assoc()) {
                ?>
                    <div class="address-item">
                        <p><strong><?php echo htmlspecialchars($address['full_name'] ?? 'N/A'); ?></strong> <?php if ($address['is_default']): ?>(Default)<?php endif; ?></p>
                        <p><?php echo htmlspecialchars($address['street_address'] ?? ''); ?>, <?php echo htmlspecialchars($address['city'] ?? ''); ?>, <?php echo htmlspecialchars($address['state'] ?? ''); ?>, <?php echo htmlspecialchars($address['country'] ?? ''); ?> <?php echo htmlspecialchars($address['postal_code'] ?? ''); ?></p>
                        <p>Phone: <?php echo htmlspecialchars($address['phone'] ?? 'N/A'); ?></p>
                        <a href="?edit_address=<?php echo $address['id']; ?>"><i class="fas fa-edit"></i> Edit</a>
                        <?php if (!$address['is_default']): ?>
                            <a href="?delete_address=<?php echo $address['id']; ?>" onclick="return confirm('Are you sure you want to delete this address?');"><i class="fas fa-trash"></i> Delete</a>
                        <?php endif; ?>
                    </div>
                <?php } $stmt->close(); ?>
                <button onclick="document.getElementById('addressForm').style.display='block';"><i class="fas fa-plus"></i> Add New Address</button>
            </div>

            <div id="addressForm" style="display:<?php echo isset($_GET['edit_address']) ? 'block' : 'none'; ?>;">
                <h3><?php echo isset($_GET['edit_address']) ? 'Edit Address' : 'Add Address'; ?></h3>
                <?php
                $edit_address = [];
                if (isset($_GET['edit_address'])) {
                    $address_id = (int)$_GET['edit_address'];
                    $stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $address_id, $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $edit_address = $result->fetch_assoc();
                    } else {
                        error_log("Invalid address_id: $address_id for user_id: {$user['id']}");
                        header("Location: profile.php?error=Invalid+address");
                        exit;
                    }
                    $stmt->close();
                }
                ?>
                <form method="POST">
                    <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($edit_address['id'] ?? '0'); ?>">
                    <label>Full Name:</label>
                    <input type="text" name="address_full_name" value="<?php echo htmlspecialchars($edit_address['full_name'] ?? ''); ?>" required>
                    <label>Street Address:</label>
                    <textarea name="street_address" required><?php echo htmlspecialchars($edit_address['street_address'] ?? ''); ?></textarea>
                    <label>City:</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($edit_address['city'] ?? ''); ?>" required>
                    <label>State:</label>
                    <input type="text" name="state" value="<?php echo htmlspecialchars($edit_address['state'] ?? ''); ?>" required>
                    <label>Country:</label>
                    <input type="text" name="country" value="<?php echo htmlspecialchars($edit_address['country'] ?? ''); ?>" required>
                    <label>Postal Code:</label>
                    <input type="text" name="postal_code" value="<?php echo htmlspecialchars($edit_address['postal_code'] ?? ''); ?>" required>
                    <label>Phone:</label>
                    <input type="text" name="address_phone" value="<?php echo htmlspecialchars($edit_address['phone'] ?? ''); ?>" />
                    <label>
                        <input type="checkbox" name="is_default" <?php echo isset($edit_address['is_default']) && $edit_address['is_default'] ? 'checked' : ''; ?>> Set as Default
                    </label>
                    <button type="submit" name="update_address"><i class="fas fa-save"></i> Save Address</button>
                    <button type="button" onclick="document.getElementById('addressForm').style.display='none';">Cancel</button>
                </form>
            </div>

            <h3><i class="fas fa-box"></i> Your Orders</h3>
            <div id="orderHistory">
                <?php
                $stmt = $conn->prepare("
                    SELECT o.*, a.street_address, a.city, a.state, a.country, a.postal_code, 
                           GROUP_CONCAT(CONCAT(p.name, ' (Qty: ', oi.quantity, ')')) as products 
                    FROM orders o 
                    JOIN addresses a ON o.address_id = a.id 
                    JOIN order_items oi ON o.id = oi.order_id 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE o.user_id = ? 
                    GROUP BY o.id
                ");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                $orders = $stmt->get_result();
                if ($orders->num_rows == 0) {
                    echo '<p>No orders found.</p>';
                }
                while ($order = $orders->fetch_assoc()) {
                ?>
                    <div class="order-item">
                        <p><strong>Order #<?php echo $order['id']; ?></strong> - $<?php echo number_format($order['total'], 2); ?> (<?php echo ucfirst($order['status']); ?>)</p>
                        <p>Products: <?php echo htmlspecialchars($order['products']); ?></p>
                        <p>Delivery Address: <?php echo htmlspecialchars($order['street_address'] . ', ' . $order['city'] . ', ' . $order['state'] . ', ' . $order['country'] . ' ' . $order['postal_code']); ?></p>
                        <p>Date: <?php echo $order['created_at']; ?></p>
                    </div>
                <?php } $stmt->close(); ?>
            </div>
        </section>
    </main>

    <footer>
        <p><i class="fas fa-copyright"></i> 2025 Deeken. All rights reserved.</p>
    </footer>

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
$conn->close();
?>