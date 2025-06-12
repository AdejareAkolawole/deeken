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
    <link rel="stylesheet" href="carttt.css">
    <link rel="stylesheet" href="hamburger.css">
    <style>  
        /* ===== NAVIGATION WITH SCROLL HIDE ===== */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            transform: translateY(0);
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease;
        }

        .navbar.navbar-hidden {
            transform: translateY(-100%);
        }

        .navbar.navbar-visible {
            transform: translateY(0);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.15);
        }

        /* Add body padding to account for fixed navbar */
        body {
            padding-top: 80px;
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

        /* ===== CART STYLES ===== */
        .cart {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1.5rem;
            background: linear-gradient(135deg, #FFFFFF, #F6F6F6);
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #f0f0f0;
        }

        .cart h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 2px solid #f7fafc;
            padding-bottom: 1rem;
        }

        /* Cart Items */
        .cart-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(42, 42, 255, 0.1);
            border-color: #2A2AFF;
        }

        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }

        .cart-item-details {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .cart-item-details h3 {
            color: #2d3748;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            min-width: 150px;
        }

        .cart-item-details p {
            color: #2A2AFF;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            min-width: 80px;
        }

        .cart-item form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-item input[type="number"] {
            width: 60px;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            background: white;
            color: #2d3748;
            font-family: 'Poppins', sans-serif;
            border-radius: 6px;
            text-align: center;
            font-weight: 500;
        }

        .cart-item input[type="number"]:focus {
            outline: none;
            border-color: #2A2AFF;
            box-shadow: 0 0 0 3px rgba(42, 42, 255, 0.1);
        }

        .cart-item button {
            background: #2A2AFF;
            color: white;
            border: none;
            padding: 0.5rem 0.8rem;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .cart-item button:hover {
            background: #1f1fcc;
            transform: scale(1.05);
        }

        .cart-item button[name="remove_from_cart"] {
            background: #ef4444;
        }

        .cart-item button[name="remove_from_cart"]:hover {
            background: #dc2626;
        }

        /* Cart Footer */
        .cart-footer {
            margin-top: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc, #edf2f7);
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .cart-footer p {
            color: #2A2AFF;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.4rem;
            margin-bottom: 1rem;
        }

        .cta-button {
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(42, 42, 255, 0.2);
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(42, 42, 255, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.8rem 1rem;
            }
            
            body {
                padding-top: 70px;
            }

            .cart {
                margin: 1rem;
                padding: 1rem;
            }

            .cart-item {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .cart-item-details {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 0.5rem;
            }

            .cart-item img {
                width: 100px;
                height: 100px;
            }

            .nav-right {
                gap: 1rem;
            }

            .search-bar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- ----- NAVIGATION ----- -->
    <header>
        <nav class="navbar" id="navbar">
            <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
          
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search products..." onkeypress="if(event.key==='Enter') searchProducts()">
                <button type="button" onclick="searchProducts()"><i class="fas fa-search"></i></button>
            </div>
            
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
                    
                    if ($cart_items->num_rows > 0):
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
                            <p>Total: $<?php echo number_format($item_total, 2); ?></p>
                            <form method="POST" onsubmit="removeFromCart(event, <?php echo $item['product_id']; ?>)">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <button type="submit" name="remove_from_cart"><i class="fas fa-trash"></i> Remove</button>
                            </form>
                        </div>
                    </div>
                <?php 
                        endwhile; 
                    else: 
                ?>
                    <div class="empty-cart">
                        <h3>Your cart is empty</h3>
                        <p><a href="index.php">Continue shopping</a></p>
                    </div>
                <?php 
                    endif;
                    $stmt->close(); 
                else: 
                ?>
                    <div class="empty-cart">
                        <h3>Please sign in to view your cart</h3>
                        <p><a href="login.php">Login</a> or <a href="register.php">Create Account</a></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($user && $total > 0): ?>
            <div class="cart-footer">
                <p>Total: $<span id="cartTotal"><?php echo number_format($total, 2); ?></span></p>
                <a href="checkout.php" class="cta-button"><i class="fas fa-check"></i> Proceed to Checkout</a>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- ----- JAVASCRIPT ----- -->
    <script src="utils.js"></script>
     <script src ="hamburger.js"></script>
      <script src ="utils.js"></script>
   
    <script>
        // Navbar scroll hide/show functionality
        let lastScrollTop = 0;
        const navbar = document.getElementById('navbar');
        const scrollThreshold = 100; // Start hiding after 100px of scroll

        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            
            // Skip if we haven't scrolled enough
            if (currentScroll < scrollThreshold) {
                navbar.classList.remove('navbar-hidden');
                navbar.classList.add('navbar-visible');
                return;
            }
            
            // Scrolling down
            if (currentScroll > lastScrollTop && currentScroll > scrollThreshold) {
                navbar.classList.add('navbar-hidden');
                navbar.classList.remove('navbar-visible');
            } 
            // Scrolling up
            else if (currentScroll < lastScrollTop) {
                navbar.classList.remove('navbar-hidden');
                navbar.classList.add('navbar-visible');
            }
            
            lastScrollTop = currentScroll <= 0 ? 0 : currentScroll; // For mobile or negative scrolling
        });

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
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                fetch('cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `remove_from_cart=1&product_id=${productId}`
                }).then(() => window.location.reload());
            }
        }

        // Add some content for demonstration (remove this in production)
        window.addEventListener('DOMContentLoaded', function() {
            // Add some placeholder content to make the page scrollable for testing
            const main = document.querySelector('main');
            const demoContent = document.createElement('div');
            demoContent.style.cssText = 'height: 200vh; background: linear-gradient(to bottom, #f8fafc, #e2e8f0); margin-top: 2rem; padding: 2rem; text-align: center; color: #666;';
            demoContent.innerHTML = '<h3>Scroll down to see the navbar hide!</h3><p>Scroll back up to see it appear again.</p>';
            // Uncomment the line below to add demo content
            // main.appendChild(demoContent);
        });
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>