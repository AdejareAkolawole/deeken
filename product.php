<?php
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user and cart count
$user = getCurrentUser();
$cartCount = getCartCount($conn, $user);

// Fetch notifications for the user
$notifications = [];
$unread_count = 0;
if ($user) {
    $notifications_query = $conn->prepare("SELECT id, message, type, order_id, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $notifications_query->bind_param("i", $user['id']);
    $notifications_query->execute();
    $result = $notifications_query->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
        if (!$row['is_read']) {
            $unread_count++;
        }
    }
    $notifications_query->close();
}

// Fetch product details
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = null;
if ($product_id > 0) {
    $product_query = "SELECT p.*, c.name as category_name, 
                      COALESCE(AVG(r.rating), 0) AS avg_rating,
                      COUNT(r.id) AS review_count,
                      COALESCE(i.stock_quantity, 0) AS stock_quantity
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      LEFT JOIN reviews r ON p.id = r.product_id 
                      LEFT JOIN inventory i ON p.id = i.product_id 
                      WHERE p.id = ? 
                      GROUP BY p.id";
    $product_stmt = $conn->prepare($product_query);
    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();
    $product = $product_stmt->get_result()->fetch_assoc();
    $product_stmt->close();

    // Fetch reviews
    $reviews_query = "SELECT r.review_text, r.rating, u.full_name, r.created_at 
                      FROM reviews r 
                      JOIN users u ON r.user_id = u.id 
                      WHERE r.product_id = ? 
                      ORDER BY r.created_at DESC 
                      LIMIT 4";
    $reviews_stmt = $conn->prepare($reviews_query);
    $reviews_stmt->bind_param("i", $product_id);
    $reviews_stmt->execute();
    $reviews = $reviews_stmt->get_result();
    $reviews_stmt->close();

    // Fetch related products
    $related_query = "SELECT p.*, c.name as category_name, 
                      COALESCE(AVG(r.rating), 0) AS avg_rating,
                      COUNT(r.id) AS review_count,
                      COALESCE(i.stock_quantity, 0) AS stock_quantity
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      LEFT JOIN reviews r ON p.id = r.product_id 
                      LEFT JOIN inventory i ON p.id = i.product_id 
                      WHERE p.category_id = ? AND p.id != ? 
                      GROUP BY p.id 
                      ORDER BY RAND() 
                      LIMIT 4";
    $related_stmt = $conn->prepare($related_query);
    $related_stmt->bind_param("ii", $product['category_id'], $product_id);
    $related_stmt->execute();
    $related_products = $related_stmt->get_result();
    $related_stmt->close();
}

