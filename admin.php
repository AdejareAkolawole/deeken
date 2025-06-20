<?php
include 'config.php';

// Enable error logging but disable display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');

// Get current user with null check
$user = getCurrentUser() ?? null;

// Require admin access
requireAdmin();

// Fetch cart count with null check
$cart_count = getCartCount($conn, $user) ?? 0;

// Fetch metrics with error handling and null coalescing
try {
    $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
    $total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
    $total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
    $total_revenue = $conn->query("SELECT SUM(total) as sum FROM orders WHERE status = 'delivered'")->fetch_assoc()['sum'] ?? 0;
    $total_stock = $conn->query("SELECT SUM(stock_quantity) as sum FROM inventory")->fetch_assoc()['sum'] ?? 0;
    $pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
} catch (Exception $e) {
    error_log('Database query error: ' . $e->getMessage());
    $total_users = $total_products = $total_orders = $total_revenue = $total_stock = $pending_orders = 0;
}

// Fetch hero section with null check
$hero_section = $conn->query("SELECT id, title, description, button_text, main_image, sparkle_image_1, sparkle_image_2 FROM hero_section LIMIT 1")->fetch_assoc() ?? [];

// Fetch products
$products = [];
try {
    $result = $conn->query("
        SELECT p.id, p.name, p.sku, p.price, p.description, i.stock_quantity, p.image, c.name AS category, ma.attribute AS misc_attribute, p.featured
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN miscellaneous_attributes ma ON p.id = ma.product_id
    ");
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
} catch (Exception $e) {
    error_log('Products query error: ' . $e->getMessage());
}

// Fetch categories
$categories = [];
try {
    $result = $conn->query("SELECT id, name, description, created_at FROM categories");
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
} catch (Exception $e) {
    error_log('Categories query error: ' . $e->getMessage());
}

// Fetch users
$users = [];
try {
    $result = $conn->query("SELECT id, email, full_name, phone, is_admin, created_at FROM users");
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} catch (Exception $e) {
    error_log('Users query error: ' . $e->getMessage());
}

// Fetch orders
$orders = [];
try {
    $result = $conn->query("
        SELECT o.id, o.user_id, u.full_name, o.total, o.delivery_fee, o.status, o.created_at, o.address_id
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY FIELD(o.status, 'processing', 'pending', 'shipped', 'delivered', 'cancelled')
    ");
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
} catch (Exception $e) {
    error_log('Orders query error: ' . $e->getMessage());
}

// Fetch reviews
$reviews = [];
try {
    $result = $conn->query("
        SELECT r.id, r.product_id, p.name AS product_name, r.user_id, u.full_name, r.rating, r.review_text, r.created_at
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        JOIN users u ON r.user_id = u.id
    ");
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
} catch (Exception $e) {
    error_log('Reviews query error: ' . $e->getMessage());
}

// Fetch inventory
$inventory = [];
try {
    $result = $conn->query("SELECT p.id, p.name, i.stock_quantity FROM products p JOIN inventory i ON p.id = i.product_id");
    while ($row = $result->fetch_assoc()) {
        $inventory[] = $row;
    }
} catch (Exception $e) {
    error_log('Inventory query error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #8b5cf6;
            --accent-color: #06b6d4;
            --background-color: #ffffff;
            --surface-color: #f8fafc;
            --card-color: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --sidebar-width: 280px;
            --font-size-base: 16px;
        }

        [data-theme="dark"] {
            --background-color: #0f172a;
            --surface-color: #1e293b;
            --card-color: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #475569;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--background-color), var(--surface-color));
            color: var(--text-primary);
            transition: all 0.3s ease;
            min-height: 100vh;
            font-size: var(--font-size-base);
        }

        .admin-container {
            display: flex;
            height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            overflow-y: auto;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h1 {
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .sidebar-header p {
            opacity: 0.8;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }

        .nav-menu {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: clamp(0.9rem, 2vw, 1rem);
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            backdrop-filter: blur(10px);
        }

        .nav-link i {
            margin-right: 1rem;
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: clamp(1rem, 3vw, 2rem);
            overflow-y: auto;
            background: var(--background-color);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(1rem, 2vw, 2rem);
            padding: clamp(0.8rem, 2vw, 1rem) clamp(1rem, 3vw, 2rem);
            background: var(--card-color);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .page-title {
            font-size: clamp(1.4rem, 3vw, 1.8rem);
            font-weight: 700;
            color: var(--text-primary);
        }

        .top-actions {
            display: flex;
            gap: clamp(0.5rem, 1.5vw, 1rem);
            align-items: center;
        }

        .theme-toggle, .home-link {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: clamp(0.4rem, 1vw, 0.5rem);
            cursor: pointer;
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-size: clamp(0.9rem, 2vw, 1rem);
        }

        .theme-toggle:hover, .home-link:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn {
            padding: clamp(0.5rem, 1.5vw, 0.75rem) clamp(1rem, 2vw, 1.5rem);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: clamp(0.85rem, 2vw, 0.95rem);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Dashboard Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(clamp(200px, 30vw, 250px), 1fr));
            gap: clamp(1rem, 2vw, 1.5rem);
            margin-bottom: clamp(1rem, 2vw, 2rem);
        }

        .dashboard-card {
            background: var(--card-color);
            padding: clamp(1rem, 2vw, 1.5rem);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .card-title {
            font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-icon {
            width: clamp(30px, 5vw, 40px);
            height: clamp(30px, 5vw, 40px);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(0.9rem, 2vw, 1.2rem);
            color: white;
        }

        .card-value {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .card-change {
            font-size: clamp(0.7rem, 1.5vw, 0.8rem);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .change-positive {
            color: var(--success-color);
        }

        .change-negative {
            color: var(--danger-color);
        }

        /* Tables */
        .table-container {
            background: var(--card-color);
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: clamp(1rem, 2vw, 2rem);
            padding: clamp(1rem, 2vw, 1.5rem);
        }

        .table-header {
            padding: clamp(0.5rem, 1vw, 1rem);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .table-title {
            font-size: clamp(1rem, 2.2vw, 1.2rem);
            font-weight: 600;
            color: var(--text-primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: clamp(0.8rem, 1.5vw, 1rem) clamp(1rem, 2vw, 1.5rem);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
        }

        th {
            background: var(--surface-color);
            font-weight: 600;
            color: var(--text-primary);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            color: var(--text-secondary);
        }

        tr:hover {
            background: var(--surface-color);
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(clamp(250px, 40vw, 300px), 1fr));
            gap: clamp(1rem, 2vw, 2rem);
        }

        .form-group {
            margin-bottom: clamp(1rem, 2vw, 1.5rem);
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
        }

        .form-input, select.form-input, textarea.form-input {
            width: 100%;
            padding: clamp(0.6rem, 1.2vw, 0.75rem) clamp(0.8rem, 1.5vw, 1rem);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--background-color);
            color: var(--text-primary);
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
            transition: all 0.3s ease;
        }

        .form-input:focus, select.form-input:focus, textarea.form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        textarea.form-input {
            min-height: 100px;
            resize: vertical;
        }

        .status-badge {
            padding: clamp(0.2rem, 0.5vw, 0.25rem) clamp(0.5rem, 1vw, 0.75rem);
            border-radius: 20px;
            font-size: clamp(0.7rem, 1.5vw, 0.8rem);
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-processing {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .status-delivered {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .status-shipped {
            background: rgba(6, 182, 212, 0.1);
            color: var(--accent-color);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card-color);
            padding: clamp(1rem, 2vw, 2rem);
            border-radius: 16px;
            max-width: clamp(400px, 80vw, 600px);
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(1rem, 2vw, 1.5rem);
        }

        .modal-title {
            font-size: clamp(1.1rem, 2.5vw, 1.3rem);
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            cursor: pointer;
            color: var(--text-secondary);
        }

        /* Alerts */
        .alert {
            padding: clamp(0.8rem, 1.5vw, 1rem);
            border-radius: 8px;
            margin-bottom: clamp(0.8rem, 1.5vw, 1rem);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .alert-dismiss {
            cursor: pointer;
            font-size: clamp(1rem, 2vw, 1.2rem);
        }

        /* Section Item */
        .section-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: clamp(0.8rem, 1.5vw, 1rem);
            margin-bottom: 1rem;
            background: var(--surface-color);
        }

        .section-item .form-group {
            margin-bottom: 0.75rem;
        }

        .remove-section {
            background: var(--danger-color);
            color: white;
            padding: clamp(0.3rem, 0.8vw, 0.5rem) clamp(0.8rem, 1.5vw, 1rem);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: clamp(0.8rem, 1.8vw, 0.9rem);
            transition: all 0.3s ease;
        }

        .remove-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }

            .sidebar-header h1,
            .sidebar-header p,
            .nav-link span {
                display: none;
            }

            .nav-link {
                justify-content: center;
                padding: 0.75rem;
            }

            .nav-link i {
                margin-right: 0;
                font-size: 1.5rem;
            }

            .main-content {
                padding: 0.75rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .top-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            body {
                font-size: 14px;
            }

            .page-title {
                font-size: 1.2rem;
            }

            .btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }

            .dashboard-card {
                padding: 0.75rem;
            }

            .card-value {
                font-size: 1.5rem;
            }

            .nav-link i {
                font-size: 1.3rem;
            }
        }

        .hero-preview {
            background: var(--surface-color);
            padding: clamp(1rem, 2vw, 1.5rem);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-top: 0.5rem;
        }

        .hero-preview h1 {
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .hero-preview p {
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .hero-preview .cta-button {
            background: var(--primary-color);
            color: white;
            padding: clamp(0.5rem, 1vw, 0.75rem) clamp(1rem, 2vw, 1.5rem);
            border-radius: 8px;
            border: none;
            cursor: default;
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
        }

        .hero-image-preview {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-loading {
            opacity: 0.7;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>Deeken</h1>
                <p>Admin Panel</p>
            </div>
            <nav class="nav-menu">
                <div class="nav-item">
                    <button class="nav-link active" data-tab="dashboard"><i class="fas fa-chart-pie"></i><span>Dashboard</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="products"><i class="fas fa-box"></i><span>Products</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="categories"><i class="fas fa-tags"></i><span>Categories</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="orders"><i class="fas fa-shopping-cart"></i><span>Orders</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="users"><i class="fas fa-users"></i><span>Users</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="inventory"><i class="fas fa-warehouse"></i><span>Inventory</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="reviews"><i class="fas fa-star"></i><span>Reviews</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="settings"><i class="fas fa-cog"></i><span>Settings</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="hero"><i class="fas fa-image"></i><span>Hero Section</span></button>
                </div>
                <div class="nav-item">
                    <button class="nav-link" data-tab="static-pages"><i class="fas fa-file-alt"></i><span>Static Pages</span></button>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1 class="page-title" id="pageTitle">Dashboard</h1>
                <div class="top-actions">
                    <a href="index.php" class="home-link">
                        <i class="fas fa-home"></i>
                    </a>
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </button>
                    <button class="btn btn-primary" onclick="openQuickAdd()">
                        <i class="fas fa-plus"></i> Quick Add
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($success_message) && !empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message) && !empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>
                </div>
            <?php endif; ?>

            <!-- Dashboard Tab -->
            <div class="tab-content active" id="dashboard">
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Total Users</div>
                            <div class="card-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="card-value"><?php echo htmlspecialchars($total_users); ?></div>
                        <div class="card-change change-positive">
                            <i class="fas fa-arrow-up"></i> Updated live
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Total Products</div>
                            <div class="card-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                        <div class="card-value"><?php echo htmlspecialchars($total_products); ?></div>
                        <div class="card-change change-positive">
                            <i class="fas fa-arrow-up"></i> Updated live
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Products In Stock</div>
                            <div class="card-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <i class="fas fa-warehouse"></i>
                            </div>
                        </div>
                        <div class="card-value"><?php echo htmlspecialchars($total_stock); ?></div>
                        <div class="card-change change-positive">
                            <i class="fas fa-arrow-up"></i> Updated live
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Total Revenue</div>
                            <div class="card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <div class="card-value">$<?php echo number_format($total_revenue, 2); ?></div>
                        <div class="card-change change-positive">
                            <i class="fas fa-arrow-up"></i> Updated live
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Total Orders</div>
                            <div class="card-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                        <div class="card-value"><?php echo htmlspecialchars($total_orders); ?></div>
                        <div class="card-change change-positive">
                            <i class="fas fa-arrow-up"></i> Updated live
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-title">Pending Orders</div>
                            <div class="card-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="card-value"><?php echo htmlspecialchars($pending_orders); ?></div>
                        <div class="card-change change-positive">
                            <i class="fas fa-arrow-up"></i> Updated live
                        </div>
                    </div>
                </div>
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Recent Orders</h3>
                        <button class="btn btn-primary" onclick="switchTab('orders')">View All</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['full_name'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo number_format(($order['total'] ?? 0) + ($order['delivery_fee'] ?? 0), 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars(strtolower($order['status'] ?? 'pending')); ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status'] ?? 'Pending')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($order['created_at'] ?? 'now'))); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="viewOrder(<?php echo htmlspecialchars($order['id']); ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Products Tab -->
            <div class="tab-content" id="products">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Products</h3>
                        <div class="table-actions">
                            <input type="text" id="productSearch" class="form-input" placeholder="Search products..." oninput="searchProducts()">
                            <button class="btn btn-primary" onclick="openAddProductModal()">Add Product</button>
                        </div>
                    </div>
                    <table id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Featured</th>
                                <th>Misc Attribute</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['id'] ?? 'N/A'); ?></td>
                                    <td><img src="<?php echo htmlspecialchars($product['image'] ?? 'images/placeholder.png'); ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"></td>
                                    <td><?php echo htmlspecialchars($product['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo number_format($product['price'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['stock_quantity'] ?? 0); ?></td>
                                    <td><?php echo ($product['featured'] ?? 0) ? '<i class="fas fa-check text-success"></i>' : ''; ?></td>
                                    <td><?php echo htmlspecialchars($product['misc_attribute'] ?? 'None'); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="openEditProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?php echo htmlspecialchars($product['id']); ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Categories Tab -->
            <div class="tab-content" id="categories">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Categories</h3>
                        <button class="btn btn-primary" onclick="openAddCategoryModal()">Add Category</button>
                    </div>
                    <table id="categoriesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($category['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($category['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($category['created_at'] ?? 'now'))); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="openEditCategoryModal(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteCategory(<?php echo htmlspecialchars($category['id']); ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Orders Tab -->
            <div class="tab-content" id="orders">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Orders</h3>
                        <select id="orderStatusFilter" class="form-input" onchange="fetchOrders()">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <table id="ordersTable">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($order['full_name'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo number_format(($order['total'] ?? 0) + ($order['delivery_fee'] ?? 0), 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars(strtolower($order['status'] ?? 'pending')); ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status'] ?? 'Pending')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($order['created_at'] ?? 'now'))); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="viewOrder(<?php echo htmlspecialchars($order['id']); ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if (isset($order['status']) && $order['status'] === 'processing'): ?>
                                            <button class="btn btn-success btn-sm" onclick="openShipOrderModal(<?php echo htmlspecialchars($order['id']); ?>)">
                                                <i class="fas fa-truck"></i> Ship
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Users Tab -->
            <div class="tab-content" id="users">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Users</h3>
                        <button class="btn btn-primary" onclick="openAddUserModal()">Add User</button>
                    </div>
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Admin</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo ($user['is_admin'] ?? 0) ? '<i class="fas fa-check text-success"></i>' : ''; ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user['created_at'] ?? 'now'))); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo htmlspecialchars($user['id']); ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inventory Tab -->
            <div class="tab-content" id="inventory">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Inventory</h3>
                    </div>
                    <table id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Stock Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['stock_quantity'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reviews Tab -->
            <div class="tab-content" id="reviews">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Reviews</h3>
                    </div>
                    <table id="reviewsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>User</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($review['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($review['product_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($review['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($review['rating'] ?? 0); ?>/5</td>
                                    <td><?php echo htmlspecialchars($review['review_text'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($review['created_at'] ?? 'now'))); ?></td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" onclick="deleteReview(<?php echo htmlspecialchars($review['id']); ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="tab-content" id="settings">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Change Password</h3>
                    </div>
                    <form id="changePasswordForm" class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-input" id="currentPassword" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-input" id="newPassword" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-input" id="confirmPassword" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Hero Section Tab -->
            <div class="tab-content" id="hero">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Hero Section</h3>
                    </div>
                    <form id="heroSectionForm" class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-input" id="heroTitle" value="<?php echo htmlspecialchars($hero_section['title'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input" id="heroDescription" required><?php echo htmlspecialchars($hero_section['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Button Text</label>
                            <input type="text" class="form-input" id="heroButtonText" value="<?php echo htmlspecialchars($hero_section['button_text'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Main Image</label>
                            <input type="file" class="form-input" id="heroMainImage" accept="image/*">
                            <input type="hidden" id="existingMainImage" value="<?php echo htmlspecialchars($hero_section['main_image'] ?? 'images/hero-couple.png'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sparkle Image 1</label>
                            <input type="file" class="form-input" id="heroSparkleImage1" accept="image/*">
                            <input type="hidden" id="existingSparkle1" value="<?php echo htmlspecialchars($hero_section['sparkle_image_1'] ?? 'images/sparkle-1.png'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sparkle Image 2</label>
                            <input type="file" class="form-input" id="heroSparkleImage2" accept="image/*">
                            <input type="hidden" id="existingSparkle2" value="<?php echo htmlspecialchars($hero_section['sparkle_image_2'] ?? 'images/sparkle-2.png'); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Save Hero Section</button>
                        </div>
                    </form>
                    <div class="hero-preview">
                        <h1 id="heroPreviewTitle"><?php echo htmlspecialchars($hero_section['title'] ?? 'Welcome to Deeken'); ?></h1>
                        <p id="heroPreviewDescription"><?php echo htmlspecialchars($hero_section['description'] ?? 'Discover our awesome products.'); ?></p>
                        <button class="cta-button" id="heroPreviewButton"><?php echo htmlspecialchars($hero_section['button_text'] ?? 'Shop Now'); ?></button>
                        <div class="hero-image-preview">
                            <img id="heroPreviewMainImage" src="<?php echo htmlspecialchars($hero_section['main_image'] ?? 'images/hero-couple.png'); ?>" alt="Main Image" style="width: 100px; height: 100px; object-fit: cover;">
                            <img id="heroPreviewSparkle1" src="<?php echo htmlspecialchars($hero_section['sparkle_image_1'] ?? 'images/sparkle-1.png'); ?>" alt="Sparkle 1" style="width: 50px; height: 50px;">
                            <img id="heroPreviewSparkle2" src="<?php echo htmlspecialchars($hero_section['sparkle_image_2'] ?? 'images/sparkle-2.png'); ?>" alt="Sparkle 2" style="width: 50px; height: 50px;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Static Pages Tab -->
            <div class="tab-content" id="static-pages">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Static Pages</h3>
                    </div>
                    <form id="staticPageForm" class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Select Page</label>
                            <select id="pageSelector" class="form-input" required>
                                <option value="">Select a page</option>
                                <option value="about">About</option>
                                <option value="contact">Contact</option>
                                <option value="careers">Careers</option>
                                <option value="support">Customer Support</option>
                                <option value="shipping">Delivery Details</option>
                                <option value="terms">Terms & Conditions</option>
                                <option value="privacy">Privacy Policy</option>
                                <option value="faq">FAQ</option>
                                <option value="blog">Blog</option>
                                <option value="size-guide">Size Guide</option>
                                <option value="care-instructions">Care Instructions</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-input" id="pageTitle" required>
                            <input type="hidden" id="pageKey">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input" id="pageDescription" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Meta Description</label>
                            <textarea class="form-input" id="pageMetaDescription" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sections</label>
                            <div id="sectionsContainer"></div>
                            <button type="button" class="btn btn-primary" onclick="addSection()">Add Section</button>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Page</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div id="notification" class="notification"></div>

        <!-- Modals -->
        <!-- Quick Add Modal -->
        <div class="modal" id="quickAddModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Quick Add</h2>
                    <button class="modal-close" onclick="closeModal('quickAddModal')">×</button>
                </div>
                <form id="quickAddForm">
                    <div class="form-group">
                        <label class="form-label">Select Type</label>
                        <select class="form-input" id="quickAddType" onchange="updateQuickAddForm()">
                            <option value="product">Product</option>
                            <option value="category">Category</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div id="quickAddFields"></div>
                    <button type="submit" class="btn btn-primary">Add</button>
                </form>
            </div>
        </div>

        <!-- Add/Edit Product Modal -->
        <div class="modal" id="productModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="productModalTitle">Add Product</h2>
                    <button class="modal-close" onclick="closeModal('productModal')">×</button>
                </div>
                <form id="productForm">
                    <input type="hidden" id="productId">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-input" id="productName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price</label>
                        <input type="number" class="form-input" id="productPrice" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" class="form-input" id="productStock" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select class="form-input" id="productCategory" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['id'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($category['name'] ?? 'N/A'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Miscellaneous Attribute</label>
                        <select class="form-input" id="productMiscAttribute">
                            <option value="">None</option>
                            <option value="new_arrival">New Arrival</option>
                            <option value="featured">Featured</option>
                            <option value="trending">Trending</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Featured</label>
                        <input type="checkbox" id="productFeatured">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" id="productDescription"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Image</label>
                        <input type="file" class="form-input" id="productImage" accept="image/*">
                        <input type="hidden" id="existingProductImage">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </form>
            </div>
        </div>

        <!-- Add/Edit Category Modal -->
        <div class="modal" id="categoryModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="categoryModalTitle">Add Category</h2>
                    <button class="modal-close" onclick="closeModal('categoryModal')">×</button>
                </div>
                <form id="categoryForm">
                    <input type="hidden" id="categoryId">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-input" id="categoryName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" id="categoryDescription"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </form>
            </div>
        </div>

        <!-- Add/Edit User Modal -->
        <div class="modal" id="userModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="userModalTitle">Add User</h2>
                    <button class="modal-close" onclick="closeModal('userModal')">×</button>
                </div>
                <form id="userForm">
                    <input type="hidden" id="userId">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" id="userEmail" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" id="userFullName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-input" id="userPhone" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" class="form-input" id="userPassword">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Admin</label>
                        <input type="checkbox" id="userIsAdmin">
                    </div>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </form>
            </div>
        </div>

        <!-- View Order Modal -->
        <div class="modal" id="orderModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Order Details</h2>
                    <button class="modal-close" onclick="closeModal('orderModal')">×</button>
                </div>
                <div id="orderDetails"></div>
            </div>
        </div>

        <!-- Ship Order Modal -->
        <div class="modal" id="shipOrderModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Ship Order</h2>
                    <button class="modal-close" onclick="closeModal('shipOrderModal')">×</button>
                </div>
                <form id="shipOrderForm">
                    <input type="hidden" id="shipOrderId">
                    <div class="form-group">
                        <label class="form-label">Estimated Delivery Days</label>
                        <input type="number" class="form-input" id="estimatedDeliveryDays" required min="1">
                    </div>
                    <button type="submit" class="btn btn-primary">Mark as Shipped</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Theme handling with validation
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('themeIcon');
            if (!body || !themeIcon) return;
            if (body.dataset.theme === 'dark') {
                body.dataset.theme = 'light';
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            } else {
                body.dataset.theme = 'dark';
                themeIcon.classList.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            }
        }

        // Apply saved theme
        if (localStorage.getItem('theme') === 'dark') {
            toggleTheme();
        }

        // Switch tabs with validation
       function switchTab(tabId) {
    const tabElement = document.getElementById(tabId);
    if (!tabElement) {
        console.error(`Tab element with ID ${tabId} not found.`);
        showAlert('Tab not found.', 'error');
        return;
    }
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    tabElement.classList.add('active');
    const navLink = document.querySelector(`.nav-link[data-tab="${tabId}"]`);
    if (navLink) navLink.classList.add('active');
    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        pageTitle.textContent = tabId.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    if (tabId === 'orders' && document.querySelector('#ordersTable')) {
        fetchOrders();
    } else if (tabId === 'hero' && document.querySelector('#heroSectionForm')) {
        fetchHeroSection();
    } else if (tabId === 'static-pages' && document.querySelector('#staticPageForm')) {
        resetStaticPage();
    } else if (tabId === 'overview') {
        console.log('Overview tab loaded; no AJAX action defined.');
        // Add future AJAX call here if needed, e.g., fetchOverviewStats()
    }
}
        // Modal handling
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
        }
        function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('active');
    if (modalId === 'productModal') {
        const productForm = document.getElementById('productForm');
        if (productForm) productForm.reset();
    }
    if (modalId === 'categoryModal') {
        const categoryForm = document.getElementById('categoryForm');
        if (categoryForm) categoryForm.reset();
    }
    if (modalId === 'userModal') {
        const userForm = document.getElementById('userForm');
        if (userForm) userForm.reset();
    }
}

// Alert handling
function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
        <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>
    `;
    const mainContent = document.querySelector('.main-content');
    const topBar = mainContent?.querySelector('.top-bar');
    if (mainContent && topBar) {
        mainContent.insertBefore(alert, topBar.nextSibling);
        setTimeout(() => alert.remove(), 5000);
    }
}

function dismissAlert(element) {
    if (element?.parentElement) {
        element.parentElement.remove();
    }
}

// AJAX Request with improved error handling
async function sendAjaxRequest(data, url = 'ajax.php', method = 'POST') {
    const formData = new FormData();
    for (const key in data) {
        if (data[key] instanceof File) {
            formData.append(key, data[key]);
        } else if (data[key] !== undefined) {
            formData.append(key, data[key]);
        }
    }
    try {
        const response = await fetch(url, {
            method: method,
            body: method === 'POST' ? formData : undefined,
        });
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', text);
            throw new Error('Invalid JSON response');
        }
    } catch (error) {
        console.error('AJAX error:', error);
        showAlert('An error occurred while processing the request.', 'error');
        throw error;
    }
}

// Quick Add
function openQuickAdd() {
    openModal('quickAddModal');
    updateQuickAddForm();
}

function updateQuickAddForm() {
    const typeInput = document.getElementById('quickAddType');
    const fields = document.getElementById('quickAddFields');
    if (!typeInput || !fields) return;
    const type = typeInput.value;
    if (type === 'product') {
        fields.innerHTML = `
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" class="form-input" id="quickProductName" required>
            </div>
            <div class="form-group">
                <label class="form-label">Price</label>
                <input type="number" class="form-input" id="quickProductPrice" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Stock Quantity</label>
                <input type="number" class="form-input" id="quickProductStock" required>
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select class="form-input" id="quickProductCategory" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id'] ?? ''); ?>">
                            <?php echo htmlspecialchars($category['name'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        `;
    } else if (type === 'category') {
        fields.innerHTML = `
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" class="form-input" id="quickCategoryName" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input" id="quickCategoryDescription"></textarea>
            </div>
        `;
    } else if (type === 'user') {
        fields.innerHTML = `
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-input" id="quickUserEmail" required>
            </div>
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-input" id="quickUserFullName" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="text" class="form-input" id="quickUserPhone" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-input" id="quickUserPassword" required>
            </div>
            <div class="form-group">
                <label class="form-label">Admin</label>
                <input type="checkbox" id="quickUserIsAdmin">
            </div>
        `;
    }
}

document.getElementById('quickAddForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const typeInput = document.getElementById('quickAddType');
    if (!typeInput) return;
    const type = typeInput.value;
    let data = { action: '' };
    try {
        if (type === 'product') {
            data = {
                action: 'add_product',
                name: document.getElementById('quickProductName')?.value || '',
                price: document.getElementById('quickProductPrice')?.value || 0,
                stock_quantity: document.getElementById('quickProductStock')?.value || 0,
                category_id: document.getElementById('quickProductCategory')?.value || '',
            };
        } else if (type === 'category') {
            data = {
                action: 'add_category',
                name: document.getElementById('quickCategoryName')?.value || '',
                description: document.getElementById('quickCategoryDescription')?.value || '',
            };
        } else if (type === 'user') {
            data = {
                action: 'add_user',
                email: document.getElementById('quickUserEmail')?.value || '',
                full_name: document.getElementById('quickUserFullName')?.value || '',
                phone: document.getElementById('quickUserPhone')?.value || '',
                password: document.getElementById('quickUserPassword')?.value || '',
                is_admin: document.getElementById('quickUserIsAdmin')?.checked ? 1 : 0,
            };
        }
        const response = await sendAjaxRequest(data);
        if (response.success) {
            showAlert(response.message, 'success');
            closeModal('quickAddModal');
            if (type === 'product') searchProducts();
            if (type === 'category') fetchCategories();
            if (type === 'user') fetchUsers();
        } else {
            showAlert(response.message || 'Failed to add item.', 'error');
        }
    } catch (error) {
        showAlert('Failed to add item.', 'error');
    }
});

// Products
function openAddProductModal() {
    const modalTitle = document.getElementById('productModalTitle');
    const productForm = document.getElementById('productForm');
    const productId = document.getElementById('productId');
    const existingImage = document.getElementById('existingProductImage');
    if (modalTitle && productForm && productId && existingImage) {
        modalTitle.textContent = 'Add Product';
        productForm.reset();
        productId.value = '';
        existingImage.value = '';
        openModal('productModal');
    }
}

function openEditProductModal(product) {
    if (!product) return;
    const modalTitle = document.getElementById('productModalTitle');
    const productId = document.getElementById('productId');
    const productName = document.getElementById('productName');
    const productPrice = document.getElementById('productPrice');
    const productStock = document.getElementById('productStock');
    const productCategory = document.getElementById('productCategory');
    const productMiscAttribute = document.getElementById('productMiscAttribute');
    const productFeatured = document.getElementById('productFeatured');
    const productDescription = document.getElementById('productDescription');
    const existingImage = document.getElementById('existingProductImage');
    if (modalTitle && productId && productName && productPrice && productStock && productCategory &&
        productMiscAttribute && productFeatured && productDescription && existingImage) {
        modalTitle.textContent = 'Edit Product';
        productId.value = product.id || '';
        productName.value = product.name || '';
        productPrice.value = product.price || 0;
        productStock.value = product.stock_quantity || 0;
        productCategory.value = product.category_id || '';
        productMiscAttribute.value = product.misc_attribute || '';
        productFeatured.checked = product.featured == 1;
        productDescription.value = product.description || '';
        existingImage.value = product.image || '';
        openModal('productModal');
    }
}

document.getElementById('productForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    if (!button) return;
    button.classList.add('btn-loading');
    button.disabled = true;
    const data = {
        action: document.getElementById('productId').value ? 'edit_product' : 'add_product',
        product_id: document.getElementById('productId')?.value || '',
        name: document.getElementById('productName')?.value || '',
        price: document.getElementById('productPrice')?.value || 0,
        stock_quantity: document.getElementById('productStock')?.value || 0,
        category_id: document.getElementById('productCategory')?.value || '',
        misc_attribute: document.getElementById('productMiscAttribute')?.value || '',
        featured: document.getElementById('productFeatured')?.checked ? 1 : 0,
        description: document.getElementById('productDescription')?.value || '',
        image: document.getElementById('productImage')?.files[0] || null,
        existing_image: document.getElementById('existingProductImage')?.value || '',
    };
    try {
        const response = await sendAjaxRequest(data);
        if (response.success) {
            showAlert(response.message, 'success');
            closeModal('productModal');
            searchProducts();
        } else {
            showAlert(response.message || 'Failed to save product.', 'error');
        }
    } catch (error) {
        showAlert('Failed to save product.', 'error');
    } finally {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
});

async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    try {
        const response = await sendAjaxRequest({ action: 'delete_product', product_id: id });
        if (response.success) {
            showAlert(response.message, 'success');
            searchProducts();
        } else {
            showAlert(response.message || 'Failed to delete product.', 'error');
        }
    } catch (error) {
        showAlert('Failed to delete product.', 'error');
    }
}

async function searchProducts() {
    const search = document.getElementById('productSearch')?.value || '';
    try {
        const response = await sendAjaxRequest({ action: 'search_products', search }, 'ajax.php', 'GET');
        if (response.success) {
            const tableBody = document.querySelector('#productsTable tbody');
            if (tableBody) {
                tableBody.innerHTML = response.data.map(product => `
                    <tr>
                        <td>${product.id}</td>
                        <td><img src="${product.image || 'images/placeholder.png'}" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"></td>
                        <td>${product.name}</td>
                        <td>${product.category || 'N/A'}</td>
                        <td>$${parseFloat(product.price).toFixed(2)}</td>
                        <td>${product.stock_quantity}</td>
                        <td>${product.featured == 1 ? '<i class="fas fa-check text-success"></i>' : ''}</td>
                        <td>${product.misc_attribute || 'None'}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick='openEditProductModal(${JSON.stringify(product)})'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteProduct(${product.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        } else {
            showAlert(response.message || 'Failed to search products.', 'error');
        }
    } catch (error) {
        showAlert('Failed to search products.', 'error');
    }
}

// Categories
function openAddCategoryModal() {
    const modalTitle = document.getElementById('categoryModalTitle');
    const categoryForm = document.getElementById('categoryForm');
    const categoryId = document.getElementById('categoryId');
    if (modalTitle && categoryForm && categoryId) {
        modalTitle.textContent = 'Add Category';
        categoryForm.reset();
        categoryId.value = '';
        openModal('categoryModal');
    }
}

function openEditCategoryModal(category) {
    if (!category) return;
    const modalTitle = document.getElementById('categoryModalTitle');
    const categoryId = document.getElementById('categoryId');
    const categoryName = document.getElementById('categoryName');
    const categoryDescription = document.getElementById('categoryDescription');
    if (modalTitle && categoryId && categoryName && categoryDescription) {
        modalTitle.textContent = 'Edit Category';
        categoryId.value = category.id || '';
        categoryName.value = category.name || '';
        categoryDescription.value = category.description || '';
        openModal('categoryModal');
    }
}

document.getElementById('categoryForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    if (!button) return;
    button.classList.add('btn-loading');
    button.disabled = true;
    const data = {
        action: document.getElementById('categoryId').value ? 'edit_category' : 'add_category',
        category_id: document.getElementById('categoryId')?.value || '',
        name: document.getElementById('categoryName')?.value || '',
        description: document.getElementById('categoryDescription')?.value || '',
    };
    try {
        const response = await sendAjaxRequest(data);
        if (response.success) {
            showAlert(response.message, 'success');
            closeModal('categoryModal');
            fetchCategories();
        } else {
            showAlert(response.message || 'Failed to save category.', 'error');
        }
    } catch (error) {
        showAlert('Failed to save category.', 'error');
    } finally {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
});

async function deleteCategory(id) {
    if (!confirm('Are you sure you want to delete this category?')) return;
    try {
        const response = await sendAjaxRequest({ action: 'delete_category', category_id: id });
        if (response.success) {
            showAlert(response.message, 'success');
            fetchCategories();
        } else {
            showAlert(response.message || 'Failed to delete category.', 'error');
        }
    } catch (error) {
        showAlert('Failed to delete category.', 'error');
    }
}

async function fetchCategories() {
    try {
        const response = await sendAjaxRequest({ action: 'fetch_categories' }, 'ajax.php', 'GET');
        if (response.success) {
            const tableBody = document.querySelector('#categoriesTable tbody');
            if (tableBody) {
                tableBody.innerHTML = response.data.map(category => `
                    <tr>
                        <td>${category.id}</td>
                        <td>${category.name}</td>
                        <td>${category.description || 'N/A'}</td>
                        <td>${new Date(category.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick='openEditCategoryModal(${JSON.stringify(category)})'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteCategory(${category.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        } else {
            showAlert(response.message || 'Failed to fetch categories.', 'error');
        }
    } catch (error) {
        showAlert('Failed to fetch categories.', 'error');
    }
}

// Users
function openAddUserModal() {
    const modalTitle = document.getElementById('userModalTitle');
    const userForm = document.getElementById('userForm');
    const userId = document.getElementById('userId');
    if (modalTitle && userForm && userId) {
        modalTitle.textContent = 'Add User';
        userForm.reset();
        userId.value = '';
        openModal('userModal');
    }
}

function openEditUserModal(user) {
    if (!user) return;
    const modalTitle = document.getElementById('userModalTitle');
    const userId = document.getElementById('userId');
    const userEmail = document.getElementById('userEmail');
    const userFullName = document.getElementById('userFullName');
    const userPhone = document.getElementById('userPhone');
    const userIsAdmin = document.getElementById('userIsAdmin');
    if (modalTitle && userId && userEmail && userFullName && userPhone && userIsAdmin) {
        modalTitle.textContent = 'Edit User';
        userId.value = user.id || '';
        userEmail.value = user.email || '';
        userFullName.value = user.full_name || '';
        userPhone.value = user.phone || '';
        userIsAdmin.checked = user.is_admin == 1;
        openModal('userModal');
    }
}

document.getElementById('userForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    if (!button) return;
    button.classList.add('btn-loading');
    button.disabled = true;
    const data = {
        action: document.getElementById('userId').value ? 'edit_user' : 'add_user',
        user_id: document.getElementById('userId')?.value || '',
        email: document.getElementById('userEmail')?.value || '',
        full_name: document.getElementById('userFullName')?.value || '',
        phone: document.getElementById('userPhone')?.value || '',
        password: document.getElementById('userPassword')?.value || '',
        is_admin: document.getElementById('userIsAdmin')?.checked ? 1 : 0,
    };
    try {
        const response = await sendAjaxRequest(data);
        if (response.success) {
            showAlert(response.message, 'success');
            closeModal('userModal');
            fetchUsers();
        } else {
            showAlert(response.message || 'Failed to save user.', 'error');
        }
    } catch (error) {
        showAlert('Failed to save user.', 'error');
    } finally {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
});

async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    try {
        const response = await sendAjaxRequest({ action: 'delete_user', user_id: id });
        if (response.success) {
            showAlert(response.message, 'success');
            fetchUsers();
        } else {
            showAlert(response.message || 'Failed to delete user.', 'error');
        }
    } catch (error) {
        showAlert('Failed to delete user.', 'error');
    }
}

async function fetchUsers() {
    try {
        const response = await sendAjaxRequest({ action: 'fetch_users' }, 'ajax.php', 'GET');
        if (response.success) {
            const tableBody = document.querySelector('#usersTable tbody');
            if (tableBody) {
                tableBody.innerHTML = response.data.map(user => `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.email}</td>
                        <td>${user.full_name}</td>
                        <td>${user.phone || 'N/A'}</td>
                        <td>${user.is_admin == 1 ? '<i class="fas fa-check text-success"></i>' : ''}</td>
                        <td>${new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick='openEditUserModal(${JSON.stringify(user)})'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        } else {
            showAlert(response.message || 'Failed to fetch users.', 'error');
        }
    } catch (error) {
        showAlert('Failed to fetch users.', 'error');
    }
}

// Orders
async function viewOrder(id) {
    if (!id || isNaN(id)) {
        console.error('Invalid order ID:', id);
        showAlert('Invalid order ID.', 'error');
        return;
    }
    const orderDetails = document.getElementById('orderDetails');
    if (!orderDetails) {
        console.error('Order details element not found.');
        showAlert('Order details section not found.', 'error');
        return;
    }
    try {
        const response = await sendAjaxRequest({ action: 'fetch_order_details', order_id: id });
        if (response && response.info) {
            const order = response.info;
            order.items = response.items || [];
            orderDetails.innerHTML = `
                <p><strong>Order ID:</strong> #${order.id || 'N/A'}</p>
                <p><strong>Customer:</strong> ${order.full_name || 'N/A'}</p>
                <p><strong>Email:</strong> ${order.email || 'N/A'}</p>
                <p><strong>Phone:</strong> ${order.phone || 'N/A'}</p>
                <p><strong>Total:</strong> $${Number((order.total || 0) + (order.delivery_fee || 0)).toFixed(2)}</p>
                <p><strong>Status:</strong> <span class="status-badge status-${(order.status || 'pending').toLowerCase()}">${order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1) : 'Pending'}</span></p>
                <p><strong>Date:</strong> ${new Date(order.created_at || 'now').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                <p><strong>Address:</strong> ${order.address || 'N/A'}</p>
                <h3>Items:</h3>
                <ul>
                    ${order.items.map(item => `
                        <li>${item.name || 'N/A'} (SKU: ${item.sku || 'N/A'}, Qty: ${item.quantity || 0}, Price: $${Number(item.price || 0).toFixed(2)})</li>
                    `).join('') || '<li>No items found</li>'}
                </ul>
            `;
            openModal('orderModal');
        } else {
            showAlert(response.message || 'Failed to fetch order details.', 'error');
        }
    } catch (error) {
        console.error('View order error:', error);
        showAlert('Failed to fetch order details.', 'error');
    }
}

function openShipOrderModal(id) {
    const shipOrderId = document.getElementById('shipOrderId');
    if (shipOrderId) {
        shipOrderId.value = id;
        openModal('shipOrderModal');
    }
}

document.getElementById('shipOrderForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    if (!button) return;
    button.classList.add('btn-loading');
    button.disabled = true;
    const data = {
        action: 'ship_order',
        order_id: document.getElementById('shipOrderId')?.value || '',
        estimated_delivery_days: document.getElementById('estimatedDeliveryDays')?.value || '',
    };
    try {
        const response = await sendAjaxRequest(data);
        if (response.success) {
            showAlert(response.message, 'success');
            closeModal('shipOrderModal');
            fetchOrders();
        } else {
            showAlert(response.message || 'Failed to ship order.', 'error');
        }
    } catch (error) {
        showAlert('Failed to ship order.', 'error');
    } finally {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
});

async function fetchOrders() {
    const status = document.getElementById('orderStatusFilter')?.value || 'all';
    try {
        const response = await sendAjaxRequest({ action: 'fetch_orders', status }, 'ajax.php', 'GET');
        if (response.success) {
            const tableBody = document.querySelector('#ordersTable tbody');
            if (tableBody) {
                tableBody.innerHTML = response.data.map(order => `
                    <tr>
                        <td>#${order.id}</td>
                        <td>${order.full_name}</td>
                        <td>$${((parseFloat(order.total) || 0) + (parseFloat(order.delivery_fee) || 0)).toFixed(2)}</td>
                        <td><span class="status-badge status-${order.status.toLowerCase()}">${order.status}</span></td>
                        <td>${new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="viewOrder(${order.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                            ${order.status === 'processing' ? `
                                <button class="btn btn-success btn-sm" onclick="openShipOrderModal(${order.id})">
                                    <i class="fas fa-truck"></i> Ship
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('');
            }
        } else {
            showAlert(response.message || 'Failed to fetch orders.', 'error');
        }
    } catch (error) {
        showAlert('Failed to fetch orders.', 'error');
    }
}

// Reviews
async function deleteReview(id) {
    if (!confirm('Are you sure you want to delete this review?')) return;
    try {
        const response = await sendAjaxRequest({ action: 'delete_review', review_id: id });
        if (response.success) {
            showAlert(response.message, 'success');
            fetchReviews();
        } else {
            showAlert(response.message || 'Failed to delete review.', 'error');
        }
    } catch (error) {
        showAlert('Failed to delete review.', 'error');
    }
}

async function fetchReviews() {
    try {
        const response = await sendAjaxRequest({ action: 'fetch_reviews' }, 'ajax.php', 'GET');
        if (response.success) {
            const tableBody = document.querySelector('#reviewsTable tbody');
            if (tableBody) {
                tableBody.innerHTML = response.data.map(review => `
                    <tr>
                        <td>${review.id}</td>
                        <td>${review.product_name}</td>
                        <td>${review.full_name}</td>
                        <td>${review.rating}/5</td>
                        <td>${review.review_text}</td>
                        <td>${new Date(review.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteReview(${review.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        } else {
            showAlert(response.message || 'Failed to fetch reviews.', 'error');
        }
    } catch (error) {
        showAlert('Failed to fetch reviews.', 'error');
    }
}

// Change Password
document.getElementById('changePasswordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    if (!button) return;
    button.classList.add('btn-loading');
    button.disabled = true;
    const newPassword = document.getElementById('newPassword')?.value || '';
    const confirmPassword = document.getElementById('confirmPassword')?.value || '';
    if (newPassword !== confirmPassword) {
        showAlert('Passwords do not match.', 'error');
        button.classList.remove('btn-loading');
        button.disabled = false;
        return;
    }
    const data = {
        action: 'change_password',
        current_password: document.getElementById('currentPassword')?.value || '',
        new_password: newPassword,
    };
    try {
        const response = await sendAjaxRequest(data);
        if (response.success) {
            showAlert(response.message, 'success');
            e.target.reset();
        } else {
            showAlert(response.message || 'Failed to change password.', 'error');
        }
    } catch (error) {
        showAlert('Failed to change password.', 'error');
    } finally {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
});

// Hero Section
document.getElementById('heroSectionForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    if (!button) return;
    button.classList.add('btn-loading');
    button.disabled = true;
    const data = {
        action: 'update_hero_section',
        title: document.getElementById('heroTitle')?.value || '',
        description: document.getElementById('heroDescription')?.value || '',
        button_text: document.getElementById('heroButtonText')?.value || '',
        main_image: document.getElementById('heroMainImage')?.files[0] || null,
        existing_main_image: document.getElementById('existingMainImage')?.value || '',
        sparkle_image_1: document.getElementById('heroSparkleImage1')?.files[0] || null,
        existing_sparkle_1: document.getElementById('existingSparkle1')?.value || '',
        sparkle_image_2: document.getElementById('heroSparkleImage2')?.files[0] || null,
        existing_sparkle_2: document.getElementById('existingSparkle2')?.value || '',
    };
    try {
        const response = await sendAjaxRequest(data);
        if (response.success) {
            showAlert(response.message, 'success');
            fetchHeroSection();
        } else {
            showAlert(response.message || 'Failed to update hero section.', 'error');
        }
    } catch (error) {
        showAlert('Failed to update hero section.', 'error');
    } finally {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
});

async function fetchHeroSection() {
    const heroSectionForm = document.getElementById('heroSectionForm');
    if (!heroSectionForm) {
        console.error('Hero section form not found.');
        showAlert('Hero section form not found.', 'error');
        return;
    }
    try {
        const response = await sendAjaxRequest({ action: 'fetch_hero_section' });
        if (response && response.id) { // Check for valid response
            const hero = response;
            document.getElementById('heroTitle').value = hero.title || '';
            document.getElementById('heroDescription').value = hero.description || '';
            document.getElementById('heroButtonText').value = hero.button_text || '';
            document.getElementById('existingMainImage').value = hero.main_image || '';
            document.getElementById('existingSparkle1').value = hero.sparkle_image_1 || '';
            document.getElementById('existingSparkle2').value = hero.sparkle_image_2 || '';
            document.getElementById('heroPreviewTitle').textContent = hero.title || 'Welcome to Deeken';
            document.getElementById('heroPreviewDescription').textContent = hero.description || 'Discover our awesome products.';
            document.getElementById('heroPreviewButton').textContent = hero.button_text || 'Shop Now';
            document.getElementById('heroPreviewMainImage').src = hero.main_image || 'images/hero-couple.png';
            document.getElementById('heroPreviewSparkle1').src = hero.sparkle_image_1 || 'images/sparkle-1.png';
            document.getElementById('heroPreviewSparkle2').src = hero.sparkle_image_2 || 'images/sparkle-2.png';
        } else {
            showAlert('Failed to fetch hero section.', 'error');
        }
    } catch (error) {
        console.error('Fetch hero section error:', error);
        showAlert('Failed to fetch hero section.', 'error');
    }
}

// Static Pages
function addSection() {
    const container = document.getElementById('sectionsContainer');
    if (!container) return;
    const sectionCount = container.querySelectorAll('.section-item').length + 1;
    const section = document.createElement('div');
    section.className = 'section-item';
    section.innerHTML = `
        <div class="form-group">
            <label class="form-label">Section Title</label>
            <input type="text" class="form-input" name="section_title_${sectionCount}" required>
        </div>
        <div class="form-group">
            <label class="form-label">Section Content</label>
            <textarea class="form-input" name="section_content_${sectionCount}" required></textarea>
        </div>
        <button type="button" class="remove-section" onclick="removeSection(this)">Remove Section</button>
    `;
    container.appendChild(section);
}

function removeSection(button) {
    if (button?.parentElement) {
        button.parentElement.remove();
    }
}

document.getElementById('pageSelector')?.addEventListener('change', async (e) => {
    const pageKey = e.target.value;
    if (!pageKey) {
        resetStaticPage();
        return;
    }
    try {
        const response = await sendAjaxRequest({ action: 'fetch_page', page_key: pageKey }, 'ajax.php', 'GET');
        if (response.success) {
            document.getElementById('pageTitle').value = response.data.title || '';
            document.getElementById('pageDescription').value = response.data.description || '';
            document.getElementById('pageMetaDescription').value = response.data.meta_description || '';
            document.getElementById('pageKey').value = pageKey;
            const container = document.getElementById('sectionsContainer');
            if (container) {
                container.innerHTML = '';
                (response.data.sections || []).forEach((section, index) => {
                    const sectionItem = document.createElement('div');
                    sectionItem.className = 'section-item';
                    sectionItem.innerHTML = `
                        <div class="form-group">
                            <label class="form-label">Section Title</label>
                            <input type="text" class="form-input" name="section_title_${index + 1}" value="${section.title || ''}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Section Content</label>
                            <textarea class="form-input" name="section_content_${index + 1}" required>${section.content || ''}</textarea>
                        </div>
                        <button type="button" class="remove-section" onclick="removeSection(this)">Remove Section</button>
                    `;
                    container.appendChild(sectionItem);
                });
            }
        } else {
            showAlert(response.message || 'Failed to fetch page.', 'error');
            resetStaticPage();
        }
    } catch (error) {
        showAlert('Failed to fetch page.', 'error');
        resetStaticPage();
    }
});

function resetStaticPage() {
    const pageSelector = document.getElementById('pageSelector');
    const pageTitle = document.getElementById('pageTitle');
    const pageDescription = document.getElementById('pageDescription');
    const pageMetaDescription = document.getElementById('pageMetaDescription');
    const pageKey = document.getElementById('pageKey');
    const sectionsContainer = document.getElementById('sectionsContainer');
    if (pageSelector && pageTitle && pageDescription && pageMetaDescription && pageKey && sectionsContainer) {
        pageSelector.value = '';
        pageTitle.value = '';
        pageDescription.value = '';
        pageMetaDescription.value = '';
        pageKey.value = '';
        sectionsContainer.innerHTML = '';
    }
}

document.getElementById('staticPageForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    if (!button) return;
    button.classList.add('btn-loading');
    button.disabled = true;
    const sections = [];
    document.querySelectorAll('.section-item').forEach((section, index) => {
        const title = section.querySelector(`input[name="section_title_${index + 1}"]`)?.value;
        const content = section.querySelector(`textarea[name="section_content_${index + 1}"]`)?.value;
        if (title && content) {
            sections.push({ title, content });
        }
    });
    const data = {
        action: 'update_page',
        page_key: document.getElementById('pageKey')?.value || '',
        title: document.getElementById('pageTitle')?.value || '',
        description: document.getElementById('pageDescription')?.value || '',
        meta_description: document.getElementById('pageMetaDescription')?.value || '',
        sections: JSON.stringify(sections),
    };
    try {
        const response = await sendAjaxRequest(data);
        if (response.success) {
            showAlert(response.message, 'success');
        } else {
            showAlert(response.message || 'Failed to update page.', 'error');
        }
    } catch (error) {
        showAlert('Failed to update page.', 'error');
    } finally {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
});

// Initialize tabs
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
        const tabId = link.getAttribute('data-tab');
        if (tabId) switchTab(tabId);
    });
});

// Initial load
fetchHeroSection();
fetchOrders();
fetchCategories();
fetchUsers();
fetchReviews();
    </script>
</body>
</html>
        