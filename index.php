<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user
$user = getCurrentUser();

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

// ----- FETCH TOTAL STOCK FOR METRICS -----
$total_stock = $conn->query("SELECT SUM(stock_quantity) as sum FROM inventory")->fetch_assoc()['sum'] ?? 0;

// ----- FETCH CATEGORIES -----
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);

// ----- FETCH CAROUSEL IMAGES -----
$carousel_query = "SELECT * FROM carousel_images WHERE is_active = 1 ORDER BY created_at DESC LIMIT 8";
$carousel_result = $conn->query($carousel_query);

// ----- FETCH FEATURED PRODUCTS FOR CAROUSEL -----
$featured_query = "SELECT p.* FROM products p WHERE p.featured = 1 ORDER BY RAND() LIMIT 8";
$featured_result = $conn->query($featured_query);

// ----- FETCH TRENDING PRODUCTS -----
$trending_query = "SELECT p.* FROM products p JOIN miscellaneous_attributes ma ON p.id = ma.product_id WHERE ma.attribute = 'trending' ORDER BY p.rating DESC, p.created_at DESC LIMIT 12";
$trending_result = $conn->query($trending_query);

// ----- FETCH NEW ARRIVALS -----
$new_arrivals_query = "SELECT p.* FROM products p JOIN miscellaneous_attributes ma ON p.id = ma.product_id WHERE ma.attribute = 'new_arrival' ORDER BY p.created_at DESC LIMIT 12";
$new_arrivals_result = $conn->query($new_arrivals_query);

// ----- PRODUCT QUERY -----
$products_query = "SELECT p.*, i.stock_quantity FROM products p JOIN categories c ON p.category_id = c.id LEFT JOIN inventory i ON p.id = i.product_id";
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : '';
$sort = isset($_GET['sort']) ? sanitize($conn, $_GET['sort']) : '';
$where_clauses = [];

