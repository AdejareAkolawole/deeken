<?php
require_once 'config.php';

// Get current user and cart count
$user = getCurrentUser();
$cart_count = getCartCount($conn, $user);

// Get search query and page number
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12; // Products per page
$offset = ($page - 1) * $per_page;

// Initialize products and total count
$products = [];
$total_products = 0;

if ($search_query) {
    // Count total matching products
    $count_query = $conn->prepare("SELECT COUNT(*) as total 
                                   FROM products p 
                                   LEFT JOIN categories c ON p.category_id = c.id 
                                   WHERE p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?");
    $search_term = "%$search_query%";
    $count_query->bind_param("sss", $search_term, $search_term, $search_term);
    $count_query->execute();
    $total_products = $count_query->get_result()->fetch_assoc()['total'];
    $count_query->close();

    // Fetch products with pagination
    $products_query = $conn->prepare("SELECT p.*, c.name as category_name,
                                      COALESCE(AVG(r.rating), 0) AS avg_rating,
                                      COUNT(r.id) AS review_count,
                                      COALESCE(i.stock_quantity, 0) AS stock_quantity
                                      FROM products p 
                                      LEFT JOIN categories c ON p.category_id = c.id 
                                      LEFT JOIN reviews r ON p.id = r.product_id 
                                      LEFT JOIN inventory i ON p.id = i.product_id 
                                      WHERE p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?
                                      GROUP BY p.id 
                                      ORDER BY p.name
                                      LIMIT ? OFFSET ?");
    $products_query->bind_param("sssii", $search_term, $search_term, $search_term, $per_page, $offset);
    $products_query->execute();
    $products_result = $products_query->get_result();
    while ($row = $products_result->fetch_assoc()) {
        $products[] = $row;
    }
    $products_query->close();
}

// Calculate total pages
$total_pages = ceil($total_products / $per_page);

// Handle add to cart
if ($_POST['action'] ?? '' === 'add_to_cart') {
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Please login to add items to cart.']);
        exit;
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id > 0) {
        $product_check = $conn->prepare("SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE p.id = ?");
        $product_check->bind_param("i", $product_id);
        $product_check->execute();
        $product = $product_check->get_result()->fetch_assoc();
        $product_check->close();
        
        if ($product && ($product['stock_quantity'] > 0 || $product['stock_quantity'] === null)) {
            $cart_check = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $cart_check->bind_param("ii", $user['id'], $product_id);
            $cart_check->execute();
            $existing = $cart_check->get_result()->fetch_assoc();
            $cart_check->close();
            
            if ($existing) {
                $new_quantity = $existing['quantity'] + $quantity;
                $update_cart = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
                $update_cart->bind_param("iii", $new_quantity, $user['id'], $product_id);
                $success = $update_cart->execute();
                $update_cart->close();
            } else {
                $add_cart = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
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
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    }
    exit;
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

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Deeken</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 3000;
            opacity: 0;
            transform: translateX(100%);
            transition: opacity 0.3s ease, transform 0.3s ease;
            min-width: 300px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .notification.success {
            background-color: #28a745;
        }
        .notification.error {
            background-color: #dc3545;
        }
        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        /* Login prompt styles */
        .login-prompt {
            background: rgba(0,0,0,0.8);
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

        /* Add to cart button styles */
        .add-to-cart-btn {
            background: rgb(46, 57, 153);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: background-color 0.3s;
            width: 100%;
        }
        .add-to-cart-btn:hover:not(:disabled) {
            background: rgb(78, 90, 197);
        }
        .add-to-cart-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .out-of-stock {
            color: #dc3545;
            font-weight: bold;
            padding: 5px 10px;
        }

        /* Search results grid */
        .search-results {
            padding: 2rem 5%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .search-results h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        .search-results p {
            font-size: 1rem;
            color: var(--text-gray);
            margin-bottom: 2rem;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .product-card {
            background: var(--primary-white);
            border: 1px solid var(--medium-gray);
            border-radius: 10px;
            overflow: hidden;
            text-align: center;
            padding: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .product-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            background: var(--accent-orange);
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
            border: 2px solid var(--accent-orange);
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

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 2rem;
        }
        .pagination a {
            padding: 10px 15px;
            border: 1px solid var(--medium-gray);
            border-radius: 5px;
            text-decoration: none;
            color: var(--primary-black);
            transition: background-color 0.3s;
        }
        .pagination a:hover {
            background: var(--light-gray);
        }
        .pagination a.active {
            background: var(--primary-blue);
            color: var(--primary-white);
            border-color: var(--primary-blue);
        }
        .pagination a.disabled {
            color: var(--text-gray);
            cursor: not-allowed;
        }

        /* Responsive styles */
        @media (min-width: 768px) {
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
            .product-grid {
                gap: 30px;
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

    <!-- Search Results Section -->
    <section class="search-results">
        <h2>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
        <?php if ($search_query): ?>
            <p>Found <?php echo $total_products; ?> result<?php echo $total_products != 1 ? 's' : ''; ?></p>
            <?php if (empty($products)): ?>
                <p>No products found matching your search.</p>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): 
                        $avg_rating = round($product['avg_rating'], 1);
                        $review_count = $product['review_count'];
                        $stock = $product['stock_quantity'];
                    ?>
                        <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                            <div class="product-image-container">
                                <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/400x300'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                                <?php if ($stock == 0): ?>
                                    <div class="product-badge out-of-stock">Out of Stock</div>
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
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                        <?php else: ?>
                            <a class="disabled">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="search.php?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>">Next</a>
                        <?php else: ?>
                            <a class="disabled">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <p>Please enter a search query.</p>
        <?php endif; ?>
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
                    <li><a href="unified.php?page=about">About</a></li>
                    <li><a href="unified.php?page=contact">Contact</a></li>
                    <li><a href="unified.php?page=careers">Careers</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Help</h4>
                <ul>
                    <li><a href="unified.php?page=support">Customer Support</a></li>
                    <li><a href="unified.php?page=shipping">Delivery Details</a></li>
                    <li><a href="unified.php?page=terms">Terms & Conditions</a></li>
                    <li><a href="unified.php?page=privacy">Privacy Policy</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>FAQ</h4>
                <ul>
                    <li><a href="unified.php?page=faq&section=account">Account</a></li>
                    <li><a href="unified.php?page=faq&section=delivery">Manage Deliveries</a></li>
                    <li><a href="unified.php?page=faq&section=orders">Orders</a></li>
                    <li><a href="unified.php?page=faq&section=payments">Payments</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h4>Resources</h4>
                <ul>
                    <li><a href="unified.php?page=blog">Blog</a></li>
                    <li><a href="unified.php?page=size-guide">Size Guide</a></li>
                    <li><a href="unified.php?page=care-instructions">Care Instructions</a></li>
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
            notification.className = 'notification';
            notification.textContent = message;
            notification.classList.add(type, 'show');
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
                const formData = new FormData();
                formData.append('action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', 1);

                const response = await fetch('search.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, 'success');
                    document.querySelectorAll('.cart-count').forEach(badge => {
                        badge.textContent = data.cartCount;
                    });
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Add to Cart Error:', error);
                showNotification('An error occurred while adding to cart. Please try again.', 'error');
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>