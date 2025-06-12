<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user
$user = getCurrentUser();

// ----- PRODUCT ID -----
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ----- REVIEW SUBMISSION -----
if (isset($_POST['submit_review']) && $user && $product_id) {
    $text = sanitize($conn, $_POST['review_text']);
    $rating = (int)$_POST['rating'];
    $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, review_text, rating) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $product_id, $user['id'], $text, $rating);
    $stmt->execute();
    $stmt->close();
}

// ----- FETCH PRODUCT -----
$stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);

// ----- FUNCTION TO DISPLAY STAR RATING -----
function displayStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="fas fa-star star-filled"></i>';
        } else {
            $stars .= '<i class="fas fa-star star-empty"></i>';
        }
    }
    return $stars;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and CSS -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - <?php echo htmlspecialchars($product['name'] ?? 'Product'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="radial.css">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="product.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="hamburger.css">
    <style>
        .star-filled { color: #f1c40f; }
        .star-empty { color: #ccc; }
        .star-filled, .star-empty { font-size: 16px; margin-right: 2px; }
        .star-rating { display: inline-block; }
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 20px;
            cursor: pointer;
            color: #ccc;
            transition: color 0.2s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #f1c40f;
        }
        .star-rating input:checked ~ label {
            color: #f1c40f;
        }

        /* Styles for Main Content Wrapper */
        .main-content-wrapper {
            display: flex;
            gap: 2rem;
            margin: 2rem;
        }

        .left-content {
            flex: 2;
            display: flex;
            flex-direction: column;
            position: relative;
            left: 300px;
        }

        .right-content {
            flex: 1;
            position: sticky;
            top: 2rem;
            height: fit-content;
            max-width: 400px;
        }

        /* Styles for Other Categories Section */
        .other-categories {
            background: linear-gradient(135deg, #BDF3FF, rgba(189, 243, 255, 0.3));
            padding: 1rem;
            border-radius: 12px;
            animation: fadeIn 0.5s ease-out;
            border: 2px solid rgba(65, 64, 255, 0.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.7);
            max-height: 50vh; /* Fallback: ~500px on most screens */
            max-height: 500px;
            overflow-y: auto; /* Enable vertical scrollbar */
            scrollbar-width: #4140ff; /* Firefox */
            scrollbar-color: #3333cc; /* Firefox */
        }

        /* Custom Scrollbar for Webkit browsers (Chrome, Safari, Edge) */
        .other-categories::-webkit-scrollbar {
            width: 8px;
        }

        .other-categories::-webkit-scrollbar-track {
            background: #BDF3FF;
            border-radius: 4px;
        }

        .other-categories::-webkit-scrollbar-thumb {
            background: #4140FF;
            border-radius: 4px;
        }

        .other-categories::-webkit-scrollbar-thumb:hover {
            background: #3333cc;
        }

        .other-categories h2 {
            font-family: 'Poppins', sans-serif;
            color: #4140FF;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .category-section {
            margin-bottom: 1.5rem;
        }

        .category-section h3 {
            font-family: 'Poppins', sans-serif;
            color: #4140FF;
            font-size: 1rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .category-products {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.8rem;
        }

        .category-product-card {
            background: rgba(255, 255, 255, 0.6);
            padding: 0.6rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid rgba(65, 64, 255, 0.1);
            text-align: center;
        }

        .category-product-card:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-3px);
            box-shadow: 0 2px 10px rgba(65, 64, 255, 0.2);
        }

        .category-product-card img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
        }

        .category-product-card h4 {
            font-size: 0.9rem;
            color: #4140FF;
            margin: 0.5rem 0;
            font-family: 'Poppins', sans-serif;
        }

        .category-product-card p {
            font-size: 0.85rem;
            color: #333;
            margin: 0.3rem 0;
        }

        .category-product-card form button {
            background: #4140FF;
            color: #FFFFFF;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.3s ease;
        }

        .category-product-card form button:hover {
            background: #3333cc;
        }

        /* Adjust existing sections */
        .product-details {
            padding: 2rem;
            display: flex;
            gap: 2rem;
            background: linear-gradient(135deg, #FFFFFF, #F6F6F6);
            border-radius: 15px;
            margin: 0;
            animation: fadeIn 0.5s ease-out;
        }

        .reviews {
            padding: 2rem;
            background: rgba(65, 64, 255, 0.2);
            border-radius: 15px;
            margin: 0;
            animation: fadeIn 0.5s ease-out;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content-wrapper {
                flex-direction: column;
                margin: 1rem;
            }

            .left-content {
                left: 0;
            }

            .right-content {
                position: static;
                order: 3;
                max-width: none;
                margin-top: 2rem;
            }

            .other-categories {
                padding: 1rem;
                max-height: 40vh; /* ~300px on smaller screens */
                max-height: 300px;
            }

            .category-section h3 {
                font-size: 0.95rem;
            }

            .category-products {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 0.6rem;
            }

            .category-product-card {
                padding: 0.5rem;
            }

            .category-product-card h4 {
                font-size: 0.85rem;
            }

            .category-product-card p {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .other-categories {
                padding: 0.8rem;
                border-radius: 10px;
                max-height: 35vh; /* ~250px on very small screens */
                max-height: 250px;
            }

            .category-product-card {
                padding: 0.4rem;
            }

            .category-product-card h4 {
                font-size: 0.8rem;
            }

            .category-product-card form button {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
        }
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

.navbar.hidden {
    transform: translateY(-100%);
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
</head>
<body>
    <!-- ----- NAVIGATION ----- -->
    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search products...">
            <button onclick="searchProducts()"><i class="fas fa-search"></i></button>
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

    <!-- ----- MAIN CONTENT WRAPPER ----- -->
    <main>
        <?php if ($product): ?>
            <div class="main-content-wrapper">
                <!-- ----- LEFT CONTENT ----- -->
                <div class="left-content">
                    <!-- ----- PRODUCT DETAILS ----- -->
                    <section class="product-details" id="productDetails">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="product-info">
                            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                            <p>$<?php echo number_format($product['price'], 2); ?></p>
                            <p><?php echo displayStars($product['rating']); ?></p>
                            <p><?php echo htmlspecialchars($product['description']); ?></p>
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" name="add_to_cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                            </form>
                        </div>
                    </section>

                    <!-- ----- REVIEWS ----- -->
                    <section class="reviews">
                        <h2><i class="fas fa-star"></i> Reviews</h2>
                        <div id="reviewList">
                            <?php
                            $stmt = $conn->prepare("SELECT r.*, u.full_name, u.email FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ?");
                            $stmt->bind_param("i", $product_id);
                            $stmt->execute();
                            $reviews = $stmt->get_result();
                            if ($reviews->num_rows > 0):
                                while ($review = $reviews->fetch_assoc()):
                            ?>
                                <div class="review">
                                    <p><strong><?php echo htmlspecialchars($review['full_name'] ?? $review['email']); ?></strong></p>
                                    <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                                    <p><?php echo displayStars($review['rating']); ?></p>
                                </div>
                            <?php endwhile; else: ?>
                                <p>No reviews yet.</p>
                            <?php endif; $stmt->close(); ?>
                        </div>
                        <?php if ($user): ?>
                            <form id="reviewForm" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                <textarea name="review_text" placeholder="Write your review..." required></textarea>
                                <div class="star-rating">
                                    <input type="radio" id="star5" name="rating" value="5" required>
                                    <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star4" name="rating" value="4">
                                    <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star3" name="rating" value="3">
                                    <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star2" name="rating" value="2">
                                    <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="star1" name="rating" value="1">
                                    <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                                </div>
                                <button type="submit" name="submit_review"><i class="fas fa-paper-plane"></i> Submit Review</button>
                            </form>
                        <?php else: ?>
                            <p><a href="login.php">Login</a> to submit a review.</p>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- ----- RIGHT CONTENT ----- -->
                <div class="right-content">
                    <!-- ----- OTHER CATEGORIES ----- -->
                    <section class="other-categories">
                        <h2><i class="fas fa-th-list"></i> Other Categories</h2>
                        <?php
                        // Fetch categories with products, excluding the current product's category_id
                        $stmt = $conn->prepare("
                            SELECT DISTINCT c.id, c.name 
                            FROM categories c 
                            JOIN products p ON c.id = p.category_id 
                            WHERE c.id != ? 
                            ORDER BY c.name
                        ");
                        $stmt->bind_param("i", $product['category_id']);
                        $stmt->execute();
                        $categories = $stmt->get_result();

                        if ($categories->num_rows > 0):
                            while ($cat = $categories->fetch_assoc()):
                                // Fetch up to 3 products from each category
                                $cat_stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? LIMIT 3");
                                $cat_stmt->bind_param("i", $cat['id']);
                                $cat_stmt->execute();
                                $cat_products = $cat_stmt->get_result();
                                if ($cat_products->num_rows > 0):
                        ?>
                                    <div class="category-section">
                                        <h3><i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat['name']); ?></h3>
                                        <div class="category-products">
                                            <?php while ($cat_product = $cat_products->fetch_assoc()): ?>
                                                <div class="category-product-card">
                                                    <img src="<?php echo htmlspecialchars($cat_product['image']); ?>" alt="<?php echo htmlspecialchars($cat_product['name']); ?>">
                                                    <h4><?php echo htmlspecialchars($cat_product['name']); ?></h4>
                                                    <p>$<?php echo number_format($cat_product['price'], 2); ?></p>
                                                    <p><?php echo displayStars($cat_product['rating']); ?></p>
                                                    <form method="POST" action="cart.php">
                                                        <input type="hidden" name="product_id" value="<?php echo $cat_product['id']; ?>">
                                                        <button type="submit" name="add_to_cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                                                    </form>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                <?php endif; $cat_stmt->close(); ?>
                            <?php endwhile; else: ?>
                                <p style="color: #4140FF; text-align: center; margin: 0;">No other categories available</p>
                            <?php endif; $stmt->close(); ?>
                    </section>
                </div>
            </div>

            <!-- ----- RELATED PRODUCTS ----- -->
            <section class="related-products">
                <h2><i class="fas fa-th"></i> Related Products</h2>
                <div class="product-grid" id="related-products">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4");
                    $stmt->bind_param("ii", $product['category_id'], $product_id);
                    $stmt->execute();
                    $related = $stmt->get_result();
                    if ($related->num_rows > 0):
                        while ($rel = $related->fetch_assoc()):
                    ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($rel['image']); ?>" alt="<?php echo htmlspecialchars($rel['name']); ?>">
                            <h3><?php echo htmlspecialchars($rel['name']); ?></h3>
                            <p>$<?php echo number_format($rel['price'], 2); ?></p>
                            <p><?php echo displayStars($rel['rating']); ?></p>
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="product_id" value="<?php echo $rel['id']; ?>">
                                <button type="submit" name="add_to_cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                            </form>
                        </div>
                    <?php endwhile; else: ?>
                        <p>No related products found.</p>
                    <?php endif; $stmt->close(); ?>
                </div>
            </section>
        <?php else: ?>
            <p>Product not found.</p>
        <?php endif; ?>
    </main>

    <!-- ----- JAVASCRIPT ----- -->
    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
    <script>
        // Ensure stars are displayed in reverse order (5 to 1) for visual consistency
        document.querySelectorAll('.star-rating label').forEach(label => {
            label.addEventListener('click', () => {
                const input = document.querySelector(`#${label.getAttribute('for')}`);
                input.checked = true;
            });
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

        // Navbar scroll behavior
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');

        window.addEventListener('scroll', function() {
            let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            
            if (currentScroll > lastScrollTop) {
                // Scrolling down
                navbar.classList.add('hidden');
            } else {
                // Scrolling up
                navbar.classList.remove('hidden');
            }
            
            // Show navbar when at the top of the page
            if (currentScroll <= 0) {
                navbar.classList.remove('hidden');
            }
            
            lastScrollTop = currentScroll <= 0 ? 0 : currentScrollTop;
        });
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>