// Handle add to cart
if ($_POST['action'] ?? '' === 'add_to_cart') {
    error_log("Add to cart action triggered");
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Please login to add items to cart.']);
        exit;
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
        exit;
    }

    if ($quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity.']);
        exit;
    }

    try {
        $product_check = $conn->prepare("SELECT p.*, COALESCE(i.stock_quantity, 0) AS stock_quantity FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE p.id = ?");
        $product_check->bind_param("i", $product_id);
        $product_check->execute();
        $product = $product_check->get_result()->fetch_assoc();
        $product_check->close();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }

        if ($product['stock_quantity'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock.']);
            exit;
        }

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
            $add_cart = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $add_cart->bind_param("iii", $user['id'], $product_id, $quantity);
            $success = $add_cart->execute();
            $add_cart->close();
        }
        
        if ($success) {
            $newCartCount = getCartCount($conn, $user);
            echo json_encode(['success' => true, 'message' => 'Item added to cart!', 'cartCount' => $newCartCount]);
        } else {
            error_log("Failed to add to cart: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Failed to add item to cart: ' . $conn->error]);
        }
    } catch (Exception $e) {
        error_log("Add to cart error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// Handle marking notification as read
if ($_POST['action'] ?? '' === 'mark_notification_read') {
    if ($user) {
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notification_id, $user['id']);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
    }
    exit;
}

// Handle review submission
if ($_POST['action'] ?? '' === 'submit_review') {
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Please login to submit a review.']);
        exit;
    }

    $product_id = intval($_POST['product_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');

    if ($product_id <= 0 || $rating < 1 || $rating > 5 || empty($review_text)) {
        echo json_encode(['success' => false, 'message' => 'Invalid review data.']);
        exit;
    }

    try {
        $product_check = $conn->prepare("SELECT id FROM products WHERE id = ?");
        $product_check->bind_param("i", $product_id);
        $product_check->execute();
        if (!$product_check->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            $product_check->close();
            exit;
        }
        $product_check->close();

        $review_stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, NOW())");
        $review_stmt->bind_param("iiis", $product_id, $user['id'], $rating, $review_text);
        $success = $review_stmt->execute();
        $review_stmt->close();

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Review submitted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit review: ' . $conn->error]);
        }
    } catch (Exception $e) {
        error_log("Submit review error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=1.5, user-scalable=yes">
    <title><?php echo $product ? htmlspecialchars($product['name']) : 'Product'; ?> - Deeken</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        /* Base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
        }

        /* Notification System */
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
            min-width: 250px;
            max-width: 90%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .notification.success { background-color: #28a745; }
        .notification.error { background-color: #dc3545; }
        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        /* Login Prompt Modal */
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
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            max-width: 90%;
            width: 400px;
        }
        .btn-primary, .btn-secondary {
            padding: 10px 20px;
            border: none;
            border-radius: 25px; /* Increased for rounded buttons */
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-size: 14px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        /* Top Banner Marquee */
        .top-banner {
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: #fff;
            padding: 10px 0;
            position: relative;
            font-size: 14px;
            overflow: hidden;
        }
        .marquee-container {
            width: 100%;
            overflow: hidden;
        }
        .marquee-content {
            display: flex;
            animation: marquee 20s linear infinite;
            white-space: nowrap;
        }
        .marquee-text {
            margin-right: 40px;
        }
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        .close-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
        }

        /* Navigation */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 5%;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logo {
            font-size: 24px;
            font-weight: 700;
        }
        .search-bar {
            position: relative;
            width: 100%;
            max-width: 500px;
        }
        .search-bar input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .search-bar .fa-search {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
        }
        .nav-icons {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .notification-dropdown, .profile-dropdown {
            position: relative;
        }
        .notification-btn, .cart-btn {
            background: none;
            border: none;
            cursor: pointer;
            position: relative;
            padding: 10px;
            font-size: 18px;
        }
        .notification-badge, .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff6f61;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 250px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .notification-dropdown-menu.active {
            display: block;
        }
        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        .notification-item.unread {
            background: #f9f9f9;
        }
        .notification-item:hover {
            background: #f5f5f5;
        }
        .notification-icon {
            margin-right: 8px;
            font-size: 16px;
        }
        .notification-time {
            font-size: 12px;
            color: #666;
        }
        .profile-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 200px;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .profile-dropdown-menu.active {
            display: block;
        }
        .profile-dropdown-menu a {
            display: block;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
        }
        .profile-dropdown-menu a:hover {
            background: #f5f5f5;
        }
        .profile-trigger {
            display: flex;
            align-items: center;
            cursor: pointer;
            gap: 10px;
        }
        .profile-avatar {
            width: 30px;
            height: 30px;
            background: #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .profile-info {
            display: flex;
            flex-direction: column;
        }
        .profile-greeting {
            font-size: 14px;
            font-weight: 500;
        }
        .profile-account {
            font-size: 12px;
            color: #666;
        }

        /* Product Section */
        .product-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 5%;
            display: flex;
            gap: 20px;
        }
        .product-images {
            flex: 1;
        }
        .product-images .main-image {
            width: 100%;
            height: auto; /* Adjusted to show full image */
            object-fit: contain; /* Changed from cover to contain */
            border-radius: 10px;
            margin-bottom: 10px;
            max-height: 500px; /* Optional: set a max height if needed */
        }
        .product-images .thumbnails {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .product-images .thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .product-images .thumbnail.active {
            border-color: #ff6f61;
        }
        .product-details {
            flex: 1;
        }
        .product-details h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #000;
        }
        .product-details .rating {
            margin-bottom: 10px;
        }
        .product-details .rating .stars {
            color: #f4c430;
            font-size: 16px;
        }
        .product-details .rating .rating-text {
            font-size: 12px;
            color: #666;
            margin-left: 5px;
        }
        .product-details .price {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .product-details .original-price {
            font-size: 16px;
            color: #666;
            text-decoration: line-through;
            margin-left: 10px;
        }
        .product-details .discount {
            color: #ff6f61;
            font-weight: 600;
            margin-left: 10px;
        }
        .product-details .description {
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        .product-details .quantity {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .product-details .quantity button {
            width: 35px;
            height: 35px;
            border: 1px solid #ddd;
            background: #fff;
            cursor: pointer;
            font-size: 16px;
            border-radius: 25px; /* Rounded buttons */
        }
        .product-details .quantity input {
            width: 50px;
            height: 35px;
            border: 1px solid #ddd;
            text-align: center;
            margin: 0 5px;
            font-size: 14px;
        }
        .add-to-cart-btn {
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 25px; /* Rounded button */
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.3s;
        }
        .add-to-cart-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #1a1aff, #9de3ff);
        }
        .add-to-cart-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }

        /* Reviews Section */
        .reviews-section {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 5%;
        }
        .reviews-section h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        .review-form {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: <?php echo $user ? 'block' : 'none'; ?>;
        }
        .review-form.hidden {
            display: none;
        }
        .review-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .review-form select, .review-form textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .review-form button {
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px; /* Rounded button */
            cursor: pointer;
        }
        .review-form button:hover {
            background: linear-gradient(135deg, #1a1aff, #9de3ff);
        }

        /* Related Products */
        .related-products {
            padding: 20px 5%;
            text-align: center;
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: white;
        }
        .related-products h2 {
            font-size: 20px;
            margin-bottom: 15px;
        }
        .related-products .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .related-products .product-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            text-align: center;
            padding: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
            color: #333;
        }
        .related-products .product-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        .related-products .product-image {
            width: 100%;
            height: auto; /* Adjusted to show full image */
            object-fit: contain; /* Changed from cover to contain */
            margin-bottom: 10px;
            max-height: 200px; /* Optional: set a max height if needed */
        }
        .related-products .product-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .related-products .product-price {
            font-size: 16px;
            font-weight: 700;
        }

        /* Newsletter */
        .newsletter {
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: #fff;
            padding: 20px;
            text-align: center;
            margin-top: 20px;
        }
        .newsletter h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        .newsletter input {
            padding: 10px;
            width: 100%;
            max-width: 300px;
            border: none;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .newsletter button {
            padding: 10px 20px;
            border: none;
            background: #fff;
            color: #000;
            border-radius: 25px; /* Rounded button */
            cursor: pointer;
        }

        /* Footer */
        .footer {
            background: #f9f9f9;
            padding: 20px 5%;
            font-size: 14px;
            text-align: center;
        }
        .footer-bottom {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .payment-icons {
            display: flex;
            gap: 10px;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 767px) {
            html, body {
                font-size: 14px;
            }
            .navbar {
                padding: 10px 5%;
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
            .logo {
                margin-bottom: 0;
            }
            .search-bar {
                width: 100%;
                max-width: 300px;
                margin: 0 10px;
            }
            .nav-icons {
                width: auto;
                justify-content: flex-end;
                gap: 10px;
            }
            .profile-info {
                display: none;
            }
            .notification-dropdown-menu, .profile-dropdown-menu {
                width: 100%;
                right: 0;
            }
            .product-section {
                flex-direction: column;
                padding: 10px;
                gap: 10px;
            }
            .product-images .main-image {
                height: auto;
                max-height: 300px;
            }
            .product-images .thumbnail {
                width: 50px;
                height: 50px;
            }
            .product-details h1 {
                font-size: 18px;
            }
            .product-details .price {
                font-size: 16px;
            }
            .product-details .description {
                font-size: 13px;
            }
            .product-details .quantity button {
                width: 30px;
                height: 30px;
            }
            .product-details .quantity input {
                width: 40px;
                height: 30px;
            }
            .add-to-cart-btn {
                font-size: 14px;
                padding: 8px;
            }
            .reviews-section {
                padding: 10px;
            }
            .reviews-section h2 {
                font-size: 16px;
            }
            .review-form {
                padding: 10px;
            }
            .review-form select, .review-form textarea {
                font-size: 13px;
            }
            .related-products {
                padding: 10px;
            }
            .related-products h2 {
                font-size: 16px;
            }
            .related-products .product-image {
                height: auto;
                max-height: 150px;
            }
            .related-products .product-title {
                font-size: 12px;
            }
            .related-products .product-price {
                font-size: 14px;
            }
            .newsletter {
                padding: 15px;
            }
            .newsletter h2 {
                font-size: 16px;
            }
            .newsletter input {
                max-width: 100%;
            }
            .footer {
                padding: 10px;
            }
            .notification {
                min-width: 200px;
                font-size: 13px;
                right: 10px;
            }
            .login-prompt .modal {
                width: 90%;
                max-width: 300px;
                padding: 15px;
            }
        }

        @media (min-width: 768px) {
            .product-images .main-image {
                height: auto;
                max-height: 400px;
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

    <!-- Top Banner Marquee -->
    <div class="top-banner">
        <div class="marquee-container">
            <div class="marquee-content">
                <span class="marquee-text">🎉 Sign up and get 20% off to your first order. <a href="register.php" style="color: #ff6f61;">Sign Up Now</a></span>
                <span class="marquee-text">✨ Free shipping on orders over $50</span>
                <span class="marquee-text">🔥 Limited time offer - Don't miss out!</span>
                <span class="marquee-text">💫 New arrivals every week</span>
                <span class="marquee-text">🎉 Sign up and get 20% off to your first order. <a href="register.php" style="color: #ff6f61;">Sign Up Now</a></span>
            </div>
        </div>
        <button class="close-btn" onclick="closeBanner()">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo">Deeken</div>
        <div class="search-bar" id="searchBar">
            <i class="fas fa-search" id="searchIcon"></i>
            <input type="text" placeholder="Search for products..." id="searchInput">
            <div class="autocomplete-suggestions" id="autocompleteSuggestions"></div>
        </div>
        <div class="nav-icons">
            <div class="notification-dropdown">
                <button class="notification-btn" title="Notifications" onclick="toggleNotificationDropdown()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notification-dropdown-menu" id="notificationDropdown">
                    <?php if (empty($notifications)): ?>
                        <div class="notification-item">No notifications</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                 data-notification-id="<?php echo $notification['id']; ?>" 
                                 onclick="markNotificationRead(<?php echo $notification['id']; ?>, <?php echo $notification['order_id']; ?>)">
                                <i class="fas fa-<?php echo $notification['type'] === 'order_received' ? 'check-circle' : 'truck'; ?> notification-icon"></i>
                                <div class="notification-content">
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <span class="notification-time"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <button class="cart-btn" title="Shopping Cart" onclick="window.location.href='cart.php'">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge" id="cartBadge"><?php echo $cartCount; ?></span>
            </button>
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

    <?php if ($product): ?>
    <!-- Product Section -->
    <section class="product-section">
        <div class="product-images">
            <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/500x500'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="main-image">
            <div class="thumbnails">
                <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/80x80'); ?>" alt="Thumbnail 1" class="thumbnail active">
                <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/80x80'); ?>" alt="Thumbnail 2" class="thumbnail">
                <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/80x80'); ?>" alt="Thumbnail 3" class="thumbnail">
                <img src="<?php echo htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/80x80'); ?>" alt="Thumbnail 4" class="thumbnail">
            </div>
        </div>
        <div class="product-details">
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="rating">
                <span class="stars"><?php echo displayStars(round($product['avg_rating'], 1)); ?></span>
                <span class="rating-text"><?php echo round($product['avg_rating'], 1); ?> (<?php echo $product['review_count']; ?> reviews)</span>
            </div>
            <div class="price">
                <span>$<?php echo number_format($product['price'], 2); ?></span>
                <?php if (isset($product['original_price']) && $product['original_price'] > $product['price']): ?>
                    <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
                    <span class="discount"><?php echo round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>% off</span>
                <?php endif; ?>
            </div>
            <p class="description"><?php echo htmlspecialchars($product['description'] ?? 'This is a stylish product crafted for any occasion.'); ?></p>
            <div class="quantity">
                <button onclick="updateQuantity(-1)">-</button>
                <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock_quantity'] > 0 ? $product['stock_quantity'] : 1; ?>">
                <button onclick="updateQuantity(1)">+</button>
            </div>
            <button class="add-to-cart-btn" onclick="addToCart(<?php echo $product['id']; ?>)" <?php echo $product['stock_quantity'] == 0 ? 'disabled' : ''; ?>>Add to Cart</button>
        </div>
    </section>

    <!-- Reviews Section -->
    <section class="reviews-section" style="max-width: 1200px; margin: 40px auto; padding: 0 5%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Rating & Reviews</h2>
            <?php if ($user): ?>
                <button onclick="toggleReviewForm()" class="btn-secondary" id="reviewToggleBtn">Write a Review</button>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <?php if ($reviews->num_rows > 0): ?>
                    <?php while ($review = $reviews->fetch_assoc()): ?>
                        <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                <span class="stars"><?php echo displayStars($review['rating']); ?></span>
                                <span style="margin-left: 10px; font-weight: 500;"><?php echo htmlspecialchars($review['full_name']); ?> ✓</span>
                            </div>
                            <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                            <p style="font-size: 12px; color: #666; margin-top: 10px;">Posted on <?php echo date('F d, Y', strtotime($review['created_at'])); ?></p>
                        </div>
                    <?php endwhile; ?>
                    <?php if ($reviews->num_rows >= 4): ?>
                        <button style="background: #fff; border: 1px solid #ddd; padding: 10px 20px; border-radius: 25px; cursor: pointer; width: 100%;">Load More Reviews</button>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No reviews yet. Be the first to write a review!</p>
                <?php endif; ?>
            </div>
        </div>
        <div id="reviewFormContainer" class="review-form">
            <form id="reviewForm">
                <label for="rating">Rating:</label>
                <select id="rating" name="rating" required>
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </select>
                <label for="review_text">Your Review:</label>
                <textarea id="review_text" name="review_text" required placeholder="Write your review here..." rows="4"></textarea>
                <button type="submit">Submit Review</button>
            </form>
        </div>
    </section>

    <!-- Related Products -->
    <section class="related-products">
        <h2>You Might Also Like</h2>
        <div class="product-grid">
            <?php while ($related_product = $related_products->fetch_assoc()): ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars($related_product['image'] ?? 'https://via.placeholder.com/200x200'); ?>" alt="<?php echo htmlspecialchars($related_product['name']); ?>" class="product-image" onclick="window.location.href='product.php?id=<?php echo $related_product['id']; ?>'">
                    <h3 class="product-title"><?php echo htmlspecialchars($related_product['name']); ?></h3>
                    <div class="product-price">
                        <span>$<?php echo number_format($related_product['price'], 2); ?></span>
                        <?php if (isset($related_product['original_price']) && $related_product['original_price'] > $related_product['price']): ?>
                            <span class="original-price">$<?php echo number_format($related_product['original_price'], 2); ?></span>
                            <span class="discount"><?php echo round((($related_product['original_price'] - $related_product['price']) / $related_product['original_price']) * 100); ?>% off</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="newsletter">
        <h2>STAY UP TO DATE ABOUT OUR LATEST OFFERS</h2>
        <form>
            <input type="email" placeholder="Enter your email address" required>
            <button type="submit">Subscribe to Newsletter</button>
        </form>
    </section>

    <?php else: ?>
        <section style="text-align: center; padding: 50px;">
            <h2>Product Not Found</h2>
            <p>The product you are looking for does not exist.</p>
            <a href="shop.php" class="btn-primary">Back to Shop</a>
        </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
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
            const button = document.querySelector('.add-to-cart-btn');
            button.disabled = true;
            button.textContent = 'Adding...';

            if (!isLoggedIn) {
                showLoginPrompt();
                button.disabled = false;
                button.textContent = 'Add to Cart';
                return;
            }

            const quantity = parseInt(document.getElementById('quantity').value);

            if (quantity < 1) {
                showNotification('Please select a valid quantity.', 'error');
                button.disabled = false;
                button.textContent = 'Add to Cart';
                return;
            }

            try {
                console.log('Adding to cart:', { productId, quantity });
                const formData = new FormData();
                formData.append('action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', quantity);

                const response = await fetch('product.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('cartBadge').textContent = data.cartCount;
                } else {
                    showNotification(data.message || 'Failed to add to cart.', 'error');
                }
            } catch (error) {
                console.error('Add to Cart Error:', error);
                showNotification('An error occurred while adding to cart. Please try again.', 'error');
            } finally {
                button.disabled = false;
                button.textContent = 'Add to Cart';
            }
        }

        async function markNotificationRead(notificationId, orderId) {
            try {
                const formData = new FormData();
                formData.append('action', 'mark_notification_read');
                formData.append('notification_id', notificationId);

                const response = await fetch('product.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();
                if (data.success) {
                    const item = document.querySelector(`.notification-item[data-notification-id="${notificationId}"]`);
                    item.classList.remove('unread');
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        let count = parseInt(badge.textContent) - 1;
                        if (count <= 0) badge.remove();
                        else badge.textContent = count;
                    }
                    window.location.href = `orders.php?id=${orderId}`;
                }
            } catch (error) {
                console.error('Mark Notification Error:', error);
            }
        }

        document.getElementById('reviewForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const rating = document.getElementById('rating').value;
            const reviewText = document.getElementById('review_text').value;
            const productId = <?php echo $product_id; ?>;

            try {
                const formData = new FormData();
                formData.append('action', 'submit_review');
                formData.append('product_id', productId);
                formData.append('rating', rating);
                formData.append('review_text', reviewText);

                const response = await fetch('product.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();
                if (data.success) {
                    showNotification('Review submitted successfully!', 'success');
                    document.getElementById('reviewForm').reset();
                    window.location.reload();
                } else {
                    showNotification(data.message || 'Failed to submit review.', 'error');
                }
            } catch (error) {
                console.error('Submit Review Error:', error);
                showNotification('An error occurred while submitting your review.', 'error');
            }
        });

        function toggleReviewForm() {
            const formContainer = document.getElementById('reviewFormContainer');
            const isHidden = formContainer.classList.contains('hidden');
            formContainer.classList.toggle('hidden', !isHidden);
            document.getElementById('reviewToggleBtn').textContent = isHidden ? 'Hide Review' : 'Write a Review';
        }

        function toggleNotificationDropdown() {
            document.getElementById('notificationDropdown').classList.toggle('active');
        }

        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('active');
        }

        function toggleProfileDropdown() {
            document.getElementById('profileDropdown').classList.toggle('active');
        }

        function closeBanner() {
            document.querySelector('.top-banner').style.display = 'none';
        }

        // Search and autocomplete
        const searchBar = document.getElementById('searchBar');
        const searchInput = document.getElementById('searchInput');
        const searchIcon = document.getElementById('searchIcon');
        const autocompleteSuggestions = document.getElementById('autocompleteSuggestions');

        function highlightMatch(text, query) {
            const regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<span class="autocomplete-highlight">$1</span>');
        }

        function performSearch() {
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = `shop.php?q=${encodeURIComponent(query)}`;
            }
        }

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });

        searchIcon.addEventListener('click', function() {
            if (window.innerWidth <= 767) {
                searchBar.classList.toggle('active');
                if (searchBar.classList.contains('active')) searchInput.focus();
            } else {
                performSearch();
            }
        });

        let debounceTimeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            const query = this.value.trim();

            if (query.length >= 2) {
                debounceTimeout = setTimeout(() => {
                    fetch(`autocomplete.php?q=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            autocompleteSuggestions.innerHTML = '';
                            if (data.length > 0) {
                                data.forEach(item => {
                                    const div = document.createElement('div');
                                    div.className = 'autocomplete-suggestion';
                                    div.innerHTML = `
                                        <img src="${item.image || 'https://via.placeholder.com/40'}" alt="${item.name}">
                                        <span>${highlightMatch(item.name, query)}</span>
                                    `;
                                    div.addEventListener('click', () => {
                                        searchInput.value = item.name;
                                        autocompleteSuggestions.style.display = 'none';
                                        window.location.href = `shop.php?q=${encodeURIComponent(item.name)}`;
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
                            autocompleteSuggestions.style.display = 'none';
                        });
                }, 300);
            } else {
                autocompleteSuggestions.style.display = 'none';
            }
        });

        document.addEventListener('click', function(e) {
            if (!searchBar.contains(e.target) && !autocompleteSuggestions.contains(e.target)) {
                autocompleteSuggestions.style.display = 'none';
                if (window.innerWidth <= 767) searchBar.classList.remove('active');
            }
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.querySelector('.profile-trigger');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationTrigger = document.querySelector('.notification-btn');
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
            if (!notificationTrigger.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('active');
            }
        });

        // Thumbnail selection
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.addEventListener('click', function() {
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.querySelector('.main-image').src = this.src;
            });
        });

        // Quantity update
        function updateQuantity(change) {
            const input = document.getElementById('quantity');
            let value = parseInt(input.value) + change;
            const max = parseInt(input.max);
            value = Math.max(1, Math.min(value, max));
            input.value = value;
        }

        // Ensure dropdowns don't overflow on mobile
        function adjustDropdowns() {
            const dropdowns = [document.getElementById('notificationDropdown'), document.getElementById('profileDropdown')];
            dropdowns.forEach(dropdown => {
                if (dropdown) {
                    dropdown.style.maxWidth = `${window.innerWidth - 20}px`;
                    dropdown.style.right = '10px';
                }
            });
        }

        window.addEventListener('resize', adjustDropdowns);
        document.addEventListener('DOMContentLoaded', adjustDropdowns);

        // Prevent dropdowns from staying open on mobile
        document.querySelectorAll('.notification-dropdown-menu, .profile-dropdown-menu').forEach(dropdown => {
            dropdown.addEventListener('click', (e) => {
                if (window.innerWidth <= 767) {
                    dropdown.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>