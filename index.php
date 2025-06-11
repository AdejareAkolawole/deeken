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
 
</head>
<body>
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
                            </a>
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" name="add_to_cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                            </form>
                            <button onclick="showProductDetails(<?php echo $product['id']; ?>)"><i class="fas fa-eye"></i> View Details</button>
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
                <p>&copy; <?php echo date('Y'); ?> Deeken. All rights reserved.</p>
                <div class="footer-links">
                    <a href="terms.php">Terms of Service</a>
                    <a href="privacy.php">Privacy Policy</a>
                    <a href="cookies.php">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ----- JAVASCRIPT ----- -->
    <script>
        // ----- PROFILE DROPDOWN -----
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.querySelector('.profile-trigger');
            if (!dropdown.contains(event.target) && !trigger.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // ----- HERO CAROUSEL -----
        let currentSlideIndex = 1;
        let autoSlideInterval;

        function changeSlide(direction) {
            const slides = document.querySelectorAll('.hero-slide');
            const dots = document.querySelectorAll('.nav-dot');
            
            slides[currentSlideIndex - 1].classList.remove('active');
            slides[currentSlideIndex - 1].classList.add('prev');
            dots[currentSlideIndex - 1].classList.remove('active');

            currentSlideIndex = (currentSlideIndex + direction - 1 + slides.length) % slides.length + 1;

            slides[currentSlideIndex - 1].classList.remove('prev');
            slides[currentSlideIndex - 1].classList.add('active');
            dots[currentSlideIndex - 1].classList.add('active');

            resetAutoSlide();
        }

        function currentSlide(index) {
            const slides = document.querySelectorAll('.hero-slide');
            const dots = document.querySelectorAll('.nav-dot');
            
            slides[currentSlideIndex - 1].classList.remove('active');
            slides[currentSlideIndex - 1].classList.add('prev');
            dots[currentSlideIndex - 1].classList.remove('active');

            currentSlideIndex = index;

            slides[currentSlideIndex - 1].classList.remove('prev');
            slides[currentSlideIndex - 1].classList.add('active');
            dots[currentSlideIndex - 1].classList.add('active');

            resetAutoSlide();
        }

        function autoSlide() {
            changeSlide(1);
        }

        function resetAutoSlide() {
            clearInterval(autoSlideInterval);
            autoSlideInterval = setInterval(autoSlide, 5000);
        }

        // Initialize auto slide
        resetAutoSlide();

        // Pause on hover
        const carousel = document.getElementById('heroCarousel');
        carousel.addEventListener('mouseenter', () => clearInterval(autoSlideInterval));
        carousel.addEventListener('mouseleave', resetAutoSlide);

        // ----- PRODUCT CAROUSEL -----
        function scrollCarousel(carouselId, direction) {
            const carousel = document.getElementById(`${carouselId}Carousel`);
            const track = carousel.querySelector('.product-carousel-track');
            const cards = track.querySelectorAll('.carousel-product-card');
            const cardWidth = cards[0].offsetWidth + 24; // Including gap
            const visibleWidth = carousel.offsetWidth;
            const maxScroll = track.scrollWidth - visibleWidth;
            let currentTransform = parseFloat(getComputedStyle(track).transform.split(',')[4]) || 0;

            let newTransform = currentTransform + (direction * cardWidth * 3);
            newTransform = Math.max(Math.min(newTransform, 0), -maxScroll);

            track.style.transform = `translateX(${newTransform}px)`;
        }

        // Auto-scroll for carousels
        const carousels = ['featured', 'trending', 'newArrivals'];
        const autoScrollIntervals = {};

        carousels.forEach(carouselId => {
            const carousel = document.getElementById(`${carouselId}Carousel`);
            if (carousel) {
                let isAutoScrolling = true;

                function autoScroll() {
                    if (!isAutoScrolling) return;
                    scrollCarousel(carouselId, 1);
                }

                autoScrollIntervals[carouselId] = setInterval(autoScroll, 3000);

                // Add auto-scroll indicator
                const indicator = document.createElement('div');
                indicator.className = 'carousel-auto-indicator';
                indicator.innerHTML = '<i class="fas fa-pause"></i> Pause';
                carousel.appendChild(indicator);

                // Toggle auto-scroll on click
                indicator.addEventListener('click', () => {
                    isAutoScrolling = !isAutoScrolling;
                    indicator.innerHTML = isAutoScrolling 
                        ? '<i class="fas fa-pause"></i> Pause' 
                        : '<i class="fas fa-play"></i> Play';
                    indicator.classList.toggle('paused', !isAutoScrolling);
                });

                // Pause on hover
                carousel.addEventListener('mouseenter', () => {
                    isAutoScrolling = false;
                    indicator.classList.add('paused');
                    indicator.innerHTML = '<i class="fas fa-play"></i> Play';
                });

                carousel.addEventListener('mouseleave', () => {
                    isAutoScrolling = true;
                    indicator.classList.remove('paused');
                    indicator.innerHTML = '<i class="fas fa-pause"></i> Pause';
                });
            }
        });

        // ----- SEARCH FUNCTIONALITY -----
        function searchProducts() {
            const searchInput = document.getElementById('searchInput').value.trim();
            const categoryId = new URLSearchParams(window.location.search).get('category_id') || '';
            const sort = document.getElementById('sortFilter').value;

            let queryParams = [];
            if (searchInput) queryParams.push(`search=${encodeURIComponent(searchInput)}`);
            if (categoryId) queryParams.push(`category_id=${categoryId}`);
            if (sort) queryParams.push(`sort=${sort}`);

            window.location.href = `index.php${queryParams.length ? '?' + queryParams.join('&') : ''}`;
        }

        // ----- AUTOCOMPLETE -----
        const searchInput = document.getElementById('searchInput');
        const autocompleteSuggestions = document.getElementById('autocompleteSuggestions');

        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length < 2) {
                autocompleteSuggestions.style.display = 'none';
                return;
            }

            fetch(`autocomplete.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    autocompleteSuggestions.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(item => {
                            const li = document.createElement('li');
                            li.textContent = item.name;
                            li.addEventListener('click', () => {
                                searchInput.value = item.name;
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

        // Close autocomplete when clicking outside
        document.addEventListener('click', function(event) {
            if (!searchInput.contains(event.target) && !autocompleteSuggestions.contains(event.target)) {
                autocompleteSuggestions.style.display = 'none';
            }
        });

        // Trigger search on Enter key
        searchInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                searchProducts();
            }
        });

        // ----- PRODUCT MODAL -----
        function showProductDetails(productId) {
            fetch(`product_details.php?id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error(data.error);
                        return;
                    }

                    document.getElementById('modalImage').src = data.image;
                    document.getElementById('modalImage').alt = data.name;
                    document.getElementById('modalName').textContent = data.name;
                    document.getElementById('modalPrice').textContent = `$${parseFloat(data.price).toFixed(2)}`;
                    document.getElementById('modalRating').innerHTML = generateStars(data.rating);
                    document.getElementById('modalDescription').textContent = data.description || 'No description available.';
                    document.querySelector('#productModal a').href = `product.php?id=${productId}`;

                    document.getElementById('productModal').classList.add('show');
                })
                .catch(error => console.error('Error fetching product details:', error));
        }

        function generateStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<i class="fas fa-star ${i <= rating ? 'star-filled' : 'star-empty'}"></i>`;
            }
            return stars;
        }

        // Close modal
        document.querySelector('.close-modal').addEventListener('click', function() {
            document.getElementById('productModal').classList.remove('show');
        });

        // Close modal when clicking outside
        document.getElementById('productModal').addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.remove('closed');
            }
        });

        // ----- NAVBAR SCROLL BEHAVIOR -----
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (currentScrollTop > lastScrollTop) {
                // Scrolling down
                navbar.classList.add('hidden');
            } else {
                // Scrolling up
                navbar.classList.remove('hidden');
            }
            lastScrollTop = currentScrollTop <= 0 ? 0 : currentScrollTop; // Prevent negative scroll
        });

        // ----- CATEGORY DROPDOWN -----
        const categoryToggle = document.getElementById('categoryToggle');
        const categoryList = document.getElementById('categoryList');

        if (categoryToggle && categoryList) {
            categoryToggle.addEventListener('click', function() {
                categoryList.classList.toggle('show');
                categoryToggle.classList.toggle('active');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!categoryToggle.contains(event.target) && !categoryList.contains(event.target)) {
                    categoryList.classList.remove('show');
                    categoryToggle.classList.remove('active');
                }
            });

            // Update toggle text when a category is selected
            categoryList.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function() {
                    categoryToggle.childNodes[0].textContent = this.textContent;
                    categoryList.classList.remove('show');
                    categoryToggle.classList.remove('active');
                });
            });
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>