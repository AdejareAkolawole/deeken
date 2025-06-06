<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user
$user = getCurrentUser();

// ----- PRODUCT QUERY -----
$products_query = "SELECT * FROM products";
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$category = isset($_GET['category']) ? sanitize($conn, $_GET['category']) : '';
$sort = isset($_GET['sort']) ? sanitize($conn, $_GET['sort']) : '';
if ($search) {
    $products_query .= " WHERE name LIKE '%$search%'";
}
if ($category) {
    $products_query .= ($search ? " AND" : " WHERE") . " category = '$category'";
}
if ($sort === 'price-asc') {
    $products_query .= " ORDER BY price ASC";
} elseif ($sort === 'price-desc') {
    $products_query .= " ORDER BY price DESC";
} elseif ($sort === 'rating-desc') {
    $products_query .= " ORDER BY rating DESC";
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="hamburger.css">
</head>
<body>
    <!-- ----- MARQUEE ----- -->
    <div class="marquee">
        <span>New Arrival Up - Shop the Latest Products Now!</span>
    </div>

    <!-- ----- NAVIGATION ----- -->
    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
            <button onclick="searchProducts()"><i class="fas fa-search"></i></button>
        </div>
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

    <!-- ----- FILTERS ----- -->
    <div class="filters">
        <select id="categoryFilter" onchange="searchProducts()">
            <option value="">All Categories</option>
            <option value="Clothing" <?php echo $category === 'Clothing' ? 'selected' : ''; ?>>Clothing</option>
            <option value="Footwear" <?php echo $category === 'Footwear' ? 'selected' : ''; ?>>Footwear</option>
            <option value="Accessories" <?php echo $category === 'Accessories' ? 'selected' : ''; ?>>Accessories</option>
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
        <?php while ($product = $products_result->fetch_assoc()): ?>
            <div class="product-card">
                <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link">
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p>$<?php echo number_format($product['price'], 2); ?></p>
                    <p><i class="fas fa-star"></i> <?php echo $product['rating']; ?></p>
                </a>
                <form method="POST" action="cart.php">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <button type="submit" name="add_to_cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                </form>
                <button onclick="showProductDetails(<?php echo $product['id']; ?>)"><i class="fas fa-eye"></i> View Details</button>
            </div>
        <?php endwhile; ?>
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
                <button id="modalAddToCart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
            </div>
        </div>
    </div>

    <!-- ----- CTA SECTION ----- -->
    <section class="cta-section">
        <div class="cta-content">
            <h2>Join Our Community!</h2>
            <p>Follow us for exclusive deals and updates, or call us to shop now!</p>
            <div class="social-media">
                <a href="https://www.instagram.com/deekenmarket" target="_blank" aria-label="Follow us on Instagram"><i class="fab fa-instagram"></i></a>
                <a href="https://x.com/deekenmarket" target="_blank" aria-label="Follow us on X"><i class="fab fa-x-twitter"></i></a>
                <a href="https://www.facebook.com/deekenmarket" target="_blank" aria-label="Follow us on Facebook"><i class="fab fa-facebook-f"></i></a>
            </div>
            <p class="phone-number"><a href="tel:+12345678900" aria-label="Call us at +1 (234) 567-8900">Call us: +1 (234) 567-8900</a></p>
            <a href="login.php" class="cta-button">Shop Now</a>
        </div>
    </section>

    <!-- ----- FOOTER ----- -->
    <footer>
        <p>© 2025 Deeken. All rights reserved.</p>
    </footer>

    <!-- ----- JAVASCRIPT ----- -->
    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
    <script>
        // Search products with filters
        function searchProducts() {
            const search = document.getElementById('searchInput').value;
            const category = document.getElementById('categoryFilter').value;
            const sort = document.getElementById('sortFilter').value;
            window.location.href = `index.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}&sort=${encodeURIComponent(sort)}`;
        }

        // Show product details in modal
        function showProductDetails(id) {
            fetch(`product.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const productDetails = doc.querySelector('.product-details').innerHTML;
                    const modal = document.getElementById('productModal');
                    modal.querySelector('.modal-content').innerHTML = `
                        <span class="close-modal"><i class="fas fa-times"></i></span>
                        ${productDetails}
                    `;
                    modal.classList.add('show');
                    modal.querySelector('.close-modal').addEventListener('click', () => modal.classList.remove('show'));
                });
        }
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>