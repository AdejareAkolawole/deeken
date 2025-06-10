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

// ----- FETCH CATEGORIES -----
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);

// ----- FETCH FEATURED PRODUCTS FOR CAROUSEL -----
$featured_query = "SELECT * FROM products WHERE featured = 1 ORDER BY RAND() LIMIT 8";
$featured_result = $conn->query($featured_query);

// ----- FETCH TRENDING PRODUCTS -----
$trending_query = "SELECT * FROM products ORDER BY rating DESC, created_at DESC LIMIT 12";
$trending_result = $conn->query($trending_query);

// ----- FETCH NEW ARRIVALS -----
$new_arrivals_query = "SELECT * FROM products ORDER BY created_at DESC LIMIT 12";
$new_arrivals_result = $conn->query($new_arrivals_query);

// ----- PRODUCT QUERY -----
$products_query = "SELECT p.* FROM products p JOIN categories c ON p.category_id = c.id";
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
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="hamburger.css">
    <link rel="stylesheet" href="global.css">
    <style>
        .star-filled { color: #f1c40f; }
        .star-empty { color: #ccc; }
        .star-filled, .star-empty { font-size: 14px; margin-right: 2px; }
        
        /* Autocomplete styles */
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 5px;
            list-style: none;
            padding: 0;
        }
        .autocomplete-suggestions li {
            padding: 10px 15px;
            cursor: pointer;
            font-size: 14px;
            color: #333;
            transition: background 0.2s;
        }
        .autocomplete-suggestions li:hover {
            background: #f0f0f0;
        }

        /* HERO CAROUSEL STYLES */
        .hero-carousel {
            position: relative;
            width: 100%;
            height: 500px;
            overflow: hidden;
            margin-bottom: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(100%);
        }

        .hero-slide.active {
            opacity: 1;
            transform: translateX(0);
        }

        .hero-slide.prev {
            transform: translateX(-100%);
        }

        .hero-slide {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5%;
            color: white;
        }

        .hero-slide:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .hero-slide:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .hero-slide:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .hero-content {
            flex: 1;
            max-width: 50%;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
            animation: slideInLeft 1s ease-out;
        }

        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: slideInLeft 1s ease-out 0.2s both;
        }

        .hero-cta {
            display: inline-block;
            padding: 15px 30px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            animation: slideInLeft 1s ease-out 0.4s both;
        }

        .hero-cta:hover {
            background: white;
            color: #333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .hero-image {
            flex: 1;
            text-align: center;
            animation: slideInRight 1s ease-out 0.3s both;
        }

        .hero-image img {
            max-width: 100%;
            max-height: 350px;
            object-fit: contain;
        }

        /* Carousel Navigation */
        .carousel-nav {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
        }

        .nav-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-dot.active {
            background: white;
            transform: scale(1.2);
        }

        .carousel-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .carousel-arrow:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-50%) scale(1.1);
        }

        .carousel-arrow.prev {
            left: 20px;
        }

        .carousel-arrow.next {
            right: 20px;
        }

        /* PRODUCT CAROUSEL STYLES */
        .product-carousel-section {
            margin: 4rem 0;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 2px;
        }

        .carousel-controls {
            display: flex;
            gap: 10px;
        }

        .carousel-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: #f8f9fa;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-btn:hover {
            background: #667eea;
            color: white;
            transform: scale(1.1);
        }

        .product-carousel {
            position: relative;
            overflow: hidden;
            padding: 0 1rem;
        }

        .product-carousel-track {
            display: flex;
            transition: transform 0.5s ease;
            gap: 1.5rem;
        }

        .carousel-product-card {
            min-width: 280px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .carousel-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .carousel-product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .carousel-product-card:hover img {
            transform: scale(1.05);
        }

        .carousel-product-info {
            padding: 1.5rem;
        }

        .carousel-product-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .carousel-product-info .price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .carousel-product-info .rating {
            margin-bottom: 1rem;
        }

        .carousel-product-actions {
            display: flex;
            gap: 0.5rem;
        }

        .carousel-product-actions button {
            flex: 1;
            padding: 0.7rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .add-to-cart-btn {
            background: #667eea;
            color: white;
        }

        .add-to-cart-btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        .view-details-btn {
            background: #f8f9fa;
            color: #333;
        }

        .view-details-btn:hover {
            background: #e9ecef;
        }

        /* CATEGORY CAROUSEL */
        .category-carousel {
            margin: 3rem 0;
            padding: 2rem 1rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
        }

        .category-carousel-track {
            display: flex;
            gap: 2rem;
            overflow-x: auto;
            padding: 1rem 0;
            scroll-behavior: smooth;
        }

        .category-carousel-track::-webkit-scrollbar {
            height: 6px;
        }

        .category-carousel-track::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
        }

        .category-carousel-track::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }

        .category-card {
            min-width: 150px;
            height: 150px;
            background: white;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .category-card:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            color: #667eea;
        }

        .category-card i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #667eea;
        }

        .category-card span {
            font-weight: 600;
            text-align: center;
        }

        /* Animations */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-carousel {
                height: 400px;
            }

            .hero-slide {
                flex-direction: column;
                text-align: center;
                padding: 2rem;
            }

            .hero-content {
                max-width: 100%;
                margin-bottom: 2rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .carousel-product-card {
                min-width: 250px;
            }

            .category-card {
                min-width: 120px;
                height: 120px;
            }

            .category-card i {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .carousel-product-card {
                min-width: 220px;
            }
        }
    </style>
</head>
<body>
    <!-- ----- MARQUEE ----- -->
    <div class="marquee">
        <span>🎉 New Arrival Up - Shop the Latest Products Now! Free Shipping on Orders Over $50! 🚚</span>
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
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
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
        <div class="hero-slide active">
            <div class="hero-content">
                <h1>Summer Sale</h1>
                <p>Up to 70% off on selected items. Don't miss out on our biggest sale of the year!</p>
                <a href="#products" class="hero-cta">Shop Now <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="hero-image">
                <img src="https://via.placeholder.com/400x300/667eea/ffffff?text=Summer+Sale" alt="Summer Sale">
            </div>
        </div>
        
        <div class="hero-slide">
            <div class="hero-content">
                <h1>New Arrivals</h1>
                <p>Discover the latest trends and must-have items for this season.</p>
                <a href="#new-arrivals" class="hero-cta">Explore <i class="fas fa-star"></i></a>
            </div>
            <div class="hero-image">
                <img src="https://via.placeholder.com/400x300/f093fb/ffffff?text=New+Arrivals" alt="New Arrivals">
            </div>
        </div>
        
        <div class="hero-slide">
            <div class="hero-content">
                <h1>Free Shipping</h1>
                <p>Get free shipping on all orders over $50. Fast and reliable delivery.</p>
                <a href="#products" class="hero-cta">Start Shopping <i class="fas fa-truck"></i></a>
            </div>
            <div class="hero-image">
                <img src="https://via.placeholder.com/400x300/4facfe/ffffff?text=Free+Shipping" alt="Free Shipping">
            </div>
        </div>
        
        <div class="hero-slide">
            <div class="hero-content">
                <h1>Premium Quality</h1>
                <p>Only the best products from trusted brands. Quality guaranteed.</p>
                <a href="#products" class="hero-cta">View Products <i class="fas fa-gem"></i></a>
            </div>
            <div class="hero-image">
                <img src="https://via.placeholder.com/400x300/43e97b/ffffff?text=Premium+Quality" alt="Premium Quality">
            </div>
        </div>
        
        <button class="carousel-arrow prev" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="carousel-arrow next" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </button>
        
        <div class="carousel-nav">
            <span class="nav-dot active" onclick="currentSlide(1)"></span>
            <span class="nav-dot" onclick="currentSlide(2)"></span>
            <span class="nav-dot" onclick="currentSlide(3)"></span>
            <span class="nav-dot" onclick="currentSlide(4)"></span>
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
                <button class="carousel-btn" onclick="scrollCarousel('featured', 1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="product-carousel" id="featuredCarousel">
            <div class="product-carousel-track">
                <?php while ($product = $featured_result->fetch_assoc()): ?>
                    <div class="carousel-product-card">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <div class="carousel-product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="rating"><?php echo displayStars($product['rating']); ?></div>
                            <div class="carousel-product-actions">
                                <form method="POST" action="cart.php" style="flex: 1;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </form>
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
                <?php while ($product = $trending_result->fetch_assoc()): ?>
                    <div class="carousel-product-card">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <div class="carousel-product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="rating"><?php echo displayStars($product['rating']); ?></div>
                            <div class="carousel-product-actions">
                                <form method="POST" action="cart.php" style="flex: 1;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </form>
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
                <?php while ($product = $new_arrivals_result->fetch_assoc()): ?>
                    <div class="carousel-product-card">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <div class="carousel-product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="rating"><?php echo displayStars($product['rating']); ?></div>
                            <div class="carousel-product-actions">
                                <form method="POST" action="cart.php" style="flex: 1;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </form>
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

    <!-- ----- FILTERS ----- -->
    <div class="filters" id="products">
        <select id="categoryFilter" onchange="searchProducts()">
            <option value="">All Categories</option>
            <?php $categories_result->data_seek(0); while ($cat = $categories_result->fetch_assoc()): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
            <?php endwhile; $categories_result->data_seek(0); ?>
        </select>
        <select id="sortFilter" onchange="searchProducts()">
            <option value="">Relevance</option>
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
                    </a>
                    <form method="POST" action="cart.php">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" name="add_to_cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                    </form>
                    <button onclick="showProductDetails(<?php echo $product['id']; ?>)"><i class="fas fa-eye"></i> View Details</button>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No products found.</p>
        <?php endif; ?>
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
                    <a href="https://www.facebook.com/deekenmarket" target="_blank" aria-label="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://x.com/deekenmarket" target="_blank" aria-label="Twitter">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                    <a href="https://www.instagram.com/deekenmarket" target="_blank" aria-label="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://www.youtube.com/deekenmarket" target="_blank" aria-label="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
                <div class="contact-info">
                    <p><i class="fas fa-phone"></i> +1 (234) 567-8900</p>
                    <p><i class="fas fa-envelope"></i> support@deeken.com</p>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p>© 2025 Deeken. All rights reserved.</p>
                <div class="footer-links">
                    <a href="privacy.php">Privacy Policy</a>
                    <a href="terms.php">Terms of Service</a>
                    <a href="cookies.php">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ----- JAVASCRIPT ----- -->
    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
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
            const category_id = document.getElementById('categoryFilter').value;
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
                transition: left 0.5s ease;
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
            }
            
            @media (max-width: 480px) {
                .carousel-product-card {
                    min-width: 220px;
                }
                
                .product-carousel-section {
                    margin: 2rem 0;
                }
                
                .section-header {
                    flex-direction: column;
                    gap: 1rem;
                    align-items: flex-start;
                }
                
                .carousel-controls {
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