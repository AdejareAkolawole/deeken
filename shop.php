<?php
require_once 'config.php';

$user = getCurrentUser();
$cartCount = getCartCount($conn, $user);

// Fetch categories for navigation and filters
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}
$categories_result->free();

// Fetch all distinct miscellaneous attributes
$attributes_query = "SELECT DISTINCT attribute FROM miscellaneous_attributes ORDER BY attribute";
$attributes_result = $conn->query($attributes_query);
$attributes = [];
if ($attributes_result) {
    while ($row = $attributes_result->fetch_assoc()) {
        $attributes[] = $row['attribute'];
    }
    $attributes_result->free();
}

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

// Handle filters and search
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$price_min = isset($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
$price_max = isset($_GET['price_max']) ? floatval($_GET['price_max']) : 1000;
$rating_filter = isset($_GET['rating']) ? floatval($_GET['rating']) : 0;
$attribute_filter = isset($_GET['attribute']) ? trim($_GET['attribute']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;

// Build the product query
$conditions = [];
$params = [];
$types = '';

if ($search_query) {
    $conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $types .= 'ss';
}
if ($category_filter > 0) {
    $conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}
if ($price_min > 0) {
    $conditions[] = "p.price >= ?";
    $params[] = $price_min;
    $types .= 'd';
}
if ($price_max < 1000) {
    $conditions[] = "p.price <= ?";
    $params[] = $price_max;
    $types .= 'd';
}
if ($rating_filter > 0) {
    $conditions[] = "COALESCE(p.rating, 0) >= ?";
    $params[] = $rating_filter;
    $types .= 'd';
}
if ($attribute_filter) {
    $conditions[] = "EXISTS (SELECT 1 FROM miscellaneous_attributes ma WHERE ma.product_id = p.id AND ma.attribute = ?)";
    $params[] = $attribute_filter;
    $types .= 's';
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sort_query = 'ORDER BY ';
switch ($sort) {
    case 'price_asc':
        $sort_query .= 'p.price ASC';
        break;
    case 'price_desc':
        $sort_query .= 'p.price DESC';
        break;
    case 'rating':
        $sort_query .= 'p.rating DESC';
        break;
    case 'new':
        $sort_query .= 'p.created_at DESC';
        break;
    case 'name':
        $sort_query .= 'p.name ASC';
        break;
    default:
        $sort_query .= 'p.id DESC';
}

$offset = ($page - 1) * $per_page;

$count_query = "SELECT COUNT(DISTINCT p.id) as total 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN inventory i ON p.id = i.product_id 
                $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_products = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
$total_pages = ceil($total_products / $per_page);

$products_query = "SELECT p.*, c.name as category_name, 
                   COALESCE(p.rating, 0) AS avg_rating,
                   (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) AS review_count,
                   COALESCE(i.stock_quantity, 0) AS stock_quantity
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   LEFT JOIN inventory i ON p.id = i.product_id 
                   $where_clause 
                   GROUP BY p.id 
                   $sort_query 
                   LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$products_stmt = $conn->prepare($products_query);
$products_stmt->bind_param($types, ...$params);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}
$products_stmt->close();

// Handle add to cart
if ($_POST['action'] ?? '' === 'add_to_cart') {
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Please login to add items to cart.']);
        exit;
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($product_id > 0) {
        $product_check = $conn->prepare("SELECT p.*, COALESCE(i.stock_quantity, 0) AS stock_quantity 
                            FROM products p LEFT JOIN inventory i ON p.id = i.product_id WHERE p.id = ?");
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

// Function to display star rating
function displayStars($rating) {
    $stars = '';
    $fullStars = floor($rating);
    $hasHalfStar = ($rating - $fullStars) >= 0.5;
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $fullStars) {
            $stars .= '★';
        } elseif ($i == $fullStars + 1 && $hasHalfStar) {
            $stars .= '½';
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
    <title>Shop - Deeken</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #fafafa;
            color: #1a1a1a;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .top-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 0;
            text-align: center;
            font-size: 12px;
            font-weight: 500;
        }

        .top-banner a {
            color: #fff;
            text-decoration: underline;
            font-weight: 600;
        }

        .navbar {
            background: white;
            padding: 12px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 12px;
        }

        .logo {
            font-size: clamp(20px, 4vw, 28px);
            font-weight: 800;
            color: #2d3436;
            text-decoration: none;
            flex-shrink: 0;
        }

        .search-bar {
            flex: 1;
            max-width: 500px;
            min-width: 200px;
            position: relative;
            order: 3;
            width: 100%;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e1e8ed;
            border-radius: 50px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-bar i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #8e9aaf;
            font-size: 16px;
        }

        .search-bar button {
            display: none;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
            order: 2;
        }

        .notification-dropdown, .profile-dropdown {
            position: relative;
        }

        .notification-btn, .cart-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: background 0.3s ease;
            position: relative;
            color: #2d3436;
            font-size: 18px;
        }

        .notification-btn:hover, .cart-btn:hover {
            background: #f1f3f4;
        }

        .notification-badge, .cart-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 600;
            min-width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 50px;
            transition: background 0.3s ease;
        }

        .profile-trigger:hover {
            background: #f1f3f4;
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            font-size: 10px;
            color: #8e9aaf;
            white-space: nowrap;
        }

        .profile-account {
            font-size: 12px;
            font-weight: 600;
            color: #2d3436;
            white-space: nowrap;
        }

        .notification-dropdown-menu, .profile-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            min-width: 280px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            border: 1px solid #e1e8ed;
        }

        .notification-dropdown-menu.show, .profile-dropdown-menu.show {
            display: block;
        }

        .notification-item {
            display: flex;
            padding: 14px;
            border-bottom: 1px solid #f1f3f4;
            cursor: pointer;
            transition: background 0.3s ease;
            gap: 10px;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: #f0f4ff;
        }

        .notification-icon {
            color: #667eea;
            font-size: 14px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .notification-content p {
            font-size: 13px;
            margin-bottom: 4px;
            color: #2d3436;
        }

        .notification-time {
            font-size: 11px;
            color: #8e9aaf;
        }

        .profile-dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            text-decoration: none;
            color: #2d3436;
            font-size: 13px;
            transition: background 0.3s ease;
        }

        .profile-dropdown-menu a:hover {
            background: #f8f9fa;
        }

        .dropdown-divider {
            border: none;
            height: 1px;
            background: #e1e8ed;
            margin: 6px 0;
        }

        .shop-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }

        .filters-sidebar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            height: fit-content;
            box-shadow: 0 4px 25px rgba(0,0,0,0.06);
            border: 1px solid #e1e8ed;
        }

        .mobile-filter-toggle {
            display: none;
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 20px;
            width: 100%;
        }

        .filters-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #2d3436;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .clear-filters {
            font-size: 12px;
            color: #667eea;
            cursor: pointer;
            font-weight: 500;
        }

        .filter-section {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f3f4;
        }

        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #2d3436;
        }

        .filter-select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e1e8ed;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .price-range {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        .price-input {
            flex: 1;
            padding: 8px 10px;
            border: 2px solid #e1e8ed;
            border-radius: 4px;
            font-size: 12px;
            width: 50px;
            text-align: center;
        }

        .price-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .attribute-grid {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 10px;
        }

        .attribute-option {
            padding: 6px;
            border: 2px solid #e1e8ed;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            background: white;
        }

        .attribute-option.selected {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .rating-filter {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 10px;
        }

        .rating-option {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 13px;
        }

        .rating-option input[type="radio"] {
            margin-right: 6px;
        }

        .apply-filters-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s ease;
            margin-top: 16px;
        }

        .apply-filters-btn:hover {
            transform: translateY(-1px);
        }

        .products-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.06);
        }

        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .products-title {
            font-size: clamp(20px, 4vw, 28px);
            font-weight: bold;
            color: #2d3436;
        }

        .products-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
        }

        .sort-dropdown {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            min-width: 140px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            position: relative;
        }

        .product-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 16px;
        }

        .product-title {
            font-size: 16px;
            font-weight: bold;
            color: #333333;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .product-category {
            font-size: 12px;
            color: #555;
            margin-bottom: 8px;
        }

        .product-rating {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            color: #ffcc00;
            font-size: 14px;
            align-items: center;
        }

        .product-price {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 12px;
            color: #333;
        }

        .add-to-cart-btn {
            width: 100%;
            padding: 10px;
            background-color: #0066cc;
            color: white;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 600;
        }

        .add-to-cart-btn:hover {
            background-color: #004499;
            transform: translateY(-1px);
        }

        .add-to-cart-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        .stock-badge {
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .out-of-stock {
            background: rgba(255, 68, 68, 0.9);
            color: white;
        }

        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            text-decoration: none;
            color: #333333;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background-color: #333;
            color: white;
        }

        .pagination a.active {
            background-color: #0066cc;
            color: white;
            border-color: #0066cc;
        }

        .footer {
            background-color: #333;
            color: white;
            padding: 32px 0;
            margin-top: 40px;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            padding: 0 20px;
        }

        .footer a {
            color: white;
            text-decoration: none;
            font-size: 13px;
            display: block;
            margin-bottom: 6px;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 16px;
            margin-top: 24px;
            border-top: 1px solid #666666;
            font-size: 12px;
            color: #cccccc;
        }

        /* Mobile Responsive Styles */
        @media screen and (max-width: 1200px) {
            .shop-container {
                grid-template-columns: 260px 1fr;
                gap: 20px;
                padding: 16px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
        }

        @media screen and (max-width: 992px) {
            .navbar {
                padding: 12px 3%;
            }
            
            .search-bar {
                max-width: 300px;
            }
            
            .shop-container {
                grid-template-columns: 240px 1fr;
                gap: 16px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 14px;
            }
            
            .profile-info {
                display: none;
            }
        }

        @media screen and (max-width: 768px) {
            .top-banner {
                font-size: 11px;
                padding: 6px 0;
            }
            
            .navbar {
                padding: 10px 4%;
                gap: 8px;
            }
            
            .search-bar {
                order: 3;
                flex-basis: 100%;
                max-width: none;
                margin-top: 8px;
            }
            
            .mobile-filter-toggle {
                display: block;
            }
            
            .shop-container {
                grid-template-columns: 1fr;
                padding: 12px;
                gap: 0;
            }
            
            .filters-sidebar {
                display: none;
                margin-bottom: 16px;
            }
            
            .filters-sidebar.show {
                display: block;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 12px;
            }
            
            .product-image {
                height: 140px;
            }
            
            .product-info {
                padding: 12px;
            }
            
            .product-title {
                font-size: 14px;
            }
            
            .product-price {
                font-size: 14px;
            }
            
            .add-to-cart-btn {
                padding: 8px;
                font-size: 12px;
            }
            
            .products-header {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            
            .products-meta {
                justify-content: space-between;
            }
            
            .pagination a {
                padding: 6px 10px;
                font-size: 12px;
            }
            
            .notification-dropdown-menu, .profile-dropdown-menu {
                min-width: 240px;
                right: -20px;
            }
        }

        @media screen and (max-width: 480px) {
            .navbar {
                padding: 8px 3%;
            }
            
            .shop-container {
                padding: 8px;
            }
            
            .products-section {
                padding: 16px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
            }
            
            .product-image {
                height: 120px;
            }
            
            .product-info {
                padding: 10px;
            }
            
            .product-title {
                font-size: 13px;
                margin-bottom: 6px;
            }
            
            .product-category {
                font-size: 11px;
            }
            
            .product-rating {
                font-size: 12px;
                gap: 6px;
            }
            
            .product-price {
                font-size: 13px;
                margin-bottom: 8px;
            }
            
            .add-to-cart-btn {
                padding: 6px;
                font-size: 11px;
            }
            
            .stock-badge {
                font-size: 9px;
                padding: 2px 4px;
            }
            
            .filters-sidebar {
                padding: 16px;
            }
            
            .notification-dropdown-menu, .profile-dropdown-menu {
                min-width: 200px;
                right: -10px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 16px;
            }
        }

        @media screen and (max-width: 360px) {
            .products-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .pagination {
                gap: 4px;
            }
            
            .pagination a {
                padding: 4px 8px;
                font-size: 11px;
            }
        }

        @media screen and (max-width: 1024px) and (orientation: landscape) {
            .shop-container {
                grid-template-columns: 220px 1fr;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }

        @media screen and (-webkit-min-device-pixel-ratio: 2), 
               screen and (min-resolution: 192dpi) {
            .product-image {
                image-rendering: -webkit-optimize-contrast;
            }
        }
    </style>
</head>
<body>
    <div class="top-banner">
        <p>Free Shipping on Orders Over $50! <a href="#">Shop Now</a></p>
    </div>

    <nav class="navbar" id="navbar">
        <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
        <div class="nav-right">
            <?php if ($user): ?>
                <div class="notification-dropdown">
                    <button class="notification-btn" onclick="toggleNotificationDropdown()">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo htmlspecialchars($unread_count); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown-menu" id="notificationDropdown">
                        <?php if (empty($notifications)): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <p>No notifications.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo htmlspecialchars($notification['id']); ?>">
                                    <i class="fas fa-<?php echo $notification['type'] == 'order' ? 'box' : ($notification['type'] == 'sale' ? 'tag' : 'star'); ?> notification-icon"></i>
                                    <div class="notification-content">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <span class="notification-time"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cart-btn">
                    <button onclick="window.location.href='cart.php'">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge"><?php echo htmlspecialchars($cartCount); ?></span>
                        <?php endif; ?>
                    </button>
                </div>
            <?php endif; ?>
            <div class="profile-dropdown">
                <?php if ($user): ?>
                    <?php
                    // Check for unread notifications using existing $conn
                    $unread_count = 0;
                    if ($user) {
                        $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
                        if ($stmt) {
                            $stmt->bind_param("i", $user['id']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $unread_count = $result->fetch_assoc()['unread'];
                            $stmt->close();
                        } else {
                            error_log("Failed to prepare notification query: " . $conn->error);
                        }
                    }
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
                        <a href="inbox.php">
                            <i class="fas fa-inbox"></i> Inbox
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-dot"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="index.php"><i class="fas fa-home"></i> Home</a>
                        <?php if ($user['is_admin']): ?>
                            <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
                        <?php endif; ?>
                        <hr class="dropdown-divider">
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    <?php else: ?>
                        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign In</a>
                        <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
                        <hr class="dropdown-divider">
                        <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>" onkeypress="if(event.key==='Enter') searchProducts()">
            <i class="fas fa-search"></i>
        </div>
    </nav>

    <div class="shop-container">
        <button class="mobile-filter-toggle" onclick="toggleFilters()">
            <i class="fas fa-filter"></i> Filters
        </button>
        
        <aside class="filters-sidebar" id="filtersSidebar">
            <div class="filters-title">
                <h2>Filters</h2>
                <a href="?q=<?php echo urlencode($search_query); ?>" class="clear-filters">Clear All</a>
            </div>
            <form id="filters-form" method="GET" action="">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                <div class="filter-section">
                    <label class="filter-label" for="category">Category</label>
                    <select name="category" id="category" class="filter-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-section">
                    <label class="filter-label">Price Range</label>
                    <div class="price-range">
                        <input type="number" name="price_min" class="price-input" placeholder="Min" value="<?php echo $price_min > 0 ? htmlspecialchars($price_min) : ''; ?>" min="0" step="0.01">
                        <span>-</span>
                        <input type="number" name="price_max" class="price-input" placeholder="Max" value="<?php echo $price_max < 1000 ? htmlspecialchars($price_max) : ''; ?>" min="0" step="0.01">
                    </div>
                </div>
                <div class="filter-section">
                    <label class="filter-label" for="attribute">Attributes</label>
                    <div class="attribute-grid">
                        <?php foreach ($attributes as $attribute): ?>
                            <div class="attribute-option <?php echo $attribute_filter == $attribute ? 'selected' : ''; ?>" data-attribute="<?php echo htmlspecialchars($attribute); ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $attribute))); ?>
                            </div>
                        <?php endforeach; ?>
                        <input type="hidden" name="attribute" value="<?php echo htmlspecialchars($attribute_filter); ?>">
                    </div>
                </div>
                <div class="filter-section">
                    <label class="filter-label">Rating</label>
                    <div class="rating-filter">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <label class="rating-option">
                                <input type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $rating_filter == $i ? 'checked' : ''; ?>>
                                <?php echo str_repeat('★', $i) . str_repeat('☆', 5 - $i); ?>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <button type="submit" class="apply-filters-btn">Apply Filters</button>
            </form>
        </aside>
        
        <section class="products-section">
            <div class="products-header">
                <h1 class="products-title">Shop Products</h1>
                <div class="products-meta">
                    <span><?php echo htmlspecialchars($total_products); ?> Products Found</span>
                    <form method="GET" action="">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <input type="hidden" name="price_min" value="<?php echo htmlspecialchars($price_min); ?>">
                        <input type="hidden" name="price_max" value="<?php echo htmlspecialchars($price_max); ?>">
                        <input type="hidden" name="attribute" value="<?php echo htmlspecialchars($attribute_filter); ?>">
                        <input type="hidden" name="rating" value="<?php echo htmlspecialchars($rating_filter); ?>">
                        <select name="sort" class="sort-dropdown" onchange="this.form.submit()">
                            <option value="default" <?php echo $sort == 'default' ? 'selected' : ''; ?>>Sort by</option>
                            <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Rating: High to Low</option>
                            <option value="new" <?php echo $sort == 'new' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name: A-Z</option>
                        </select>
                    </form>
                </div>
            </div>
            
            <div class="products-grid">
                <?php if (empty($products)): ?>
                    <p>No products found.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <a href="product.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="product-card" style="text-decoration: none; color: inherit;">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            <?php if ($product['stock_quantity'] == 0): ?>
                                <span class="stock-badge out-of-stock">Out of Stock</span>
                            <?php else: ?>
                                <span class="stock-badge"><?php echo htmlspecialchars($product['stock_quantity']); ?> in Stock</span>
                            <?php endif; ?>
                            <div class="product-info">
                                <h2 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h2>
                                <span class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                <div class="product-rating">
                                    <?php echo displayStars($product['avg_rating']); ?>
                                    <span>(<?php echo htmlspecialchars($product['review_count']); ?>)</span>
                                </div>
                                <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                                <button class="add-to-cart-btn" onclick="addToCart(<?php echo htmlspecialchars($product['id']); ?>, 1); event.preventDefault();" <?php echo $product['stock_quantity'] == 0 ? 'disabled' : ''; ?>>
                                    Add to Cart
                                </button>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">« Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="<?php echo $page == $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next »</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
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
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="careers.php">Careers</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h4>Help</h4>
                <ul>
                    <li><a href="support.php">Customer Support</a></li>
                    <li><a href="shipping.php">Delivery Details</a></li>
                    <li><a href="terms.php">Terms & Conditions</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h4>FAQ</h4>
                <ul>
                    <li><a href="faq.php#account">Account</a></li>
                    <li><a href="faq.php#delivery">Manage Deliveries</a></li>
                    <li><a href="faq.php#orders">Orders</a></li>
                    <li><a href="faq.php#payments">Payments</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h4>Resources</h4>
                <ul>
                    <li><a href="blog.php">Blog</a></li>
                    <li><a href="size-guide.php">Size Guide</a></li>
                    <li><a href="care-instructions.php">Care Instructions</a></li>
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
        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
            document.getElementById('profileDropdown').classList.remove('show');
        }

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
            document.getElementById('notificationDropdown').classList.remove('show');
        }

        function toggleFilters() {
            const sidebar = document.getElementById('filtersSidebar');
            sidebar.classList.toggle('show');
        }

        function searchProducts() {
            const query = document.getElementById('searchInput').value;
            window.location.href = `?q=${encodeURIComponent(query)}`;
        }

        function addToCart(productId, quantity) {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_to_cart&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = data.cartCount;
                    } else {
                        const cartBtn = document.querySelector('.cart-btn');
                        if (cartBtn) {
                            cartBtn.insertAdjacentHTML('beforeend', `<span class="cart-badge">${data.cartCount}</span>`);
                        }
                    }
                }
            });
        }

        document.querySelectorAll('.attribute-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.attribute-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                document.querySelector('input[name="attribute"]').value = this.dataset.attribute;
                document.getElementById('filters-form').submit();
            });
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-dropdown') && !e.target.closest('.profile-dropdown')) {
                document.querySelectorAll('.notification-dropdown-menu, .profile-dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.id;
                if (notificationId) {
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_notification_read&notification_id=${notificationId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.remove('unread');
                            const badge = document.querySelector('.notification-badge');
                            if (badge) {
                                let count = parseInt(badge.textContent) - 1;
                                if (count <= 0) {
                                    badge.remove();
                                } else {
                                    badge.textContent = count;
                                }
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>