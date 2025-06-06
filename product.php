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
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and CSS -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Product</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="product.css">
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
    </header>

    <!-- ----- PRODUCT DETAILS ----- -->
    <main>
        <?php if ($product): ?>
            <section class="product-details" id="productDetails">
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p>$<?php echo number_format($product['price'], 2); ?></p>
                    <p><i class="fas fa-star"></i> <?php echo $product['rating']; ?></p>
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
                    $stmt = $conn->prepare("SELECT r.*, u.email FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ?");
                    $stmt->bind_param("i", $product_id);
                    $stmt->execute();
                    $reviews = $stmt->get_result();
                    if ($reviews->num_rows > 0):
                        while ($review = $reviews->fetch_assoc()):
                    ?>
                        <div class="review">
                            <p><strong><?php echo htmlspecialchars($review['email']); ?></strong></p>
                            <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                            <p>Rating: <?php echo $review['rating']; ?></p>
                        </div>
                    <?php endwhile; else: ?>
                        <p>No reviews yet.</p>
                    <?php endif; $stmt->close(); ?>
                </div>
                <?php if ($user): ?>
                    <form id="reviewForm" method="POST">
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                        <textarea name="review_text" placeholder="Write your review..." required></textarea>
                        <input type="number" name="rating" min="1" max="5" placeholder="Rating (1-5)" required>
                        <button type="submit" name="submit_review"><i class="fas fa-paper-plane"></i> Submit Review</button>
                    </form>
                <?php else: ?>
                    <p><a href="login.php">Login</a> to submit a review.</p>
                <?php endif; ?>
            </section>

            <!-- ----- RELATED PRODUCTS ----- -->
            <section class="related-products">
                <h2><i class="fas fa-th"></i> Related Products</h2>
                <div class="product-grid" id="relatedProducts">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM products WHERE category = ? AND id != ? LIMIT 4");
                    $stmt->bind_param("si", $product['category'], $product_id);
                    $stmt->execute();
                    $related = $stmt->get_result();
                    while ($rel = $related->fetch_assoc()):
                    ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($rel['image']); ?>" alt="<?php echo htmlspecialchars($rel['name']); ?>">
                            <h3><?php echo htmlspecialchars($rel['name']); ?></h3>
                            <p>$<?php echo number_format($rel['price'], 2); ?></p>
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="product_id" value="<?php echo $rel['id']; ?>">
                                <button type="submit" name="add_to_cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                            </form>
                        </div>
                    <?php endwhile; $stmt->close(); ?>
                </div>
            </section>
        <?php else: ?>
            <p>Product not found.</p>
        <?php endif; ?>
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