if ($search) {
    $where_clauses[] = "p.name LIKE '%$search%'";
}
if ($category_id) {
    $where_clauses[] = "p.category_id = $category_id";
}
if ($where_clauses) {
    $products_query .= " WHERE " . implode(" AND ", $where_clauses);
}
if ($sort === 'price-asc') {
    $products_query .= " ORDER BY p.price ASC";
} elseif ($sort === 'price-desc') {
    $products_query .= " ORDER BY p.price DESC";
} elseif ($sort === 'rating-desc') {
    $products_query .= " ORDER BY p.rating DESC";
}
$products_result = $conn->query($products_query);

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and CSS -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="responsive.css">
 
    <style>
        .out-of-stock {
            background: #ef4444;
            color: white;
            padding: 8px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
            cursor: not-allowed;
            margin-top: 8px;
        }
        .add-to-cart-btn.out-of-stock {
            background: #9ca3af; /* Gray for dormant button */
            cursor: not-allowed;
            opacity: 0.7;
        }
        .cart-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            text-align: center;
        }
        .cart-popup.show {
            display: block;
        }
        .cart-popup p {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .cart-popup button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 5px;
        }
        .cart-popup button:hover {
            background: #2563eb;
        }
        .cart-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .cart-overlay.show {
            display: block;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            padding: 2rem 0;
        }
        .metric-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        .metric-card:hover {
            transform: translateY(-5px);
        }
        .metric-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: white;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
        }
        .metric-label {
            font-size: 1rem;
            color: #666;
            margin: 0.5rem 0;
        }
        .metric-change {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #4caf50;
        }
        .stock-info {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- ----- CART POPUP ----- -->
    <div class="cart-overlay" id="cartOverlay"></div>
    <div class="cart-popup" id="cartPopup">
        <p>You already have this product in your cart. This action will increase the quantity by 1.</p>
        <button onclick="confirmAddToCart()">Confirm</button>
        <button onclick="closeCartPopup()">Cancel</button>
    </div>

    <!-- ----- MARQUEE ----- -->
    <div class="marquee-section">
        <div class="marquee">
            <span>🎉 New Arrival Up - Shop the Latest Products Now! Free Shipping on Orders Over $50! 🚚</span>
        </div>
    </div>

    <!-- ----- NAVIGATION ----- -->
    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
            <button onclick="searchProducts()"><i class="fas fa-search"></i></button>
            <ul class="autocomplete-suggestions" id="autocompleteSuggestions"></ul>
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
                        <?php if ($user['is_admin']): ?>
                            <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
                        <?php endif; ?>
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

    <!-- ----- HERO CAROUSEL ----- -->
    <div class="hero-carousel" id="heroCarousel">
        <?php if ($carousel_result && $carousel_result->num_rows > 0): ?>
            <?php $first = true; while ($slide = $carousel_result->fetch_assoc()): ?>
                <div class="hero-slide <?php echo $first ? 'active' : ''; ?>">
                    <div class="hero-content">
                        <h1><?php echo htmlspecialchars($slide['title']); ?></h1>
                        <p><?php echo htmlspecialchars($slide['subtitle']); ?></p>
                        <a href="<?php echo htmlspecialchars($slide['link'] ?: '#products'); ?>" class="hero-cta">
                            Shop Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="hero-image">
                        <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>">
                    </div>
                </div>
                <?php $first = false; endwhile; ?>
        <?php else: ?>
            <!-- Fallback Slide -->
            <div class="hero-slide active">
                <div class="hero-content">
                    <h1>Welcome to Deeken</h1>
                    <p>Discover amazing products at unbeatable prices.</p>
                    <a href="#products" class="hero-cta">Shop Now <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="hero-image">
                    <img src="https://via.placeholder.com/400x300/667eea/ffffff?text=Welcome" alt="Welcome">
                </div>
            </div>
        <?php endif; ?>
        
        <button class="carousel-arrow prev" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="carousel-arrow next" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </button>
        
        <div class="carousel-nav">
            <?php 
            $carousel_result->data_seek(0);
            $count = $carousel_result->num_rows > 0 ? $carousel_result->num_rows : 1;
            for ($i = 1; $i <= $count; $i++): ?>
                <span class="nav-dot <?php echo $i === 1 ? 'active' : ''; ?>" onclick="currentSlide(<?php echo $i; ?>)"></span>
            <?php endfor; ?>
        </div>
    </div>

    <!-- ----- CATEGORY CAROUSEL ----- -->
    <div class="category-carousel">
        <div class="section-header">
            <h2 class="section-title">Shop by Category</h2>
        </div>
        <div class="category-carousel-track">
            <?php 
            $category_icons = [
                'Electronics' => 'fas fa-laptop',
                'Fashion' => 'fas fa-tshirt',
                'Home & Garden' => 'fas fa-home',
                'Sports' => 'fas fa-football-ball',
                'Books' => 'fas fa-book',
                'Beauty' => 'fas fa-spa',
                'Toys' => 'fas fa-gamepad',
                'Food' => 'fas fa-utensils'
            ];
            
            $categories_result->data_seek(0);
            while ($category = $categories_result->fetch_assoc()): 
                $icon = $category_icons[$category['name']] ?? 'fas fa-box';
            ?>
                <a href="?category_id=<?php echo $category['id']; ?>" class="category-card">
                    <i class="<?php echo $icon; ?>"></i>
                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- ----- FEATURED PRODUCTS CAROUSEL ----- -->
    <?php if ($featured_result && $featured_result->num_rows > 0): ?>
    <div class="product-carousel-section">
        <div class="section-header">
            <h2 class="section-title">Featured Products</h2>
            <div class="carousel-controls">
                <button class="carousel-btn" onclick="scrollCarousel('featured', -1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn btn-secondary" onclick="scrollCarousel('featured', 1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="product-carousel" id="featuredCarousel">
            <div class="product-carousel-track">
                <?php while ($product = $featured_result->fetch_assoc()): 
                    $stock = $conn->query("SELECT stock_quantity FROM inventory WHERE product_id = {$product['id']}")->fetch_assoc()['stock_quantity'] ?? 0;
                ?>
                    <div class="carousel-product-card">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <div class="carousel-product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="rating"><?php echo displayStars($product['rating']); ?></div>
                            <div class="stock-info">In Stock: <?php echo $stock; ?></div>
                            <div class="carousel-product-actions">
                                <?php if ($stock > 0): ?>
                                    <form method="POST" action="cart.php" style="flex: 1;" class="cart-form" data-product-id="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="button" onclick="checkAddToCart(<?php echo $product['id']; ?>)" class="add-to-cart-btn">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="add-to-cart-btn out-of-stock" disabled>
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                    <div class="out-of-stock">Out of Stock</div>
                                <?php endif; ?>
                                <button onclick="showProductDetails(<?php echo $product['id']; ?>)" class="view-details-btn">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ----- TRENDING PRODUCTS CAROUSEL ----- -->
    <?php if ($trending_result && $trending_result->num_rows > 0): ?>
    <div class="product-carousel-section">
        <div class="section-header">
            <h2 class="section-title">🔥 Trending Now</h2>
            <div class="carousel-controls">
                <button class="carousel-btn" onclick="scrollCarousel('trending', -1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-btn" onclick="scrollCarousel('trending', 1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="product-carousel" id="trendingCarousel">
            <div class="product-carousel-track">
                <?php while ($product = $trending_result->fetch_assoc()): 
                    $stock = $conn->query("SELECT stock_quantity FROM inventory WHERE product_id = {$product['id']}")->fetch_assoc()['stock_quantity'] ?? 0;
                ?>
                    <div class="carousel-product-card">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                        </a>
                        <div class="carousel-product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="rating"><?php echo displayStars($product['rating']); ?></div>
                            <div class="stock-info">In Stock: <?php echo $stock; ?></div>
                            <div class="carousel-product-actions">
                                <?php if ($stock > 0): ?>
                                    <form method="POST" action="cart.php" style="flex: 1;" class="cart-form" data-product-id="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="button" onclick="checkAddToCart(<?php echo $product['id']; ?>)" class="add-to-cart-btn">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="add-to-cart-btn out-of-stock" disabled>
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                    <div class="out-of-stock">Out of Stock</div>
                                <?php endif; ?>
                                <button onclick="showProductDetails(<?php echo $product['id']; ?>)" class="view-details-btn">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ----- NEW ARRIVALS CAROUSEL ----- -->
    <?php if ($new_arrivals_result && $new_arrivals_result->num_rows > 0): ?>
    <div class="product-carousel-section" id="new-arrivals">
        <div class="section-header">
            <h2 class="section-title">✨ New Arrivals</h2>
            <div class="carousel-controls">
                <button class="carousel-btn" onclick="scrollCarousel('newArrivals', -1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-btn" onclick="scrollCarousel('newArrivals', 1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="product-carousel" id="newArrivalsCarousel">
            <div class="product-carousel-track">
                <?php while ($product = $new_arrivals_result->fetch_assoc()): 
                    $stock = $conn->query("SELECT stock_quantity FROM inventory WHERE product_id = {$product['id']}")->fetch_assoc()['stock_quantity'] ?? 0;
                ?>
                    <div class="carousel-product-card">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <div class="carousel-product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="rating"><?php echo displayStars($product['rating']); ?></div>
                            <div class="stock-info">In Stock: <?php echo $stock; ?></div>
                            <div class="carousel-product-actions">
                                <?php if ($stock > 0): ?>
                                    <form method="POST" action="cart.php" style="flex: 1;" class="cart-form" data-product-id="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="button" onclick="checkAddToCart(<?php echo $product['id']; ?>)" class="add-to-cart-btn">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="add-to-cart-btn out-of-stock" disabled>
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                    <div class="out-of-stock">Out of Stock</div>
                                <?php endif; ?>
                                <button onclick="showProductDetails(<?php echo $product['id']; ?>)" class="view-details-btn">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ----- MAIN CONTENT WITH FILTERS AND PRODUCTS ----- -->
    <div class="main-content">
        <div class="content-wrapper" id="products">
            <!-- ----- FILTERS ----- -->
            <div class="filter-section">
                <h3>Filter Products</h3>
                <div class="category-dropdown">
                    <button class="category-toggle" id="categoryToggle">All Categories <span></span></button>
                    <ul class="category-list" id="categoryList">
                        <li><a href="?category_id=" class="<?php echo $category_id === '' ? 'active' : ''; ?>">All Categories</a></li>
                        <?php $categories_result->data_seek(0); while ($cat = $categories_result->fetch_assoc()): ?>
                            <li><a href="?category_id=<?php echo $cat['id']; ?>" class="<?php echo $category_id == $cat['id'] ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a></li>
                        <?php endwhile; $categories_result->data_seek(0); ?>
                    </ul>
                </div>
                <select id="sortFilter" onchange="searchProducts()">
                    <option value="">Sort by Relevance</option>
                    <option value="price-asc" <?php echo $sort === 'price-asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price-desc" <?php echo $sort === 'price-desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="rating-desc" <?php echo $sort === 'rating-desc' ? 'selected' : ''; ?>>Rating</option>
                </select>
            </div>

            <!-- ----- PRODUCT GRID ----- -->
            <div class="product-grid" id="productGrid">
                <?php if ($products_result->num_rows > 0): ?>
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                        <div class="product-card">
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p>$<?php echo number_format($product['price'], 2); ?></p>
                                <p><?php echo displayStars($product['rating']); ?></p>
                                <p class="stock-info">In Stock: <?php echo $product['stock_quantity']; ?></p>
                            </a>
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <form method="POST" action="cart.php" class="cart-form" data-product-id="<?php echo $product['id']; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="button" onclick="checkAddToCart(<?php echo $product['id']; ?>)" class="add-to-cart-btn">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="add-to-cart-btn out-of-stock" disabled>
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                                <div class="out-of-stock">Out of Stock</div>
                            <?php endif; ?>
                          
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Products Found</h3>
                        <p>Try adjusting your filters or search terms.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ----- PRODUCT MODAL ----- -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <span class="close-modal"><i class="fas fa-times"></i></span>
            <img id="modalImage" src="" alt="">
            <div class="modal-info">
                <h1 id="modalName"></h1>
                <p id="modalPrice"></p>
                <p id="modalRating"></p>
                <p id="modalDescription"></p>
                <a href="product.php"><button><i class="fas fa-cart-plus"></i> See More</button></a>
            </div>
        </div>
    </div>

    <!-- ----- FOOTER ----- -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Deeken</h3>
                <ul>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="careers.php">Careers</a></li>
                    <li><a href="press.php">Press</a></li>
                    <li><a href="sustainability.php">Sustainability</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul>
                    <li><a href="help.php">Help Center</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="returns.php">Returns & Refunds</a></li>
                    <li><a href="shipping.php">Shipping Info</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>My Account</h3>
                <ul>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="orders.php">Order History</a></li>
                    <li><a href="wishlist.php">Wishlist</a></li>
                    <li><a href="addresses.php">Address Book</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Connect With Us</h3>
                <div class="social-links">
                    <a href="https://www.facebook.com/deeken"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.twitter.com/deeken"><i class="fab fa-twitter"></i></a>
                    <a href="https://www.instagram.com/deeken"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.linkedin.com/company/deeken"><i class="fab fa-linkedin-in"></i></a>
                </div>
                <div class="contact-info">
                    <p><i class="fas fa-envelope"></i> support@deeken.com</p>
                    <p><i class="fas fa-phone"></i> +1-800-123-4567</p>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Commerce St, City, Country</p>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p>© <?php echo date('Y'); ?> Deeken. All rights reserved.</p>
                <div class="footer-links">
                    <a href="terms.php">Terms of Service</a>
                    <a href="privacy.php">Privacy Policy</a>
                    <a href="cookies.php">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ----- JAVASCRIPT ----- -->
    <script src="utils.js"></script>
    <script>
        // Enhanced Product Carousel with Auto-Scroll
        class ProductCarousel {
            constructor(carouselId, autoScrollInterval = 3000) {
                this.carouselId = carouselId;
                this.carousel = document.getElementById(carouselId + 'Carousel');
                this.track = this.carousel?.querySelector('.product-carousel-track');
                this.cards = this.track?.children || [];
                this.position = 0;
                this.cardWidth = 280 + 24; // card width + gap
                this.autoScrollInterval = autoScrollInterval;
                this.isAutoScrolling = true;
                this.autoScrollTimer = null;
                
                if (this.carousel && this.track && this.cards.length > 0) {
                    this.init();
                }
            }
            
            init() {
                // Start auto-scroll
                this.startAutoScroll();
                
                // Pause auto-scroll on hover
                this.carousel.addEventListener('mouseenter', () => {
                    this.pauseAutoScroll();
                });
                
                // Resume auto-scroll when mouse leaves
                this.carousel.addEventListener('mouseleave', () => {
                    this.startAutoScroll();
                });
                
                // Handle visibility change (pause when tab is not active)
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.pauseAutoScroll();
                    } else if (this.isAutoScrolling) {
                        this.startAutoScroll();
                    }
                });
            }
            
            scrollToNext() {
                const containerWidth = this.carousel.offsetWidth;
                const totalWidth = this.cards.length * this.cardWidth;
                const maxScroll = Math.max(0, totalWidth - containerWidth);
                
                // Move to next position
                this.position += this.cardWidth;
                
                // If we've reached the end, reset to beginning with smooth transition
                if (this.position >= maxScroll) {
                    this.position = 0;
                }
                
                this.updatePosition();
            }
            
            scrollToPrev() {
                // Move to previous position
                this.position -= this.cardWidth;
                
                // If we've reached the beginning, go to end
                if (this.position < 0) {
                    const containerWidth = this.carousel.offsetWidth;
                    const totalWidth = this.cards.length * this.cardWidth;
                    const maxScroll = Math.max(0, totalWidth - containerWidth);
                    this.position = maxScroll;
                }
                
                this.updatePosition();
            }
            
            updatePosition() {
                if (this.track) {
                    this.track.style.transform = `translateX(-${this.position}px)`;
                }
            }
            
            startAutoScroll() {
                if (!this.isAutoScrolling || this.cards.length <= 3) return;
                
                this.pauseAutoScroll(); // Clear any existing timer
                this.autoScrollTimer = setInterval(() => {
                    this.scrollToNext();
                }, this.autoScrollInterval);
            }
            
            pauseAutoScroll() {
                if (this.autoScrollTimer) {
                    clearInterval(this.autoScrollTimer);
                    this.autoScrollTimer = null;
                }
            }
            
            toggleAutoScroll() {
                this.isAutoScrolling = !this.isAutoScrolling;
                if (this.isAutoScrolling) {
                    this.startAutoScroll();
                } else {
                    this.pauseAutoScroll();
                }
            }
            
            // Manual scroll method for buttons
            manualScroll(direction) {
                this.pauseAutoScroll();
                
                if (direction > 0) {
                    this.scrollToNext();
                } else {
                    this.scrollToPrev();
                }
                
                // Resume auto-scroll after a delay
                setTimeout(() => {
                    if (this.isAutoScrolling) {
                        this.startAutoScroll();
                    }
                }, 3000);
            }
        }

        // Handle navbar scroll behavior
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');

        window.addEventListener('scroll', function() {
            let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            
            if (currentScroll > lastScrollTop && currentScroll > 100) {
                // Scrolling down & past 100px
                navbar.classList.add('hidden');
            } else if (currentScroll <= 100) {
                // At or near top
                navbar.classList.remove('hidden');
            }
            
            lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
        });

        // Toggle category dropdown
        function toggleCategoryDropdown() {
            const categoryToggle = document.getElementById('categoryToggle');
            const categoryList = document.getElementById('categoryList');
            
            if (categoryToggle && categoryList) {
                categoryToggle.classList.toggle('active');
                categoryList.classList.toggle('show');
            }
        }

        // Event listener for category toggle
        document.addEventListener('DOMContentLoaded', function() {
            const categoryToggle = document.getElementById('categoryToggle');
            if (categoryToggle) {
                categoryToggle.addEventListener('click', toggleCategoryDropdown);
            }

            // Close category dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const categoryDropdown = document.querySelector('.category-dropdown');
                const categoryList = document.getElementById('categoryList');
                if (categoryDropdown && categoryList && !categoryDropdown.contains(event.target)) {
                    categoryToggle.classList.remove('active');
                    categoryList.classList.remove('show');
                }
            });
        });

        // Hero Carousel
        let currentSlideIndex = 0;
        const slides = document.querySelectorAll('.hero-slide');
        const dots = document.querySelectorAll('.nav-dot');
        
        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.remove('active', 'prev');
                dots[i].classList.remove('active');
                
                if (i === index) {
                    slide.classList.add('active');
                    dots[i].classList.add('active');
                } else if (i < index) {
                    slide.classList.add('prev');
                }
            });
        }
        
        function changeSlide(direction) {
            currentSlideIndex += direction;
            if (currentSlideIndex >= slides.length) currentSlideIndex = 0;
            if (currentSlideIndex < 0) currentSlideIndex = slides.length - 1;
            showSlide(currentSlideIndex);
        }
        
        function currentSlide(index) {
            currentSlideIndex = index - 1;
            showSlide(currentSlideIndex);
        }
        
        // Auto-advance hero slides
        setInterval(() => {
            changeSlide(1);
        }, 5000);

        // Initialize product carousels when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize auto-scrolling carousels with different intervals
            const featuredCarousel = new ProductCarousel('featured', 4000); // 4 seconds
            const trendingCarousel = new ProductCarousel('trending', 3500); // 3.5 seconds
            const newArrivalsCarousel = new ProductCarousel('newArrivals', 4500); // 4.5 seconds
            
            // Store carousel instances globally for manual control
            window.productCarousels = {
                featured: featuredCarousel,
                trending: trendingCarousel,
                newArrivals: newArrivalsCarousel
            };

            // Add auto-scroll indicators
            setTimeout(() => {
                addAutoScrollIndicators();
            }, 500);
        });

        // Enhanced manual scroll function for buttons
        function scrollCarousel(carouselId, direction) {
            if (window.productCarousels && window.productCarousels[carouselId]) {
                window.productCarousels[carouselId].manualScroll(direction);
            }
        }

        // Add auto-scroll indicators to carousels
        function addAutoScrollIndicators() {
            const carousels = ['featured', 'trending', 'newArrivals'];
            
            carousels.forEach(carouselId => {
                const carousel = document.getElementById(carouselId + 'Carousel');
                if (carousel && carousel.querySelector('.product-carousel-track').children.length > 3) {
                    const indicator = document.createElement('div');
                    indicator.className = 'carousel-auto-indicator';
                    indicator.innerHTML = '<i class="fas fa-play"></i> Auto-scroll';
                    indicator.id = carouselId + 'Indicator';
                    carousel.style.position = 'relative';
                    carousel.appendChild(indicator);
                    
                    // Add click handler to toggle auto-scroll
                    indicator.addEventListener('click', () => {
                        toggleCarouselAutoScroll(carouselId);
                    });
                }
            });
        }

        // Add pause/play functionality
        function toggleCarouselAutoScroll(carouselId) {
            if (window.productCarousels && window.productCarousels[carouselId]) {
                const carousel = window.productCarousels[carouselId];
                const indicator = document.getElementById(carouselId + 'Indicator');
                
                carousel.toggleAutoScroll();
                
                if (indicator) {
                    if (carousel.isAutoScrolling) {
                        indicator.innerHTML = '<i class="fas fa-play"></i> Auto-scroll';
                        indicator.classList.remove('paused');
                    } else {
                        indicator.innerHTML = '<i class="fas fa-pause"></i> Paused';
                        indicator.classList.add('paused');
                    }
                }
            }
        }

        // Search products with filters
        function searchProducts() {
            const search = document.getElementById('searchInput').value;
            const category_id = document.querySelector('.category-list a.active')?.href.match(/category_id=(\d+)/)?.[1] || '';
            const sort = document.getElementById('sortFilter').value;
            window.location.href = `index.php?search=${encodeURIComponent(search)}&category_id=${encodeURIComponent(category_id)}&sort=${encodeURIComponent(sort)}`;
        }

        // Autocomplete functionality
        const searchInput = document.getElementById('searchInput');
        const autocompleteSuggestions = document.getElementById('autocompleteSuggestions');

        if (searchInput && autocompleteSuggestions) {
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                if (query.length < 2) {
                    autocompleteSuggestions.innerHTML = '';
                    autocompleteSuggestions.style.display = 'none';
                    return;
                }

                fetch(`autocomplete.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        autocompleteSuggestions.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(item => {
                                const li = document.createElement('li');
                                li.textContent = item.name;
                                li.addEventListener('click', () => {
                                    searchInput.value = item.name;
                                    autocompleteSuggestions.innerHTML = '';
                                    autocompleteSuggestions.style.display = 'none';
                                    searchProducts();
                                });
                                autocompleteSuggestions.appendChild(li);
                            });
                            autocompleteSuggestions.style.display = 'block';
                        } else {
                            autocompleteSuggestions.style.display = 'none';
                        }
                    })
                    .catch(error => console.error('Error fetching autocomplete:', error));
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(event) {
                if (!searchInput.contains(event.target) && !autocompleteSuggestions.contains(event.target)) {
                    autocompleteSuggestions.innerHTML = '';
                    autocompleteSuggestions.style.display = 'none';
                }
            });
        }

        // Check if product is in cart before adding
        let currentProductId = null;

        function checkAddToCart(productId) {
            <?php if ($user): ?>
                currentProductId = productId;
                
                // First, check stock
                fetch('check_stock.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}`
                })
                .then(response => response.json())
                .then(stockData => {
                    if (stockData.stock <= 0) {
                        alert('This product is out of stock.');
                        return;
                    }
                    
                    // Then check cart
                    fetch('check_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&user_id=<?php echo $user['id']; ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error:', data.error);
                            submitCartForm(productId);
                            return;
                        }
                        if (data.exists) {
                            showCartPopup();
                        } else {
                            submitCartForm(productId);
                        }
                    })
                    .catch(error => {
                        console.error('Error checking cart:', error);
                        submitCartForm(productId);
                    });
                })
                .catch(error => console.error('Error checking stock:', error));
            <?php else: ?>
                window.location.href = 'login.php';
            <?php endif; ?>
        }

        function showCartPopup() {
            const popup = document.getElementById('cartPopup');
            const overlay = document.getElementById('cartOverlay');
            if (popup && overlay) {
                popup.classList.add('show');
                overlay.classList.add('show');
            }
        }

        function closeCartPopup() {
            const popup = document.getElementById('cartPopup');
            const overlay = document.getElementById('cartOverlay');
            if (popup && overlay) {
                popup.classList.remove('show');
                overlay.classList.remove('show');
            }
            currentProductId = null;
        }

        function confirmAddToCart() {
            if (currentProductId) {
                submitCartForm(currentProductId);
            }
            closeCartPopup();
        }

        function submitCartForm(productId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'cart.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'product_id';
            input.value = productId;
            
            const addToCartInput = document.createElement('input');
            addToCartInput.type = 'hidden';
            addToCartInput.name = 'add_to_cart';
            addToCartInput.value = '1';
            
            form.appendChild(input);
            form.appendChild(addToCartInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Show product details in modal
        function showProductDetails(id) {
            fetch(`product.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const productDetails = doc.querySelector('.product-details');
                    if (productDetails) {
                        const modal = document.getElementById('productModal');
                        if (modal) {
                            modal.querySelector('.modal-content').innerHTML = `
                                <span class="close-modal"><i class="fas fa-times"></i></span>
                                ${productDetails.innerHTML}
                            `;
                            modal.classList.add('show');
                            modal.querySelector('.close-modal').addEventListener('click', () => modal.classList.remove('show'));
                        }
                    }
                })
                .catch(error => console.error('Error fetching product details:', error));
        }
        
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

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                }
            });
        }, observerOptions);

        // Observe carousel sections
        document.querySelectorAll('.product-carousel-section, .category-carousel').forEach(section => {
            observer.observe(section);
        });

        // Add enhanced CSS styles
        const enhancedStyles = document.createElement('style');
        enhancedStyles.textContent = `
            .product-carousel-track {
                transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            }
            
            .product-carousel:hover .product-carousel-track {
                transition-duration: 0.3s;
            }
            
            /* Auto-scroll indicator */
            .carousel-auto-indicator {
                position: absolute;
                top: 10px;
                right: 10px;
                background: rgba(0, 0, 0, 0.7);
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 12px;
                opacity: 0;
                transition: opacity 0.3s ease;
                cursor: pointer;
                z-index: 10;
            }
            
            .product-carousel:hover .carousel-auto-indicator {
                opacity: 1;
            }
            
            .carousel-auto-indicator.paused {
                background: rgba(255, 193, 7, 0.9);
                color: #333;
            }
            
            .carousel-auto-indicator:hover {
                transform: scale(1.05);
            }
            
            /* Enhanced carousel controls */
            .carousel-controls {
                gap: 15px;
            }
            
            .carousel-btn {
                position: relative;
                overflow: hidden;
            }
            
            .carousel-btn:hover::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
                transform: translateX(-100%);
                animation: shimmer 0.6s ease-out;
            }
            
            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }
            
            /* Progressive loading effect */
            .carousel-product-card {
                position: relative;
                overflow: hidden;
            }
            
            .carousel-product-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
                transition: left 0.5s;
                z-index: 1;
                pointer-events: none;
            }
            
            .carousel-product-card:hover::before {
                left: 100%;
            }

            /* Mobile responsive */
            @media (max-width: 768px) {
                .product-carousel-track {
                    gap: 1rem;
                }
                
                .carousel-product-card {
                    min-width: 250px;
                }
                
                .carousel-auto-indicator {
                    font-size: 10px;
                    padding: 3px 8px;
                }
            @media (products) {
                .product-card {
                    width: calc(100% - 30px);
                }
                
                .carousel-product-card {
                    min-width: calc(100% - 50px);
                }
                
                .product-section {
                    margin: 2rem 0;
                }
                
                .section-header {
                    flex-direction: column;
                    gap: 1rem;
                    align-items: flex-start;
                }
                
                .controls-product-controls {
                    align-self: flex-end;
                }
            }
        `;
        document.head.appendChild(enhancedStyles);
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>