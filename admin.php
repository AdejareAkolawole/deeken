<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Require admin access
requireAdmin();

// ----- PRODUCT DELETION HANDLING -----
if (isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];

    // Delete associated records (if no CASCADE in schema)
    $conn->query("DELETE FROM cart WHERE product_id = $product_id");
    $conn->query("DELETE FROM orders WHERE product_id = $product_id");
    $conn->query("DELETE FROM reviews WHERE product_id = $product_id");

    // Delete product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    if ($stmt->execute()) {
        $success = "Product deleted successfully.";
    } else {
        $error = "Failed to delete product.";
    }
    $stmt->close();
}

// ----- PRODUCT MANAGEMENT -----
$products = [];
$result = $conn->query("SELECT id, name, price, stock_quantity, image_url, category FROM products");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
}

// ----- ORDER MANAGEMENT -----
$orders = [];
$result = $conn->query("
    SELECT o.id, o.user_id, o.product_id, o.quantity, o.total, o.order_date, o.status, u.email, p.name as product_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN products p ON o.product_id = p.id
    ORDER BY o.order_date DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Inline CSS from admin.html */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #06b6d4;
            --accent: #8b5cf6;
            --background: #0f172a;
            --surface: rgba(15, 23, 42, 0.8);
            --glass: rgba(255, 255, 255, 0.1);
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border: rgba(255, 255, 255, 0.1);
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .navbar {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .nav-content {
            max-width: 1200px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .nav-links a:hover {
            color: var(--text-primary);
            background: var(--glass);
        }

        .container {
            max-width: 1200px;
            margin: 100px auto 2rem;
            padding: 0 2rem;
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .form {
            display: grid;
            gap: 1rem;
            max-width: 600px;
            margin-bottom: 2rem;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-input {
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--error), #b91c1c);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        th {
            font-weight: 600;
            color: var(--text-primary);
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }

        .validation-message {
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .validation-message.success {
            color: var(--success);
        }

        .validation-message.error {
            color: var(--error);
        }

        @media (max-width: 767px) {
            .container {
                padding: 0 1rem;
                margin-top: 80px;
            }

            .section {
                padding: 1.5rem;
            }

            .form {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.9rem;
            }

            th, td {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- ----- NAVIGATION ----- -->
    <nav class="navbar">
        <div class="nav-content">
            <a href="index.php" class="logo">
                <i class="fas fa-store"></i>
                Deeken
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="cart.php">Cart</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="login.php?logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- ----- DASHBOARD ----- -->
    <div class="container">
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
        </div>

        <!-- Product Management -->
        <div class="section">
            <h2>Manage Products</h2>

            <?php if (isset($success)): ?>
                <div class="validation-message success"><?php echo htmlspecialchars($success); ?></div>
            <?php elseif (isset($error)): ?>
                <div class="validation-message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form class="form" method="POST" enctype="multipart/form-data">
                <div class="input-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" class="form-input" name="product_name" required>
                </div>
                <div class="input-group">
                    <label for="price">Price ($)</label>
                    <input type="number" class="form-input" name="price" step="0.01" required>
                </div>
                <div class="input-group">
                    <label for="stock_quantity">Stock Quantity</label>
                    <input type="number" class="form-input" name="stock_quantity" required>
                </div>
                <div class="input-group">
                    <label for="image">Product Image</label>
                    <input type="file" class="form-input" name="image" accept="image/*">
                </div>
                <div class="input-group">
                    <label for="category">Category</label>
                    <input type="text" class="form-input" name="category">
                </div>
                <button type="submit" name="add_product" class="btn">Add Product</button>
            </form>

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
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image"></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['stock_quantity']; ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="delete_product" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Management -->
        <div class="section">
            <h2>Manage Orders</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User Email</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Order Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['email']); ?></td>
                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                            <td><?php echo $order['quantity']; ?></td>
                            <td>$<?php echo number_format($order['total'], 2); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ----- JAVASCRIPT ----- -->
    <script>
        // Minimal JavaScript for mobile menu (if needed)
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const navLinks = document.querySelector('.nav-links');

        if (mobileMenuBtn && navLinks) {
            mobileMenuBtn.addEventListener('click', () => {
                navLinks.classList.toggle('active');
                const icon = mobileMenuBtn.querySelector('i');
                icon.className = navLinks.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
            });
        }
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>