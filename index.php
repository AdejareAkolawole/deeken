<?php
require_once 'config.php';

// Get current user and cart count
$user = getCurrentUser();
$cartCount = getCartCount($conn, $user);

// Fetch categories for navigation
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch featured/new arrival products with stock
$new_arrivals_query = "SELECT p.*, c.name as category_name, 
                       COALESCE(AVG(r.rating), 0) AS avg_rating,
                       COUNT(r.id) AS review_count,
                       COALESCE(i.stock_quantity, 0) AS stock_quantity
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       LEFT JOIN reviews r ON p.id = r.product_id 
                       LEFT JOIN inventory i ON p.id = i.product_id 
                       GROUP BY p.id 
                       ORDER BY p.created_at DESC 
                       LIMIT 4";
$new_arrivals = $conn->query($new_arrivals_query);

// Fetch top selling products with stock
$top_selling_query = "SELECT p.*, c.name as category_name,
                       COALESCE(AVG(r.rating), 0) AS avg_rating,
                       COUNT(r.id) AS review_count,
                       COALESCE(SUM(oi.quantity), 0) AS total_sold,
                       COALESCE(i.stock_quantity, 0) AS stock_quantity
                       FROM products p 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       LEFT JOIN reviews r ON p.id = r.product_id 
                       LEFT JOIN order_items oi ON p.id = oi.product_id 
                       LEFT JOIN orders o ON oi.order_id = o.id 
                       LEFT JOIN inventory i ON p.id = i.product_id 
                       WHERE o.status != 'cancelled' OR o.status IS NULL 
                       GROUP BY p.id 
                       ORDER BY total_sold DESC, p.created_at DESC 
                       LIMIT 4";
$top_selling = $conn->query($top_selling_query);

// Fetch customer testimonials
$testimonials_query = "SELECT r.review_text, r.rating, u.full_name, r.created_at 
                      FROM reviews r 
                      JOIN users u ON r.user_id = u.id 
                      WHERE r.rating >= 4 
                      ORDER BY r.created_at DESC 
                      LIMIT 3";
$testimonials = $conn->query($testimonials_query);

