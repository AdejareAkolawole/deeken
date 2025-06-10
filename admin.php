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

    // Prepare search query
    $query = "
        SELECT p.id, p.name, p.price, i.stock_quantity, p.image, p.category
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE p.name LIKE ? OR p.category LIKE ?
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

    // Output JSON response
    header('Content-Type: application/json');
    echo json_encode($products);
    exit;
}

// ----- AJAX FETCH ORDERS -----
if (isset($_POST['action']) && $_POST['action'] === 'fetch_orders') {
    $orders = [];
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
    header('Content-Type: application/json');
    echo json_encode($orders);
    exit;
}

// ----- PRODUCT ADDITION HANDLING -----
if (isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category_id = (int)$_POST['category_id'];
    $image_url = 'https://via.placeholder.com/150'; // Default image

    // Handle file upload
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . basename($_FILES['image']['name']);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowed_types) && $_FILES['image']['size'] <= 5000000) { // 5MB limit
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_url = $target_file;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image format or size too large.";
        }
    }

    // Begin transaction
    $conn->begin_transaction();
    try {
        // Insert product
        $stmt = $conn->prepare("
            INSERT INTO products (category_id, name, sku, price, image, description, category)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $sku = "PROD" . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $description = "Description for " . $product_name;
        $category_name = $conn->query("SELECT name FROM categories WHERE id = $category_id")->fetch_assoc()['name'] ?? 'Unknown';
        $stmt->bind_param("issdsss", $category_id, $product_name, $sku, $price, $image_url, $description, $category_name);
        if (!$stmt->execute()) {
            throw new Exception("Failed to add product.");
        }
        $product_id = $conn->insert_id;
        $stmt->close();

        // Insert inventory
        $inv_stmt = $conn->prepare("INSERT INTO inventory (product_id, stock_quantity) VALUES (?, ?)");
        $inv_stmt->bind_param("ii", $product_id, $stock_quantity);
        if (!$inv_stmt->execute()) {
            throw new Exception("Failed to add inventory.");
        }
        $inv_stmt->close();

        // Commit transaction
        $conn->commit();
        $success = "Product added successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Failed to add product: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// ----- PRODUCT DELETION HANDLING -----
if (isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];

    // Begin transaction
    $conn->begin_transaction();
    try {
        // Delete associated records
        $conn->query("DELETE FROM cart WHERE product_id = $product_id");
        $conn->query("DELETE FROM order_items WHERE product_id = $product_id");
        $conn->query("DELETE FROM reviews WHERE product_id = $product_id");
        $conn->query("DELETE FROM inventory WHERE product_id = $product_id");

        // Delete product
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete product.");
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();
        $success = "Product deleted successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Failed to delete product: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

// ----- PASSWORD CHANGE HANDLING -----
if (isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_security = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_security = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_security = "New password must be at least 8 characters long.";
    } else {
        // Fetch current user's password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $stored_password = $result->fetch_assoc()['password'];
        $stmt->close();

        // Verify current password
        if (password_verify($current_password, $stored_password)) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password
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
    }
}

// ----- FETCH METRICS -----
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total) as sum FROM orders")->fetch_assoc()['sum'] ?? 0;
$total_stock = $conn->query("SELECT SUM(stock_quantity) as sum FROM inventory")->fetch_assoc()['sum'] ?? 0;

// ----- FETCH SALES DATA FOR CHART -----
$sales_data = [];
$result = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b') as month, SUM(total) as total
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY created_at
");
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
$result = $conn->query("SELECT category, COUNT(*) as count FROM products GROUP BY category");
while ($row = $result->fetch_assoc()) {
    $category_data[$row['category']] = $row['count'];
}

// ----- FETCH PRODUCTS -----
$products = [];
$result = $conn->query("
    SELECT p.id, p.name, p.price, i.stock_quantity, p.image, p.category
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --shadow: rgba(0, 0, 0, 0.3);
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

        /* Navigation */
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
            font-weight: 600;
            color: #2A2AFF;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
            color: #1A1AFF;
        }

        .logo i {
            font-size: 1.6rem;
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            background: rgba(255, 255, 255, 0.9);
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
            font-weight: 500;
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
            margin-left: -2px;
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
            border-color: #2A2AFF;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
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

        .hamburger {
            display: none;
        }

        /* Main Content */
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
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }

        .tab:hover {
            color: var(--text-primary);
            background: var(--bg-tertiary);
        }

        .tab.active {
            color: var(--accent-primary);
            border-bottom: 2px solid var(--accent-primary);
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Metrics Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--shadow);
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
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

        .metric-icon.products { background: linear-gradient(135deg, var(--accent-primary), var(--info)); }
        .metric-icon.orders { background: linear-gradient(135deg, var(--success), var(--accent-secondary)); }
        .metric-icon.revenue { background: linear-gradient(135deg, var(--warning), var(--error)); }
        .metric-icon.stock { background: linear-gradient(135deg, var(--info), var(--accent-primary)); }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .metric-label {
            color: var(--text-secondary);
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

        .metric-change.positive { color: var(--success); }
        .metric-change.negative { color: var(--error); }

        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .chart-header {
            margin-bottom: 1rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .chart-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Sections */
        .section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px var(--shadow);
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
            color: var(--text-primary);
        }

        /* Forms */
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
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .form-input {
            padding: 12px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-family: 'Poppins', sans-serif;
            display: flex;
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
            background: linear-gradient(135deg, var(--error), #dc2626);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .table-container.loading {
            display: block;
            position: relative;
        }

        .table-container.loading:before {
            content: 'Loading...';
            display: block;
            text-align: center;
            padding: 1rem;
            color: var(--text-muted);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-primary);
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background: var(--bg-tertiary);
            font-weight: bold;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            color: var(--text-secondary);
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: bold;
            position: relative;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0);
            color: var(--error);
        }

        .alert-dismiss {
            position: absolute;
            right: 1rem;
            cursor: pointer;
            color: inherit;
            font-size: 1rem;
        }

        /* Responsive */
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

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            border-top-color: var(--accent-primary);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search products...">
            <button type="button" onclick="searchProducts()"><i class="fas fa-search"></i></button>
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

    <!-- Main Content -->
    <div class="container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <p class="dashboard-subtitle">Monitor and manage your e-commerce platform</p>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('overview')">Overview</div>
            <div class="tab" onclick="switchTab('products')">Product Management</div>
            <div class="tab" onclick="switchTab('orders')">Order Management</div>
            <div class="tab" onclick="switchTab('security')">Security</div>
        </div>

        <!-- Tab Content -->
        <div id="overview" class="tab-content active">
            <!-- Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon products">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $total_products; ?></div>
                    <div class="metric-label">Total Products</div>
                    <div class="metric-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>Updated live</span>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $total_orders; ?></div>
                    <div class="metric-label">Total Orders</div>
                    <div class="metric-change positive">
                        <i class="fas fa-arrow-up"></i>
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
                        <i class="fas fa-arrow-up"></i>
                        <span>Updated live</span>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-icon stock">
                            <i class="fas fa-warehouse"></i>
                        </div>
                    </div>
                    <div class="metric-value"><?php echo $total_stock; ?></div>
                    <div class="metric-label">Total Stock</div>
                    <div class="metric-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>Updated live</span>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Sales Analytics</h3>
                        <p class="chart-subtitle">Monthly sales performance</p>
                    </div>
                    <canvas id="salesChart" width="400" height="200"></canvas>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Product Categories</h3>
                        <p class="chart-subtitle">Distribution by category</p>
                    </div>
                    <canvas id="categoryChart" width="300" height="200"></canvas>
                </div>
            </div>
        </div>

        <div id="products" class="tab-content">
            <!-- Product Management -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Product Management</h2>
                    <button class="btn" onclick="toggleProductForm()">
                        <i class="fas fa-plus"></i>
                        Add Product
                    </button>
                </div>

                <div id="alertContainer">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success); ?>
                            <span class="alert-dismiss" onclick="this.parentElement.remove()">×</span>
                        </div>
                    <?php elseif (isset($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <span class="alert-dismiss" onclick="this.parentElement.remove()">×</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="productForm" style="display: none;">
                    <form class="form" method="POST" enctype="multipart/form-data">
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
                            <select class="form-input" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-input" name="image" accept="image/*">
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_product" class="btn">
                                <i class="fas fa-save"></i>
                                Save Product
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleProductForm()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Category</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image"></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $product['stock_quantity'] ?? 0; ?></td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
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

        <div id="orders" class="tab-content">
            <!-- Order Management -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Order Management</h2>
                    <button class="btn btn-secondary" onclick="refreshOrders()">
                        <i class="fas fa-refresh"></i>
                        Refresh
                    </button>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Sales</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="salesTableBody">
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['email']); ?></td>
                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                <td><?php echo $order['quantity']; ?></td>
                                <td>$<?php echo number_format($order['o_total'], 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
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

        <div id="security" class="tab-content">
            <!-- Security Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Security Settings</h2>
                </div>

                <div id="alertContainerSecurity">
                    <?php if (isset($success_security)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($success_security); ?>
                            <span class="alert-dismiss" onclick="this.parentElement.remove()">×</span>
                        </div>
                    <?php elseif (isset($error_security)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error_security); ?>
                            <span class="alert-dismiss" onclick="this.parentElement.remove()">×</span>
                        </div>
                    <?php endif; ?>
                </div>

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
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-input" name="confirm_password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="change_password" class="btn">
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
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            // Add active class to selected tab and content
            document.querySelector(`[onclick="switchTab('${tabId}')"]`).classList.add('active');
            document.getElementById(tabId).classList.add('active');

            // Re-initialize charts if switching to overview
            if (tabId === 'overview') {
                initCharts();
            }
        }

        // Initialize Charts
        function initCharts() {
            // Destroy existing charts to prevent duplicates
            if (window.salesChartInstance) window.salesChartInstance.destroy();
            if (window.categoryChartInstance) window.categoryChartInstance.destroy();

            // Sales Chart
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
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            window.categoryChartInstance = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_keys($category_data)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($category_data)); ?>,
                        backgroundColor: [
                            '#3b82f6',
                            '#06b6d4',
                            '#10b981',
                            '#f59e0b',
                            '#ef4444',
                            '#8b5cf6'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Search Products
        function searchProducts() {
            const search = document.getElementById('searchInput').value;
            const tbody = document.getElementById('productTableBody');
            const tableContainer = tbody.parentElement.parentElement;

            tableContainer.classList.add('loading');

            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=search_products&search=${encodeURIComponent(search)}`
            })
            .then(response => response.json())
            .then(products => {
                tbody.innerHTML = '';
                if (products.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No products found.</td></tr>';
                } else {
                    products.forEach(product => {
                        const row = `
                            <tr>
                                <td><img src="${product.image}" alt="${product.name}" class="product-image"></td>
                                <td>${product.name}</td>
                                <td>$${parseFloat(product.price).toFixed(2)}</td>
                                <td>${product.stock_quantity || 0}</td>
                                <td>${product.category}</td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="product_id" value="${product.id}">
                                        <button type="submit" name="delete_product" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Delete
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
                console.error('Error:', error);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Error loading products.</td></tr>';
            })
            .finally(() => {
                tableContainer.classList.remove('loading');
            });
        }

        // Real-time search
        let debounceTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(searchProducts, 300);
            switchTab('products'); // Switch to products tab on search
        });

        // Toggle Product Form
        function toggleProductForm() {
            const form = document.getElementById('productForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        // Refresh Orders
        function refreshOrders() {
            const tbody = document.getElementById('salesTableBody');
            const tableContainer = tbody.parentElement.parentElement;

            tableContainer.classList.add('loading');

            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=fetch_orders`
            })
            .then(response => response.json())
            .then(orders => {
                tbody.innerHTML = '';
                if (orders.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No orders found.</td></tr>';
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
                        tbody.innerHTML += row;
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Error loading orders.</td></tr>';
            })
            .finally(() => {
                tableContainer.classList.remove('loading');
            });
        }

        // Toggle Profile Dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileDropdown = document.querySelector('.profile-dropdown');
            const dropdown = document.getElementById('profileDropdown');
            if (!profileDropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            switchTab('overview'); // Default to Overview tab
            searchProducts(); // Initial product load
            refreshOrders(); // Initial order load
        });
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>