<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get current user
$user = getCurrentUser();

// Require admin access
requireAdmin();

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);

// ----- AJAX SEARCH HANDLING -----
if (isset($_POST['action']) && $_POST['action'] === 'search_products') {
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $products = [];

    try {
        $query = "
            SELECT p.id, p.name, p.price, p.description, i.stock_quantity, p.image, c.name AS category, ma.attribute AS misc_attribute
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN miscellaneous_attributes ma ON p.id = ma.product_id
            WHERE p.name LIKE ? OR c.name LIKE ?
        ";
        $stmt = $conn->prepare($query);
        $search_param = "%$search%";
        $stmt->bind_param("ss", $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Search products error: " . $e->getMessage());
    }

    header('Content-Type: application/json');
    echo json_encode($products);
    exit;
}

// ----- AJAX FETCH ORDERS -----
if (isset($_POST['action']) && $_POST['action'] === 'fetch_orders') {
    $orders = [];
    try {
        $result = $conn->query("
            SELECT o.id, o.user_id, p.name AS product_name, oi.quantity, o.total AS total, o.created_at AS order_date, o.status, u.email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            ORDER BY o.created_at DESC
        ");
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    } catch (Exception $e) {
        error_log("Fetch orders error: " . $e->getMessage());
    }

    header('Content-Type: application/json');
    echo json_encode($orders);
    exit;
}

// ----- AJAX FETCH CATEGORIES -----
if (isset($_POST['action']) && $_POST['action'] === 'fetch_categories') {
    $categories = [];
    try {
        $result = $conn->query("SELECT id, name, description FROM categories");
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    } catch (Exception $e) {
        error_log("Fetch categories error: " . $e->getMessage());
    }

    header('Content-Type: application/json');
    echo json_encode($categories);
    exit;
}

// ----- CATEGORY CREATION HANDLING -----
if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $category_description = trim($_POST['category_description']);

    if (empty($category_name)) {
        $error_message = "Category name is required.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $category_name, $category_description);
            if ($stmt->execute()) {
                $success_message = "Category added successfully.";
            } else {
                throw new Exception("Failed to add category.");
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Failed to add category: " . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

// ----- CATEGORY DELETION HANDLING -----
if (isset($_POST['delete_category'])) {
    $category_id = (int)$_POST['id'];

    try {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        if ($stmt->execute()) {
            $success_message = "Category deleted successfully.";
        } else {
            throw new Exception("Failed to delete category.");
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Failed to delete category: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// ----- PRODUCT ADDITION HANDLING -----
if (isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category_id = (int)$_POST['category_id'];
    $misc_attribute = trim($_POST['misc_attribute']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $product_description = trim($_POST['product_description']);
    $image_url = 'https://via.placeholder.com/150'; // Default image

    if (empty($product_name)) {
        $error_message = "Product name is required.";
    } else {
        // Handle file upload
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "Uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $target_file = $target_dir . basename($_FILES['image']['name']);
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($imageFileType, $allowed_types) && $_FILES['image']['size'] <= 5000000) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_url = $target_file;
                } else {
                    $error_message = "Failed to upload image.";
                }
            } else {
                $error_message = "Invalid image format or size too large.";
            }
        }

        if (!isset($error_message)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    INSERT INTO products (category_id, name, sku, price, image, description, featured, rating)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0.0)
                ");
                $sku = "PROD" . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $description = !empty($product_description) ? $product_description : "No description provided.";
                $stmt->bind_param("issdssi", $category_id, $product_name, $sku, $price, $image_url, $description, $featured);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add product.");
                }
                $product_id = $conn->insert_id;
                $stmt->close();

                $inv_stmt = $conn->prepare("INSERT INTO inventory (product_id, stock_quantity) VALUES (?, ?)");
                $inv_stmt->bind_param("ii", $product_id, $stock_quantity);
                if (!$inv_stmt->execute()) {
                    throw new Exception("Failed to add inventory.");
                }
                $inv_stmt->close();

                if (!empty($misc_attribute)) {
                    $attr_stmt = $conn->prepare("INSERT INTO miscellaneous_attributes (product_id, attribute) VALUES (?, ?)");
                    $attr_stmt->bind_param("is", $product_id, $misc_attribute);
                    if (!$attr_stmt->execute()) {
                        throw new Exception("Failed to add miscellaneous attribute.");
                    }
                    $attr_stmt->close();
                }

                $conn->commit();
                $success_message = "Product added successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to add product: " . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }
}

// ----- PRODUCT DELETION HANDLING -----
if (isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM cart WHERE product_id = $product_id");
        $conn->query("DELETE FROM order_items WHERE product_id = $product_id");
        $conn->query("DELETE FROM reviews WHERE product_id = $product_id");
        $conn->query("DELETE FROM inventory WHERE product_id = $product_id");
        $conn->query("DELETE FROM miscellaneous_attributes WHERE product_id = $product_id");

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete product.");
        }
        $stmt->close();

        $conn->commit();
        $success_message = "Product deleted successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Failed to delete product: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// ----- CAROUSEL IMAGE UPLOAD HANDLING -----
if (isset($_POST['add_carousel_image'])) {
    $title = trim($_POST['title']);
    $subtitle = trim($_POST['subtitle']);
    $link = trim($_POST['link']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image_url = 'https://via.placeholder.com/400x300'; // Default image

    if (empty($title) || empty($subtitle)) {
        $error_message = "Title and subtitle are required.";
    } else {
        // Handle file upload
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "Uploads/carousel/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $target_file = $target_dir . basename($_FILES['image']['name']);
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($imageFileType, $allowed_types) && $_FILES['image']['size'] <= 5000000) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_url = $target_file;
                } else {
                    $error_message = "Failed to upload carousel image.";
                }
            } else {
                $error_message = "Invalid image format or size too large.";
            }
        }

        if (!isset($error_message)) {
            try {
                $stmt = $conn->prepare("INSERT INTO carousel_images (title, subtitle, image, link, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssi", $title, $subtitle, $image_url, $link, $is_active);
                if ($stmt->execute()) {
                    $success_message = "Carousel image added successfully.";
                } else {
                    throw new Exception("Failed to add carousel image.");
                }
                $stmt->close();
            } catch (Exception $e) {
                $error_message = "Failed to add carousel image: " . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }
}

// ----- CAROUSEL IMAGE DELETION HANDLING -----
if (isset($_POST['delete_carousel_image'])) {
    $image_id = (int)$_POST['image_id'];

    try {
        // Fetch image path to delete file
        $stmt = $conn->prepare("SELECT image FROM carousel_images WHERE id = ?");
        $stmt->bind_param("i", $image_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $image_path = $row['image'];
            if (file_exists($image_path) && $image_path !== 'https://via.placeholder.com/400x300') {
                unlink($image_path);
            }
        }
        $stmt->close();

        // Delete from database
        $stmt = $conn->prepare("DELETE FROM carousel_images WHERE id = ?");
        $stmt->bind_param("i", $image_id);
        if ($stmt->execute()) {
            $success_message = "Carousel image deleted successfully.";
        } else {
            throw new Exception("Failed to delete carousel image.");
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Failed to delete carousel image: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// ----- TOGGLE CAROUSEL IMAGE ACTIVE STATUS -----
if (isset($_POST['toggle_carousel_image'])) {
    $image_id = (int)$_POST['image_id'];
    $is_active = (int)$_POST['is_active'];

    try {
        $stmt = $conn->prepare("UPDATE carousel_images SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $image_id);
        if ($stmt->execute()) {
            $success_message = "Carousel image status updated successfully.";
        } else {
            throw new Exception("Failed to update carousel image status.");
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Failed to update carousel image status: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// ----- PASSWORD CHANGE HANDLING -----
if (isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_security = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_security = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_security = "New password must be at least 8 characters long.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stored_password = $result->fetch_assoc()['password'];
            $stmt->close();

            if (password_verify($current_password, $stored_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user['id']);
                if ($update_stmt->execute()) {
                    $success_security = "Password changed successfully.";
                } else {
                    $error_security = "Failed to update password.";
                }
                $update_stmt->close();
            } else {
                $error_security = "Current password is incorrect.";
            }
        } catch (Exception $e) {
            $error_security = "Failed to change password: " . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

// ----- FETCH METRICS -----
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(total) as sum FROM orders")->fetch_assoc()['sum'] ?? 0;
$total_stock = $conn->query("SELECT SUM(stock_quantity) as sum FROM inventory")->fetch_assoc()['sum'] ?? 0;

// ----- FETCH SALES DATA FOR CHART -----
$sales_data = [];
$result = $conn->query("SELECT DATE_FORMAT(created_at, '%b') as month, SUM(total) as total
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY created_at");
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$totals = array_fill(0, 6, 0);
while ($row = $result->fetch_assoc()) {
    $month_index = array_search($row['month'], $months);
    if ($month_index !== false) {
        $totals[$month_index] = (float)$row['total'];
    }
}
$sales_data = [
    'labels' => $months,
    'totals' => $totals
];

// ----- FETCH CATEGORY DATA FOR CHART -----
$category_data = [];
$result = $conn->query("SELECT c.name AS category, COUNT(p.id) as count 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    GROUP BY c.id, c.name");
while ($row = $result->fetch_assoc()) {
    $category_data[$row['category']] = $row['count'];
}

// ----- FETCH PRODUCTS -----
$products = [];
$result = $conn->query("
    SELECT p.id, p.name, p.price, p.description, i.stock_quantity, p.image, c.name AS category, ma.attribute AS misc_attribute
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN miscellaneous_attributes ma ON p.id = ma.product_id
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
}

// ----- FETCH ORDERS -----
$orders = [];
$result = $conn->query("
    SELECT o.id, o.user_id, p.name AS product_name, oi.quantity, o.total AS o_total, o.created_at AS order_date, o.status, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    ORDER BY o.created_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $result->free();
}

// ----- FETCH CATEGORIES FOR FORM -----
$categories = [];
$result = $conn->query("SELECT id, name FROM categories");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// ----- FETCH CAROUSEL IMAGES -----
$carousel_images = [];
$result = $conn->query("SELECT * FROM carousel_images ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $carousel_images[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Deeken</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.min.js"></script>
    <style>
        :root {
            /* Light Mode Colors */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --shadow: rgba(0, 0, 0, 0.1);
            --accent-primary: #3b82f6;
            --accent-secondary: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --info: #8b5cf6;
        }

        [data-theme="dark"] {
            /* Dark Mode Colors */
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --shadow: rgba(0,0,0,0.3);
            --accent-primary: #3b82f6;
            --accent-secondary: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --info: #8b5cf6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s ease;
            line-height: 1.6;
        }

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
            border-bottom: 1px solid var(--border-color);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2A2AFF;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.02);
            color: #1A1AFF;
        }

        .logo i {
            font-size: 1.6rem;
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            background: rgba(255,255,255,0.9);
        }

        .search-bar input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-bar button {
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            color: white;
            cursor: pointer;
            margin-left: -50px;
            z-index: 2;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .search-bar button:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(42, 42, 255, 0.3);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .cart-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #333;
            font-weight: bold;
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
            background: #FF3F35;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-left: 4px;
        }

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
            border-color: #3b82f6;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #2A2AFF, #BDF3FF);
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
            font-weight: bold;
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

        .profile-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            min-width: 220px;
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .profile-dropdown-menu.show {
            display: block;
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
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
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

        .container {
            max-width: 1400px;
            margin: 80px auto 2rem;
            padding: 0 2rem;
        }

        .dashboard-header {
            margin-bottom: 1rem;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: #64748b;
            font-size: 1.1rem;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }

        .tab:hover {
            color: #1e293b;
            background: #f1f5f9;
        }

        .tab.active {
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .metric-icon.products { background: linear-gradient(135deg, #3b82f6, #8b5cf6); }
        .metric-icon.orders { background: linear-gradient(135deg, #10b981, #06b6d4); }
        .metric-icon.revenue { background: linear-gradient(135deg, #f59e0b, #ef4444); }
        .metric-icon.stock { background: linear-gradient(135deg, #8b5cf6, #3b82f6); }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .metric-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .metric-change {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .metric-change .positive { color: #10b981; }
        .metric-change .negative { color: #ef4444; }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
        }

        .chart-header {
            margin-bottom: 1rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .chart-section {
            color: #64748b;
            font-size: 0.9rem;
        }

        .section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e293b;
        }

        .form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: bold;
            color: #1e293b;
            font-size: 0.9rem;
        }

        .form-input, .form select, .form textarea {
            padding: 12px 16px;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            color: #1e293b;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form select:focus, .form textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .table-container.loading::before {
            content: 'Loading...';
            display: block;
            text-align: center;
            padding: 1rem;
            color: #94a3b8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e8f0;
        }

        th {
            background: #f1f5f9;
            font-weight: bold;
            color: #333;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            color: #64748b;
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: bold;
            position: relative;
            transition: opacity 0.3s ease;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .alert-dismiss {
            position: absolute;
            right: 1rem;
            cursor: pointer;
            color: inherit;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin-top: 80px;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .navbar {
                flex-direction: column;
                gap: 1rem;
            }

            .search-bar {
                margin: 0;
                max-width: 100%;
            }

            .nav-right {
                flex-direction: column;
                gap: 1rem;
            }

            .tabs {
                flex-wrap: wrap;
            }

            .tab {
                flex: 1 1 50%;
                text-align: center;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 50%;
            border-top-color: #3b82f6;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.25);
            color: #f59e0b;
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.25);
            color: #10b981;
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.25);
            color: #ef4444;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Enter product or category...">
            <button type="submit" onclick="searchProducts()"><i class="fas fa-search"></i></button>
        </div>
        <div class="nav-right">
            <a href="cart.php" class="cart-link">
                <i class="fas fa-cart-shopping"></i>
                <span class="cart-text">Cart</span>
                <span class="cart-count"><?php echo htmlspecialchars($cart_count); ?></span>
            </a>
            <div class="profile-dropdown">
                <?php if ($user): ?>
                    <div class="profile-trigger" onclick="toggleProfileDropdown()">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <span class="profile-greeting">Name: </span><?php echo htmlspecialchars($user['name'] ?? $user['email'] ?? 'User'); ?>
                            <span class="profile-account">My Account <i class="fas fa-chevron-down"></i></span>
                        </div>
                    </div>
                    <div class="profile-dropdown-menu" id="profileDropdown">
                        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
                        <a href="index.php"><i class="fas fa-home"></i> Home</a>
                        <?php if ($user['is_admin']): ?>
                            <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
                        <?php endif; ?>
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
                        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a>
                        <hr class="dropdown-divider">
                        <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <p class="dashboard-subtitle">Manage your e-commerce platform</p>
        </div>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>
            </div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="overview">Overview</div>
            <div class="tab" data-tab="products">Product Management</div>
            <div class="tab" data-tab="orders">Orders</div>
            <div class="tab" data-tab="carousel">Carousel</div>
            <div class="tab" data-tab="security">Security</div>
        </div>

        <!-- Overview Tab -->
        <div id="overview-tab" class="tab-content active">
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon products">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo htmlspecialchars($total_products); ?></div>
                    <div class="metric-label">Total Products</div>
                    <div class="metric-change positive">
                        <i class="fas fa-angle-up"></i>
                        <span>Updated live</span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo htmlspecialchars($total_orders); ?></div>
                    <div class="metric-label">Total Orders</div>
                    <div class="metric-change positive">
                        <i class="fas fa-angle-up"></i>
                        <span>Updated live</span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon revenue">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="metric-value">$<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-change positive">
                        <i class="fas fa-angle-up"></i>
                        <span>Updated live</span>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon stock">
                            <i class="fas fa-warehouse"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo htmlspecialchars(number_format($total_stock)); ?></div>
                    <div class="metric-label">Total Stock</div>
                    <div class="metric-change positive">
                        <i class="fas fa-angle-up"></i>
                        <span>Updated live</span>
                    </div>
                </div>
            </div>
            <div class="charts-grid">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Sales Analytics</h3>
                        <div class="chart-subtitle">Monthly sales performance</div>
                    </div>
                    <canvas id="salesChart"></canvas>
                </div>
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Product Categories</h3>
                        <p class="chart-subtitle">Stock by category</p>
                    </div>
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Product Management Tab -->
        <div id="products-tab" class="tab-content">
            <!-- Category Management -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Category Management</h2>
                    <button class="btn" onclick="toggleCategoryForm()">
                        <i class="fas fa-plus"></i>
                        Add Category
                    </button>
                </div>
                <div id="categoryForm" style="display: none;">
                    <form method="POST" id="categoryFormElement">
                        <div class="form-group">
                            <label class="form-label">Category Name</label>
                            <input type="text" class="form-input" name="category_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input" name="category_description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_category" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Save Category
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelCategoryForm()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
                <div class="table-container" id="categoryTable">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoryTableBody">
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['id']); ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description'] ? substr($category['description'], 0, 50) . '...' : 'No description'); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($category['id']); ?>">
                                            <button type="submit" name="delete_category" class="btn btn-danger">
                                                <i class="fas fa-trash"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Product Management -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Product Management</h2>
                    <button class="btn" onclick="toggleProductForm()">
                        <i class="fas fa-plus"></i>
                        Add Product
                    </button>
                </div>
                <div id="productForm" style="display: none;">
                    <form class="form" method="POST" id="productFormElement" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-input" name="product_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price ($)</label>
                            <input type="number" class="form-input" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" class="form-input" name="stock_quantity" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <select class="form-input" name="category_id" id="categorySelect" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-primary" onclick="toggleCategoryForm()">
                                    <i class="fas fa-plus"></i>
                                    New Category
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Miscellaneous Attribute</label>
                            <select class="form-input" name="misc_attribute">
                                <option value="">None</option>
                                <option value="new_arrival">New Arrival</option>
                                <option value="featured">Featured</option>
                                <option value="trending">Trending</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Featured Product</label>
                            <input type="checkbox" name="featured" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-input" name="image" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Description</label>
                            <textarea class="form-input" name="product_description" rows="5" placeholder="Enter product description"></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_product" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Save Product
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelProductForm()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
                <div class="table-container" id="productTable">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Misc Attribute</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image"></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['stock_quantity'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($product['category'] ?? 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($product['description'] ? substr($product['description'], 0, 50) . '...' : 'No description'); ?></td>
                                    <td><?php echo htmlspecialchars($product['misc_attribute'] ? ucwords(str_replace('_', ' ', $product['misc_attribute'])) : 'None'); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                            <button type="submit" name="delete_product" class="btn btn-danger">
                                                <i class="fas fa-trash"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Orders Tab -->
        <div id="orders-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Order Management</h2>
                    <button class="btn btn-secondary" onclick="refreshOrders()">
                        <i class="fas fa-refresh"></i>
                        Refresh
                    </button>
                </div>
                <div class="table-container" id="ordersTable">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['email']); ?></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                    <td>$<?php echo number_format($order['o_total'], 2); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars(strtolower($order['status'])); ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Carousel Management Tab -->
        <div id="carousel-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Hero Carousel Management</h2>
                    <button class="btn btn-primary" onclick="toggleCarouselForm()">
                        <i class="fas fa-plus"></i>
                        Add Item
                    </button>
                </div>
                <div id="carouselForm" style="display: none;">
                    <form class="form" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-input" name="title" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subtitle</label>
                            <textarea class="form-input" name="subtitle" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Link (Optional)</label>
                            <input type="text" class="form-input" name="link" placeholder="e.g., #products">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-input" name="image" accept="image/*" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Active</label>
                            <input type="checkbox" name="is_active" value="1" checked>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_carousel_image" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Save Image
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelCarouselForm()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
                <div class="table-container" id="carouselTable">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Subtitle</th>
                                <th>Link</th>
                                <th>Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="carouselTableBody">
                            <?php foreach ($carousel_images as $image): ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($image['image']); ?>" alt="<?php echo htmlspecialchars($image['title']); ?>" class="product-image"></td>
                                    <td><?php echo htmlspecialchars($image['title']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($image['subtitle'], 0, 50)) . (strlen($image['subtitle']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars($image['link'] ?: 'None'); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="image_id" value="<?php echo htmlspecialchars($image['id']); ?>">
                                            <input type="hidden" name="is_active" value="<?php echo ($image['is_active'] ? 0 : 1); ?>">
                                            <button type="submit" name="toggle_carousel_image" class="btn btn-secondary">
                                                <i class="fas fa-<?php echo ($image['is_active'] ? 'check' : 'times'); ?>"></i>
                                                <?php echo ($image['is_active'] ? 'Deactivate' : 'Activate'); ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                            <input type="hidden" name="image_id" value="<?php echo htmlspecialchars($image['id']); ?>">
                                            <button type="submit" name="delete_carousel_image" class="btn btn-danger">
                                                <i class="fas fa-trash"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Security Settings Tab -->
        <div id="security-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Security Settings</h2>
                </div>
                <?php if (isset($success_security)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_security); ?>
                        <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>
                    </div>
                <?php elseif (isset($error_security)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_security); ?>
                        <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>
                    </div>
                <?php endif; ?>
                <form class="form" method="POST">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-input" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-input" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-input" name="confirm_password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-lock"></i>
                            Change Password
                        </button>
                    </div>
                </form>
                <p class="dashboard-subtitle">Note: Additional security features like Two-Factor Authentication (2FA) and audit logs will be added in future updates.</p>
            </div>
        </div>
    </div>

    <script>
        // Theme Management
        function toggleTheme() {
            const body = document.body;
            const savedTheme = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', savedTheme);
            localStorage.setItem('theme', savedTheme);
        }

        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
        }

        // Tab Switching
        function switchTab(tabId) {
            console.log('Switching to tab:', tabId);
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.querySelector(`.tab[data-tab="${tabId}"]`).classList.add('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
            if (tabId === 'overview') {
                initChart();
            }
        }

        // Initialize Chart
        function initChart() {
            try {
                if (window.salesChartInstance) window.salesChartInstance.destroy();
                if (window.categoryChartInstance) window.categoryChartInstance.destroy();

                const salesCtx = document.getElementById('salesChart').getContext('2d');
                window.salesChartInstance = new Chart(salesCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($sales_data['labels']); ?>,
                        datasets: [{
                            label: 'Sales',
                            data: <?php echo json_encode($sales_data['totals']); ?>,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.1)' }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });

                const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                window.categoryChartInstance = new Chart(categoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_keys($category_data)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_values($category_data)); ?>,
                            backgroundColor: [
                                '#3b82f6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            } catch (error) {
                console.error('Chart initialization error:', error);
            }
        }

        // Search Products
        function searchProducts() {
            console.log('Searching products...');
            const searchInput = document.getElementById('searchInput');
            const search = searchInput.value.trim();
            const tbody = document.getElementById('productTableBody');
            const tableContainer = document.getElementById('productTable');

            tableContainer.classList.add('loading');

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=search_products&search=${encodeURIComponent(search)}`
            })
            .then(response => response.json())
            .then(products => {
                tbody.innerHTML = '';
                if (products.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">No products found.</td></tr>';
                } else {
                    products.forEach(product => {
                        const row = `
                            <tr>
                                <td><img src="${product.image}" alt="${product.name}" class="product-image"></td>
                                <td>${product.name}</td>
                                <td>$${parseFloat(product.price).toFixed(2)}</td>
                                <td>${product.stock_quantity || 0}</td>
                                <td>${product.category || 'None'}</td>
                                <td>${product.description ? product.description.substring(0, 50) + (product.description.length > 50 ? '...' : '') : 'No description'}</td>
                                <td>${product.misc_attribute ? product.misc_attribute.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'None'}</td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="product_id" value="${product.id}">
                                        <button type="submit" name="delete_product" class="btn btn-danger">
                                            <i class="fas fa-trash"></i>
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center;">Error loading products.</td></tr>';
            })
            .finally(() => {
                tableContainer.classList.remove('loading');
            });
        }

        // Real-time search
        let searchDebounceTimeout;
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimeout);
            searchDebounceTimeout = setTimeout(() => {
                searchProducts();
                switchTab('products');
            }, 500);
        });

        // Toggle Forms
        function toggleCategoryForm() {
            const form = document.getElementById('categoryForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'none') {
                form.querySelector('form').reset();
            } else {
                refreshCategories();
            }
        }

        function cancelCategoryForm() {
            toggleCategoryForm();
            refreshCategories();
        }

        function toggleProductForm() {
            const form = document.getElementById('productForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                form.querySelector('form').reset();
            }
        }

        function cancelProductForm() {
            toggleProductForm();
        }

        function toggleCarouselForm() {
            const form = document.getElementById('carouselForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                form.querySelector('form').reset();
            }
        }

        function cancelCarouselForm() {
            toggleCarouselForm();
        }

        // Refresh Orders
        function refreshOrders() {
            console.log('Refreshing orders...');
            const tableBody = document.getElementById('ordersTableBody');
            const tableContainer = document.getElementById('ordersTable');
            tableContainer.classList.add('loading');

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=fetch_orders'
            })
            .then(response => response.json())
            .then(orders => {
                tableBody.innerHTML = '';
                if (orders.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No orders found.</td></tr>';
                } else {
                    orders.forEach(order => {
                        const row = `
                            <tr>
                                <td>#${order.id}</td>
                                <td>${order.email}</td>
                                <td>${order.product_name}</td>
                                <td>${order.quantity}</td>
                                <td>$${parseFloat(order.total).toFixed(2)}</td>
                                <td>${new Date(order.order_date).toISOString().split('T')[0]}</td>
                                <td>
                                    <span class="status-badge status-${order.status.toLowerCase()}">
                                        ${order.status}
                                    </span>
                                </td>
                            </tr>
                        `;
                        tableBody.innerHTML += row;
                    });
                }
            })
            .catch(error => {
                console.error('Orders refresh error:', error);
                tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Error loading orders.</td></tr>';
            })
            .finally(() => {
                tableContainer.classList.remove('loading');
            });
        }

        // Refresh Categories
        function refreshCategories() {
            console.log('Refreshing categories...');
            const tableBody = document.getElementById('categoryTableBody');
            const select = document.getElementById('categorySelect');
            const tableContainer = document.getElementById('categoryTable');

            tableContainer.classList.add('loading');

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=fetch_categories'
            })
            .then(response => response.json())
            .then(categories => {
                // Update table
                tableBody.innerHTML = '';
                if (categories.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center;">No categories found.</td></tr>';
                } else {
                    categories.forEach(category => {
                        const row = `
                            <tr>
                                <td>${category.id}</td>
                                <td>${category.name}</td>
                                <td>${category.description ? category.description.substring(0, 50) + (category.description.length > 50 ? '...' : '') : 'No description'}</td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                        <input type="hidden" name="id" value="${category.id}">
                                        <button type="submit" name="delete_category" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        `;
                        tableBody.innerHTML += row;
                    });
                }

                // Update select dropdown
                select.innerHTML = '<option value="">Select Category</option>';
                categories.forEach(category => {
                    select.innerHTML += `<option value="${category.id}">${category.name}</option>`;
                });
            })
            .catch(error => {
                console.error('Categories refresh error:', error);
                tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Error loading categories.</td></tr>';
            })
            .finally(() => {
                tableContainer.classList.remove('loading');
            });
        }

        // Dismiss Alert
        function dismissAlert(element) {
            const alert = element.closest('.alert');
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }

        // Toggle Profile Dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            const dropdown = document.getElementById('profileDropdown');
            const trigger = document.querySelector('.profile-trigger');
            if (!trigger.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Initialize
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    switchTab('overview'); // Default to overview tab
    initChart(); // Initialize charts for overview tab

    // Add event listeners for tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.getAttribute('data-tab');
            switchTab(tabId);
        });
    });

    // Initialize search input if needed
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchProducts();
                switchTab('products');
            }
        });
    }

    // Refresh categories on load to ensure dropdown is populated
    refreshCategories();

    // Handle form submissions with loading state
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="loading"></span> Processing...';
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = submitButton.getAttribute('data-original-text') || submitButton.innerHTML;
                }, 2000); // Simulate processing (remove in production with actual async handling)
            }
        });
    });

    // Store original button text for restoration
    document.querySelectorAll('button[type="submit"]').forEach(button => {
        button.setAttribute('data-original-text', button.innerHTML);
    });

    // Handle window resize for responsive charts
    window.addEventListener('resize', () => {
        if (window.salesChartInstance) window.salesChartInstance.resize();
        if (window.categoryChartInstance) window.categoryChartInstance.resize();
    });

    console.log('Admin panel initialized');
});
</script>
</body>
</html>