// Handle newsletter subscription
if ($_POST['action'] ?? '' === 'subscribe_newsletter_subscribe') {
    $email = sanitize($conn, $_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Save to newsletter table or integrate with email service
        echo json_encode(['success' => true, 'message' => 'Successfully subscribed to newsletter!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    }
    exit;
}

// Handle add to cart
if ($_POST['action'] === 'add_to_cart') {
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Please login to add items to your cart.']);
        exit();
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id > 0) {
        // Check stock
        $stmt = $conn->prepare("SELECT stock_quantity FROM inventory WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stock = $result->num_rows > 0 ? $result->fetch_assoc()['stock_quantity'] : 0;
        $stmt->close();
        
        if ($stock >= $quantity) {
            // Check if item already in cart
            $cart_check = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $cart_check->bind_param("ii", $user['id'], $product_id);
            $cart_check->execute();
            $existing = $cart_check->get_result()->fetch_assoc();
            $cart_check->close();
            
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                $update_cart = $conn->prepare("UPDATE$ cart SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
                $update_cart->bind_param("iii", $new_quantity, $user['id'], $product_id);
                $success = $update_cart->execute();
                $update_cart->close();
                
            } else {
                // Add new item
                $add_cart = $cart->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $add_cart->bind_param("iii", $user['id'], $product_id, $quantity);
                $success = $add_cart->execute();
                $add_cart->close();
            }
            
            if ($success) {
                $newCartCount = getCartCount($conn, $user);
                echo json_encode(['success' => true, 'message' => 'Item added to cart!', 'cartCount' => $newCartCount]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add item to cart.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Product is out of stock.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    }
    exit();
}

// Function to display star rating
function displayStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '★';
        } else {
            $stars .= '☆';
        }
    }
    return $stars;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Find Clothes That Matches Your Style</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;300;500;600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: bold500;
            z-index: 1000;
            display: none;
            min-width: 300px;
        }
        .notification.success { background-color: #28a745; }
        .notification.error { background-color: #dc3545; }
        .notification.show { display: block; animation: slideIn 0.3s ease; }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        .login-prompt {
            background: rgba:(0,0,0,0.8);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .login-prompt .modal {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .btn-primary {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .add-to-cart-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px5px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: background-color 0.3s;
            width: 100%;
        }
        .add-to-cart-btn:hover:not(:disabled) {
            background: #218838;
opacity: 0.9;
        }
        .add-to-cart-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .out-of-stock {
            color: #dc3545;
            font-weight: bold;
            background: rgba:(220, 53, 69, 0.1);
            padding: 5px 10px;
        }
        /* Autocomplete styles */
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 2px 5px rgba:(0,0,0,0.1);
        }
        .autocomplete-suggestion {
            padding: 10px;
            cursor: pointer;
            font-size: 14px;
        }
        .autocomplete-suggestion:hover {
            background: #f0f0f0;
        }
        .search-bar {
            position: relative;
        }
    </style>
</head>
<body>
    <!-- Notification System -->
    <div id="notification" class="notification"></div>
    
    <!-- Login Prompt Modal -->
    <div id="loginPrompt" class="login-prompt">
        <div class="modal">
            <h3>Login Required</h3>
            <p>Please login to add items to your cart and make purchases.</p>
            <a href="login.php" class="btn-primary">Login</a>
            <a href="register.php" class="btn-secondary">Register</a>
            <button onclick="closeLoginPrompt()" class="btn-secondary">Cancel</button>
        </div>
    </div>

    <!-- Top Banner Marquee -->
    <div class="top-banner">
        <div class="marquee-container">
            <div class="marquee-content">
                <span class="marquee-text">🎉 Sign up and get 20% off to your first order. Sign Up Now</span>
                <span class="marquee-text">✨ Free shipping on orders over $50</span>
                <span class="marquee-text">🔥 Limited time offer - Don't miss out!</span>
                <span class="marquee-text">💫 New arrivals every week</span>
                <span class="marquee-text">🎉 Sign up and get 20% off to your first order. Sign Up Now</span>
            </div>
        </div>
        <button class="close-btn" onclick="closeBanner()">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo">Deeken</div>
        <div class="search-bar">
            <i class="fas fa-search" id="searchIcon"></i>
            <input type="text" placeholder="Search for products..." id="searchInput">
            <div class="autocomplete-suggestions" id="autocompleteSuggestions"></div>
        </div>
        <div class="nav-icons">
            <button class="cart-btn" title="Shopping Cart" onclick="window.location.href='cart.php'">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge" id="cartBadge"><?php echo $cartCount; ?></span>
            </button>
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>FIND CLOTHES<br>THAT MATCHES<br>YOUR STYLE</h1>
            <p>Browse through our diverse range of meticulously crafted garments, designed to bring out your individuality and cater to your sense of style.</p>
            <button class="cta-button" onclick="window.location.href='shop.php'">Shop Now</button>
        </div>
        <div class="hero-image">
            <div class="hero-couple"></div>
            <div class="sparkle sparkle-1"></div>
            <div class="sparkle sparkle-2"></div>
        </div>
    </section>

    <!-- Stats -->
    <section class="stats">
        <div class="stat-item">
            <h3><?php echo count($categories); ?>+</h3>
            <p>Product Categories</p>
        </div>
        <div class="stat-item">
            <h3>2,000+</h3>
            <p>High-Quality Products</p>
        </div>
        <div class="stat-item">
            <h3>30,000+</h3>
            <p>Happy Customers</p>
        </div>
    </section>

    <!-- Categories -->
    <div class="categories">
        <div class="categories-text">
            <h2>Shop by Category</h2>
            <p>Choose from a variety of options tailored just for you</p>
        </div>
        <?php foreach ($categories as $category): ?>
            <div class="category-item" onclick="window.location.href='shop.php?category=<?php echo $category['id']; ?>'">
                <div class="category-circle"></div>
                <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- New Arrivals Section -->
    <section class="new-arrivals">
        <h2 class="section-title">New Arrivals</h2>
        <div class="new-arrivals-grid product-grid">
            <div class="scroll-container">
                <?php
                $new_arrivals->data_seek(0);
                while ($product = $new_arrivals->fetch_assoc()):
                    $avg_rating = round($product['avg_rating'], 1);
                    $review_count = $product['review_count'];
                    $stock = $product['stock_quantity'];
                ?>
                    <div class="new-arrival-card product-card" data-product-id="<?php echo $product['id']; ?>">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/400x300'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                            <?php if ($stock == 0): ?>
                                <div class="product-badge out-of-stock">Out of Stock</div>
                            <?php else: ?>
                                <div class="product-badge">New</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-details">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price">
                                <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                                <?php if (isset($product['original_price']) && $product['original_price'] > $product['price']): ?>
                                    <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="color-options">
                                <div class="color-dot active"></div>
                                <div class="color-dot"></div>
                                <div class="color-dot"></div>
                                <div class="color-dot"></div>
                            </div>
                            <div class="product-rating">
                                <span class="stars"><?php echo displayStars($avg_rating); ?></span>
                                <span class="rating-text"><?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)</span>
                            </div>
                            <button class="add-to-cart-btn" onclick="addToCart(<?php echo $product['id']; ?>)" <?php echo $stock == 0 ? 'disabled' : ''; ?>>Add to Cart</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <div class="view-all">
            <button class="view-all-btn" onclick="window.location.href='shop.php?filter=new'">View All</button>
        </div>
    </section>

    <!-- Top Selling Section -->
    <section class="top-selling">
        <h2 class="section-title">Top Selling</h2>
        <div class="top-selling-grid product-grid">
            <div class="scroll-container">
                <?php
                $top_selling->data_seek(0);
                while ($product = $top_selling->fetch_assoc()):
                    $avg_rating = round($product['avg_rating'], 1);
                    $review_count = $product['review_count'];
                    $stock = $product['stock_quantity'];
                ?>
                    <div class="top-selling-card product-card" data-product-id="<?php echo $product['id']; ?>">
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/400x300'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                            <?php if ($stock == 0): ?>
                                <div class="product-badge out-of-stock">Out of Stock</div>
                            <?php else: ?>
                                <div class="product-badge">Hot</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-details">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price">
                                <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                                <?php if (isset($product['original_price']) && $product['original_price'] > $product['price']): ?>
                                    <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="color-options">
                                <div class="color-dot active"></div>
                                <div class="color-dot"></div>
                                <div class="color-dot"></div>
                                <div class="color-dot"></div>
                            </div>
                            <div class="product-rating">
                                <span class="stars"><?php echo displayStars($avg_rating); ?></span>
                                <span class="rating-text"><?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)</span>
                            </div>
                            <button class="add-to-cart-btn" onclick="addToCart(<?php echo $product['id']; ?>)" <?php echo $stock == 0 ? 'disabled' : ''; ?>>Add to Cart</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <div class="view-all">
            <button class="view-all-btn" onclick="window.location.href='shop.php?filter=popular'">View All</button>
        </div>
    </section>

    <style>
        /* Updated styles for centralization and auto-scrolling */
        .new-arrivals-grid, .top-selling-grid {
            padding: 0 5%;
            text-align: center;
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
        }

        .scroll-container {
            display: flex;
            flex-direction: row;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 20px 0;
            max-width: 100%;
            margin: 0 auto;
            justify-content: center;
            flex-wrap: nowrap;
        }

        .scroll-container::-webkit-scrollbar {
            height: 8px;
        }

        .scroll-container::-webkit-scrollbar-thumb {
            background-color: var(--medium-gray);
            border-radius: 4px;
        }

        .scroll-container::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        .new-arrival-card, .top-selling-card {
            min-width: 250px;
            flex: 0 0 auto;
            background: var(--primary-white);
            border: 1px solid var(--medium-gray);
            border-radius: 10px;
            overflow: hidden;
            text-align: center;
            padding: 10px;
            transition: var(--transition);
        }

        .new-arrival-card:hover, .top-selling-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-5px);
        }

        .product-image-container {
            position: relative;
            height: 250px;
            overflow: hidden;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
        }

        .product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--accent-color);
            color: var(--primary-white);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            text-transform: uppercase;
        }

        .product-details {
            padding: 15px;
        }

        .product-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .original-price {
            font-size: 14px;
            color: var(--text-gray);
            text-decoration: line-through;
            margin-left: 5px;
        }

        .color-options {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-bottom: 10px;
        }

        .color-dot {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #ccc;
            cursor: pointer;
        }

        .color-dot.active {
            border: 2px solid var(--accent-color);
        }

        .product-rating {
            margin-bottom: 10px;
        }

        .stars {
            color: #f4c430;
            font-size: 14px;
        }

        .rating-text {
            font-size: 12px;
            color: var(--text-gray);
            margin-left: 5px;
        }

        .add-to-cart-btn {
            background: var(--gradient, #28a745);
            color: var(--primary-white);
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
        }

        @media (min-width: 768px) {
            .scroll-container {
                justify-content: center;
            }

            .new-arrivals-grid, .top-selling-grid {
                padding: 0 5%;
            }

            .product-image-container {
                height: 300px;
            }

            .product-title {
                font-size: 18px;
            }

            .product-price {
                font-size: 20px;
            }

            .add-to-cart-btn {
                padding: 10px 20px;
            }
        }

        @media (min-width: 1024px) {
            .scroll-container {
                gap: 30px;
                justify-content: center;
            }

            .product-image-container {
                height: 350px;
            }

            .product-title {
                font-size: 20px;
            }

            .product-price {
                font-size: 22px;
            }

            .add-to-cart-btn {
                padding: 12px 24px;
            }
        }
    </style>

    <!-- Testimonials -->
    <section class="testimonials">
        <h2 class="section-title">OUR HAPPY CUSTOMERS</h2>
        <div class="testimonial-grid">
            <?php if ($testimonials->num_rows > 0): ?>
                <?php while ($testimonial = $testimonials->fetch_assoc()): ?>
                    <div class="testimonial-card">
                        <div class="rating">
                            <span class="stars"><?php echo displayStars($testimonial['rating']); ?></span>
                        </div>
                        <div class="testimonial-header">
                            <span class="customer-name"><?php echo htmlspecialchars($testimonial['full_name']); ?></span>
                            <span class="verified">✓</span>
                        </div>
                        <p class="testimonial-text"><?php echo htmlspecialchars($testimonial['review_text']); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="testimonial-card">
                    <div class="rating">
                        <span class="stars">★★★★★</span>
                    </div>
                    <div class="testimonial-header">
                        <span class="customer-name">Sarah M.</span>
                        <span class="verified">✓</span>
                    </div>
                    <p class="testimonial-text">I'm blown away by the quality and style of the clothes I received from Deeken. From casual wear to elegant dresses, every piece I've bought has exceeded my expectations.</p>
                </div>
                <div class="testimonial-card">
                    <div class="rating">
                        <span class="stars">★★★★★</span>
                    </div>
                    <div class="testimonial-header">
                        <span class="customer-name">Alex K.</span>
                        <span class="verified">✓</span>
                    </div>
                    <p class="testimonial-text">Finding clothes that align with my personal style used to be a challenge until I discovered Deeken. The range of options they offer is truly remarkable.</p>
                </div>
                <div class="testimonial-card">
                    <div class="rating">
                        <span class="stars">★★★★★</span>
                    </div>
                    <div class="testimonial-header">
                        <span class="customer-name">James L.</span>
                        <span class="verified">✓</span>
                    </div>
                    <p class="testimonial-text">As someone who's always on the lookout for unique fashion pieces, I'm thrilled to have stumbled upon Deeken. The selection is diverse and on-point with trends.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <h3>DEEKEN</h3>
                <p>We have clothes that suits your style and which you're proud to wear. From women to men.</p>
                <div class="social-icons">
                    <div class="social-icon">f</div>
                    <div class="social-icon">t</div>
                    <div class="social-icon">in</div>
                    <div class="social-icon">ig</div>
                </div>
            </div>
            <div class="footer-column">
                <h4>Company</h4>
                <ul>
                    <li><a href="unified.html?section=about">About</a></li>
                    <li><a href="unified.html?section=contact">Contact</a></li>
                    <li><a href="unified.html?section=careers">Careers</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Help</h4>
                <ul>
                    <li><a href="unified.html?section=support">Customer Support</a></li>
                    <li><a href="unified.html?section=shipping">Delivery Details</a></li>
                    <li><a href="unified.html?section=terms">Terms & Conditions</a></li>
                    <li><a href="unified.html?section=privacy">Privacy Policy</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>FAQ</h4>
                <ul>
                    <li><a href="unified.html?section=faq-account">Account</a></li>
                    <li><a href="unified.html?section=faq-delivery">Manage Deliveries</a></li>
                    <li><a href="unified.html?section=faq-orders">Orders</a></li>
                    <li><a href="unified.html?section=faq-payments">Payments</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Resources</h4>
                <ul>
                    <li><a href="unified.html?section=blog">Blog</a></li>
                    <li><a href="unified.html?section=size-guide">Size Guide</a></li>
                    <li><a href="unified.html?section=care-instructions">Care Instructions</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>Deeken © 2025, All Rights Reserved</p>
            <div class="payment-icons">
                <div class="payment-icon">💳</div>
                <div class="payment-icon">🏦</div>
                <div class="payment-icon">📱</div>
            </div>
        </div>
    </footer>

    <script>
        const isLoggedIn = <?php echo $user ? 'true' : 'false'; ?>;

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        function showLoginPrompt() {
            document.getElementById('loginPrompt').style.display = 'flex';
        }

        function closeLoginPrompt() {
            document.getElementById('loginPrompt').style.display = 'none';
        }

        async function addToCart(productId) {
            if (!isLoggedIn) {
                showLoginPrompt();
                return;
            }

            try {
                // Check stock
                const response = await fetch(`check_stock.php?product_id=${productId}`);
                const data = await response.json();

                if (data.error || data.stock <= 0) {
                    showNotification('Product is out of stock', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);

                const cartResponse = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const cartData = await cartResponse.json();

                if (cartData.success) {
                    showNotification(cartData.message, 'success');
                    document.getElementById('cartBadge').textContent = cartData.cartCount;
                    window.location.href = 'cart.php';
                } else {
                    showNotification(cartData.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        }

        function subscribeNewsletter() {
            const email = document.getElementById('emailInput').value.trim();
            if (!email) {
                showNotification('Please enter your email address.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'subscribe_newsletter');
            formData.append('email', email);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('emailInput').value = '';
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        function closeBanner() {
            document.querySelector('.top-banner').style.display = 'none';
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchIcon = document.getElementById('searchIcon');
        const autocompleteSuggestions = document.getElementById('autocompleteSuggestions');

        function performSearch() {
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = `products.php?q=${encodeURIComponent(query)}`;
            }
        }

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        searchIcon.addEventListener('click', performSearch);

        // Autocomplete functionality
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length >= 2) {
                fetch(`autocomplete.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        autocompleteSuggestions.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'autocomplete-suggestion';
                                div.textContent = item.name;
                                div.addEventListener('click', () => {
                                    searchInput.value = item.name;
                                    autocompleteSuggestions.style.display = 'none';
                                    window.location.href = `products.php?q=${encodeURIComponent(item.name)}`;
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
                    });
            } else {
                autocompleteSuggestions.style.display = 'none';
            }
        });

        // Hide autocomplete when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !autocompleteSuggestions.contains(e.target)) {
                autocompleteSuggestions.style.display = 'none';
            }
        });

        // Profile dropdown toggle
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.querySelector('.profile-trigger');
            if (!trigger.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });

        // Auto-scroll functionality
        document.addEventListener('DOMContentLoaded', () => {
            function autoScroll(containerClass) {
                const container = document.querySelector(`.${containerClass} .scroll-container`);
                if (!container) return;

                const cards = container.querySelectorAll('.product-card');
                if (cards.length <= 3) {
                    container.style.justifyContent = 'center';
                    container.style.overflowX = 'hidden';
                    return;
                }

                let scrollAmount = 0;
                const scrollSpeed = 1;
                const cardWidth = cards[0].offsetWidth + 20;
                const maxScroll = container.scrollWidth - container.clientWidth;

                function scroll() {
                    if (scrollAmount >= maxScroll) {
                        scrollAmount = 0;
                        container.scrollLeft = 0;
                    } else {
                        scrollAmount += scrollSpeed;
                        container.scrollLeft = scrollAmount;
                    }
                    requestAnimationFrame(scroll);
                }

                setTimeout(() => requestAnimationFrame(scroll), 2000);

                container.addEventListener('mouseenter', () => {
                    scrollAmount = container.scrollLeft;
                    cancelAnimationFrame(scroll);
                });

                container.addEventListener('mouseleave', () => {
                    requestAnimationFrame(scroll);
                });
            }

            autoScroll('new-arrivals');
            autoScroll('top-selling');
        });
    </script>
</body>
</html>