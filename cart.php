<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user
$user = getCurrentUser();

// ----- CART ITEM ADDITION -----
if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    
    $user_id = $user['id']; 
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    // Check if item already exists in cart
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Update existing item
        $new_quantity = $existing['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
    } else {
        // Insert new item
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $product_id, $quantity);
    }
    $stmt->execute();
    $stmt->close();

    // Fetch product name for notification
    $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Create notification (if function exists)
    if (function_exists('createNotification')) {
        $message = "Added {$product['name']} (x$quantity) to your cart.";
        createNotification($conn, $user_id, $message, 'cart_added', 0);
    }
    
    // Redirect to prevent form resubmission
    header('Location: cart.php');
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
    
    // Redirect to prevent form resubmission
    header('Location: cart.php');
    exit;
}

// ----- CART ITEM REMOVAL -----
if (isset($_POST['remove_from_cart']) && $user) {
    $product_id = (int)$_POST['product_id'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user['id'], $product_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to prevent form resubmission
    header('Location: cart.php');
    exit;
}

// ----- CART COUNT -----
$cart_count = 0;
if (function_exists('getCartCount') && $user) {
    $cart_count = getCartCount($conn, $user);
} elseif ($user) {
    // Fallback cart count calculation
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $cart_count = $result['total'] ?? 0;
    $stmt->close();
}

// ----- FETCH DELIVERY FEE FROM DB -----
$delivery_fee = 0;
if ($user) {
    $stmt = $conn->prepare("SELECT fee FROM delivery_fees WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $delivery_fee = $result['fee'] ?? 0;
    $stmt->close();
}
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
    <link rel="stylesheet" href="hamburger.css">
    <style>  
        /* ===== GLOBAL RESET AND BASE STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background: #f8fafc;
            overflow-x: hidden; /* Prevent horizontal scroll */
            min-height: 100vh;
            width: 100%;
            max-width: 100vw; /* Prevent width overflow */
        }

        /* ===== NAVIGATION WITH SCROLL HIDE ===== */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem;
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
            width: 100%;
            max-width: 100vw;
        }

        .navbar.navbar-hidden {
            transform: translateY(-100%);
        }

        .navbar.navbar-visible {
            transform: translateY(0);
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            padding-top: 80px;
        }

        .logo {
            font-size: clamp(1.2rem, 4vw, 1.8rem);
            font-weight: 600;
            color: #2A2AFF;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .logo:hover {
            transform: scale(1.05);
            color: #1A1AFF;
        }

        .logo i {
            font-size: clamp(1rem, 3vw, 1.6rem);
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .search-bar {
            display: flex;
            flex: 1;
            max-width: 500px;
            margin: 0 1rem;
            position: relative;
            min-width: 0; /* Allow shrinking */
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
            min-width: 0;
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
            flex-shrink: 0;
        }

        .search-bar button:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(42, 42, 255, 0.3);
        }

        /* ===== NAVIGATION RIGHT SECTION ===== */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        /* Cart Link */
        .cart-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
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
            white-space: nowrap;
        }

        .profile-trigger:hover {
            background: #f8f9fa;
            border-color: #2A2AFF;
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            flex-shrink: 0;
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

        /* ===== MAIN CONTENT ===== */
        main {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        /* Cart Container */
        .cart-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: flex-start;
            width: 100%;
            box-sizing: border-box;
        }

        /* Cart Items Section */
        .cart-items {
            flex: 2;
            min-width: 300px;
            width: 100%;
        }

        .cart-items h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: clamp(1.5rem, 4vw, 2rem);
            color: #000;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: #fff;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }

        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .cart-item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 0; /* Allow text to wrap */
        }

        .cart-item-details h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            color: #000;
            margin: 0;
            word-wrap: break-word;
        }

        .cart-item-details p {
            font-family: 'Poppins', sans-serif;
            font-weight: 400;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: #666;
            margin: 0;
        }

        .cart-item-details .price {
            font-weight: 600;
            color: #000;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-shrink: 0;
        }

        .quantity-controls button {
            width: 30px;
            height: 30px;
            border: 1px solid #ccc;
            background: #fff;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-controls button:hover {
            background: #f0f0f0;
        }

        .quantity-controls input {
            width: 50px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 0.25rem;
            font-family: 'Poppins', sans-serif;
        }

        .remove-item {
            margin-left: 1rem;
            flex-shrink: 0;
        }

        .remove-item button {
            background: none;
            border: none;
            color: #ff0000;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .remove-item button:hover {
            background: rgba(255, 0, 0, 0.1);
        }

        /* Order Summary Section */
        .order-summary {
            flex: 1;
            min-width: 300px;
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .order-summary h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: #000;
            margin-bottom: 1rem;
        }

        .order-summary .summary-item {
            display: flex;
            justify-content: space-between;
            font-family: 'Poppins', sans-serif;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            color: #333;
            margin-bottom: 0.5rem;
        }

        .order-summary .total {
            font-weight: 700;
            font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            margin-top: 1rem;
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }

        .order-summary .promo-code {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .order-summary .promo-code input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            min-width: 0;
        }

        .order-summary .promo-code button {
            padding: 0.5rem 1rem;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            white-space: nowrap;
        }

        .order-summary .promo-code button:hover {
            background: #333;
        }

        .order-summary .checkout-btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 1rem;
            transition: background 0.3s;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .order-summary .checkout-btn:hover {
            background: #333;
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .empty-cart h3 {
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            color: #333;
            margin-bottom: 1rem;
        }

        .empty-cart p a {
            color: #2A2AFF;
            text-decoration: none;
            font-weight: 500;
        }

        /* Footer Newsletter Section */
        .newsletter {
            width: 100%;
            max-width: 1200px;
            margin: 2rem auto;
            background: #000;
            color: #fff;
            text-align: center;
            padding: 2rem 1rem;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .newsletter h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .newsletter input {
            width: 100%;
            max-width: 300px;
            padding: 0.75rem;
            border: none;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 14px;
        }

        .newsletter button {
            padding: 0.75rem 1.5rem;
            background: #fff;
            color: #000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 14px;
            font-weight: 500;
        }

        .newsletter button:hover {
            background: #f0f0f0;
        }

        /* ===== RESPONSIVE BREAKPOINTS ===== */

        /* Large tablets and small desktops */
        @media (max-width: 1024px) {
            .cart-container {
                padding: 0 1rem;
                gap: 1.5rem;
            }
            
            .order-summary {
                position: static;
            }
        }

        /* Tablets */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .search-bar {
                order: 3;
                flex-basis: 100%;
                margin: 0.5rem 0 0 0;
                max-width: none;
            }

            .cart-container {
                flex-direction: column;
                gap: 1rem;
                margin: 1rem auto;
            }

            .cart-items,
            .order-summary {
                min-width: 100%;
                width: 100%;
            }

            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                text-align: left;
            }

            .cart-item img {
                width: 100%;
                height: 200px;
                margin-right: 0;
                margin-bottom: 0.5rem;
            }

            .cart-item-details {
                width: 100%;
            }

            .quantity-controls {
                justify-content: center;
                margin-top: 1rem;
            }

            .remove-item {
                margin-left: 0;
                align-self: center;
                margin-top: 0.5rem;
            }
        }

        /* Mobile devices */
        @media (max-width: 480px) {
            body {
                padding-top: 120px; /* Account for wrapped navbar */
            }

            .navbar {
                padding: 0.5rem;
            }

            .logo {
                font-size: 1.3rem;
                position: relative;
                bottom: 50px;
                right: 150px;
                margin-left: 50px;
            }
            .profile-dropdown{
                position: relative;
                left: 90px;
            }
           

            .cart-link span:not(.cart-count) {
                display: none; /* Hide "Cart" text, keep count */
            }

            .cart-container {
                padding: 0 0.5rem;
                margin: 0.5rem auto;
            }

            .cart-items h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            .cart-item {
                padding: 0.75rem;
                flex-direction: row;
                align-items: center;
            }
            .notification-dot {
    display: inline-block;
    background-color: red;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    margin-left: 5px;
    vertical-align: middle;
}
            .cart-item img {
                width: 60px;
                height: 60px;
                margin-right: 0.75rem;
            }

            .cart-item-details h3 {
                font-size: 0.9rem;
            }

            .cart-item-details p {
                font-size: 0.8rem;
            }

            .quantity-controls {
                margin-top: 0.25rem;
            }

            .quantity-controls button {
                width: 25px;
                height: 25px;
                font-size: 0.8rem;
            }

            .quantity-controls input {
                width: 40px;
                font-size: 0.8rem;
            }

            .order-summary {
                padding: 1rem;
            }

            .newsletter {
                margin: 1rem auto;
                padding: 1.5rem 0.5rem;
            }

            .newsletter input {
                margin-bottom: 0.75rem;
            }
        }

        /* Extra small devices */
        @media (max-width: 360px) {
            .cart-item {
                padding: 0.5rem;
            }

            .cart-item img {
                width: 50px;
                height: 50px;
                margin-right: 0.5rem;
            }

            .quantity-controls button {
                width: 22px;
                height: 22px;
            }

            .quantity-controls input {
                width: 35px;
            }

            .order-summary {
                padding: 0.75rem;
            }
        }

        /* Landscape orientation for mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            body {
                padding-top: 70px;
            }

            .navbar {
                padding: 0.5rem;
            }

            .cart-container {
                margin: 0.5rem auto;
            }

            .newsletter {
                margin-top: 1rem;
                padding: 1rem;
            }
        }
       
      
       
           .footer-bottom {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            position: relative;
            top: 265px;
           
            font-size: 12px;
            color: #ffffff;
        }

        /* Print styles */
        @media print {
            .navbar,
            .newsletter,
            .remove-item,
            .quantity-controls {
                display: none !important;
            }

            body {
                padding-top: 0;
                background: white;
                color: black;
            }

            .cart-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            .cart-item {
                page-break-inside: avoid;
                border: 1px solid #ccc;
                margin-bottom: 1rem;
            }

            .order-summary {
                page-break-inside: avoid;
                border: 2px solid #000;
                margin-top: 2rem;
            }
        }

        /* Prevent text selection issues on mobile */
        .quantity-controls button,
        .remove-item button,
        .profile-trigger {
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        /* Ensure buttons are properly sized for touch */
        @media (pointer: coarse) {
            .quantity-controls button,
            .remove-item button {
                min-width: 44px;
                min-height: 44px;
            }
        }
    </style>
</head>
<body>
    <!-- ----- NAVIGATION ----- -->
    <header>
        <nav class="navbar" id="navbar">
            <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
          
            
            
            <div class="nav-right">
               
                
                <!-- Profile Dropdown -->
               <div class="profile-dropdown">
    <?php if ($user): ?>
        <?php
        // Check for unread notifications
        require_once 'config.php'; // Include database connection
        $unread_count = 0;
        $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $unread_count = $result->fetch_assoc()['unread'];
        $stmt->close();
        ?>
        <div class="profile-trigger" onclick="toggleProfileDropdown()">
            <div class="profile-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-info">
                <span class="profile-greeting">Hi, <?php echo htmlspecialchars($user['full_name'] ?? $user['email'] ?? 'User'); ?></span>
                <span class="profile-account">My Account <i class="fas fa-chevron-down"></i></span>
            </div>
        </div>
        <div class="profile-dropdown-menu" id="profileDropdown">
            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
            <a href="inbox.php">
                <i class="fas fa-inbox"></i> Inbox
                <?php if ($unread_count > 0): ?>
                    <span class="notification-dot"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="index.php"><i class="fas fa-heart"></i> Home</a>
            <hr class="dropdown-divider">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
        <div class="profile-dropdown-menu" id="profileDropdown">
            <a href="login.php"><i class="fas fa-sign-in"></i> Sign In</a>
            <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
            <hr class="dropdown-divider">
            <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
        </div>
    <?php endif; ?>
</div>
                </div>
            </div>
        </nav>
    </header>

    <!-- ----- CART CONTENT ----- -->
    <main>
        <section class="cart-container">
            <div class="cart-items">
                <h2>Your Cart</h2>
                <div id="cartItems">
                    <?php
                    $total = 0;
                    if ($user):
                        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.name, p.price, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
                        $stmt->bind_param("i", $user['id']);
                        $stmt->execute();
                        $cart_items = $stmt->get_result();
                        
                        if ($cart_items && $cart_items->num_rows > 0):
                            while ($item = $cart_items->fetch_assoc()):
                                $item_total = $item['price'] * $item['quantity'];
                                $total += $item_total;
                    ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" onerror="this.src='images/placeholder.jpg'">
                        <div class="cart-item-details">
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p>Size: <?php echo isset($item['size']) ? htmlspecialchars($item['size']) : 'N/A'; ?></p>
                            <p>Color: <?php echo isset($item['color']) ? htmlspecialchars($item['color']) : 'N/A'; ?></p>
                            <p class="price">$<?php echo number_format($item['price'], 2); ?></p>
                            <form method="POST" class="quantity-controls">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <button type="submit" name="update_quantity" value="-" onclick="this.form.querySelector('input[name=quantity]').value = Math.max(1, this.form.querySelector('input[name=quantity]').value - 1)">-</button>
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" readonly>
                                <button type="submit" name="update_quantity" value="+" onclick="this.form.querySelector('input[name=quantity]').value = parseInt(this.form.querySelector('input[name=quantity]').value) + 1">+</button>
                            </form>
                        </div>
                        <div class="remove-item">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove this item?')">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <button type="submit" name="remove_from_cart"><i class="fas fa-trash"></i></button>
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
                        if (isset($stmt)) $stmt->close(); 
                    endif;
                    ?>
                </div>
            </div>

            <?php if ($user && $cart_items && $cart_items->num_rows > 0): ?>
            <div class="order-summary">
                <h3>Order Summary</h3>
                <div class="summary-item">Subtotal<span>$<?php echo number_format($total, 2); ?></span></div>
                <div class="summary-item">Delivery Fee<span>$<?php echo number_format($delivery_fee, 2); ?></span></div>
                <div class="summary-item total">Total<span>$<?php echo number_format($total + $delivery_fee, 2); ?></span></div>
                <div class="promo-code">
                    <input type="text" placeholder="Add promo code">
                    <button type="button">Apply</button>
                </div>
                <a href="checkout.php" class="checkout-btn">Go to Checkout <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($user && $cart_items && $cart_items->num_rows > 0): ?>
       
        <?php endif; ?>
    </main>
  <footer class="footer">
        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> Deeken. All Rights Reserved.</p>
        </div>
    </footer>
    <!-- ----- JAVASCRIPT ----- -->
    <script>
        // Global functions
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        function searchProducts() {
            const search = document.getElementById('searchInput').value;
            if (search.trim()) {
                window.location.href = `index.php?search=${encodeURIComponent(search)}`;
            }
        }

        // DOM Ready
        document.addEventListener('DOMContentLoaded', function() {
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const profileDropdown = document.querySelector('.profile-dropdown');
                const dropdown = document.getElementById('profileDropdown');
                if (profileDropdown && dropdown && !profileDropdown.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });

            // Navbar scroll hide/show functionality
            let lastScrollTop = 0;
            const navbar = document.getElementById('navbar');
            const scrollThreshold = 100;
            
            if (navbar) {
                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                    
                    if (currentScroll < scrollThreshold) {
                        navbar.classList.remove('navbar-hidden');
                        navbar.classList.add('navbar-visible');
                        return;
                    }
                    
                    if (currentScroll > lastScrollTop && currentScroll > scrollThreshold) {
                        navbar.classList.add('navbar-hidden');
                        navbar.classList.remove('navbar-visible');
                    } else if (currentScroll < lastScrollTop) {
                        navbar.classList.remove('navbar-hidden');
                        navbar.classList.add('navbar-visible');
                    }
                    
                    lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
                });
            }

            // Search on Enter key
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchProducts();
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
if (isset($conn)) {
    $conn->close();
}
?>