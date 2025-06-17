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

// ----- AJAX HANDLING -----

// Search Products
if (isset($_POST['action']) && $_POST['action'] === 'search_products') {
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $products = [];
    try {
        $query = "
            SELECT p.id, p.name, p.sku, p.price, p.description, i.stock_quantity, p.image, c.name AS category, ma.attribute AS misc_attribute, p.featured
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

// Fetch Orders
if (isset($_POST['action']) && $_POST['action'] === 'fetch_orders') {
    $status = isset($_POST['status']) && $_POST['status'] !== 'all' ? $_POST['status'] : null;
    $orders = [];
    try {
        $query = "
            SELECT o.id, o.user_id, u.full_name, o.total, o.delivery_fee, o.status, o.created_at, o.address_id
            FROM orders o
            JOIN users u ON o.user_id = u.id
        ";
        if ($status) {
            $query .= " WHERE o.status = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $status);
        } else {
            $stmt = $conn->prepare($query);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Fetch orders error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($orders);
    exit;
}

// Fetch Categories
if (isset($_POST['action']) && $_POST['action'] === 'fetch_categories') {
    $categories = [];
    try {
        $result = $conn->query("SELECT id, name, description, created_at FROM categories");
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

// Fetch Users
if (isset($_POST['action']) && $_POST['action'] === 'fetch_users') {
    $users = [];
    try {
        $result = $conn->query("SELECT id, email, full_name, phone, is_admin, created_at FROM users");
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } catch (Exception $e) {
        error_log("Fetch users error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}

// Fetch Reviews
if (isset($_POST['action']) && $_POST['action'] === 'fetch_reviews') {
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
        error_log("Fetch reviews error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($reviews);
    exit;
}

// Add Product
if (isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $product_name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category_id = (int)$_POST['category_id'];
    $misc_attribute = trim($_POST['misc_attribute']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $description = trim($_POST['description']);
    $image_url = 'https://via.placeholder.com/150';
    $response = ['success' => false, 'message' => ''];

    if (empty($product_name)) {
        $response['message'] = 'Product name is required.';
    } elseif ($price <= 0) {
        $response['message'] = 'Price must be greater than 0.';
    } elseif ($category_id <= 0) {
        $response['message'] = 'Please select a valid category.';
    } else {
        $valid_attributes = ['new_arrival', 'featured', 'trending', ''];
        if (!in_array($misc_attribute, $valid_attributes)) {
            $response['message'] = 'Invalid miscellaneous attribute.';
        } else {
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                if (!is_writable($target_dir)) {
                    $response['message'] = 'Upload directory is not writable.';
                } else {
                    $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($imageFileType, $allowed_types)) {
                        $response['message'] = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
                    } elseif ($_FILES['image']['size'] > 5000000) {
                        $response['message'] = 'Image size exceeds 5MB limit.';
                    } else {
                        $unique_name = uniqid('img_') . '.' . $imageFileType;
                        $target_file = $target_dir . $unique_name;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                            $image_url = $target_file;
                        } else {
                            $response['message'] = 'Failed to upload image.';
                        }
                    }
                }
            }
            if (!$response['message']) {
                $conn->begin_transaction();
                try {
                    $sku = "PROD" . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE sku = ?");
                    $stmt->bind_param("s", $sku);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
                        throw new Exception("Generated SKU already exists.");
                    }
                    $stmt->close();

                    $stmt = $conn->prepare("
                        INSERT INTO products (category_id, name, sku, price, image, description, featured, rating)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 0.0)
                    ");
                    $description = $description ?: 'No description provided.';
                    $stmt->bind_param("issdssi", $category_id, $product_name, $sku, $price, $image_url, $description, $featured);
                    $stmt->execute();
                    $product_id = $conn->insert_id;
                    $stmt->close();

                    $inv_stmt = $conn->prepare("INSERT INTO inventory (product_id, stock_quantity) VALUES (?, ?)");
                    $inv_stmt->bind_param("ii", $product_id, $stock_quantity);
                    $inv_stmt->execute();
                    $inv_stmt->close();

                    if ($misc_attribute) {
                        $attr_stmt = $conn->prepare("INSERT INTO miscellaneous_attributes (product_id, attribute) VALUES (?, ?)");
                        $attr_stmt->bind_param("is", $product_id, $misc_attribute);
                        $attr_stmt->execute();
                        $attr_stmt->close();
                    }

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Product added successfully.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['message'] = 'Failed to add product: ' . $e->getMessage();
                    error_log("Product addition error: " . $e->getMessage());
                }
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Edit Product
if (isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = (int)$_POST['product_id'];
    $product_name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category_id = (int)$_POST['category_id'];
    $misc_attribute = trim($_POST['misc_attribute']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $description = trim($_POST['description']);
    $image_url = $_POST['existing_image'];
    $response = ['success' => false, 'message' => ''];

    if (empty($product_name)) {
        $response['message'] = 'Product name is required.';
    } elseif ($price <= 0) {
        $response['message'] = 'Price must be greater than 0.';
    } elseif ($category_id <= 0) {
        $response['message'] = 'Please select a valid category.';
    } else {
        $valid_attributes = ['new_arrival', 'featured', 'trending', ''];
        if (!in_array($misc_attribute, $valid_attributes)) {
            $response['message'] = 'Invalid miscellaneous attribute.';
        } else {
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                if (!is_writable($target_dir)) {
                    $response['message'] = 'Upload directory is not writable.';
                } else {
                    $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($imageFileType, $allowed_types)) {
                        $response['message'] = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
                    } elseif ($_FILES['image']['size'] > 5000000) {
                        $response['message'] = 'Image size exceeds 5MB limit.';
                    } else {
                        $unique_name = uniqid('img_') . '.' . $imageFileType;
                        $target_file = $target_dir . $unique_name;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                            if ($image_url !== 'https://via.placeholder.com/150' && file_exists($image_url)) {
                                unlink($image_url);
                            }
                            $image_url = $target_file;
                        } else {
                            $response['message'] = 'Failed to upload new image.';
                        }
                    }
                }
            }
            if (!$response['message']) {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("
                        UPDATE products 
                        SET category_id = ?, name = ?, price = ?, image = ?, description = ?, featured = ?
                        WHERE id = ?
                    ");
                    $description = $description ?: 'No description provided.';
                    $stmt->bind_param("isdssii", $category_id, $product_name, $price, $image_url, $description, $featured, $product_id);
                    $stmt->execute();
                    $stmt->close();

                    $inv_stmt = $conn->prepare("UPDATE inventory SET stock_quantity = ? WHERE product_id = ?");
                    $inv_stmt->bind_param("ii", $stock_quantity, $product_id);
                    $inv_stmt->execute();
                    $inv_stmt->close();

                    $attr_stmt = $conn->prepare("DELETE FROM miscellaneous_attributes WHERE product_id = ?");
                    $attr_stmt->bind_param("i", $product_id);
                    $attr_stmt->execute();
                    $attr_stmt->close();

                    if ($misc_attribute) {
                        $attr_stmt = $conn->prepare("INSERT INTO miscellaneous_attributes (product_id, attribute) VALUES (?, ?)");
                        $attr_stmt->bind_param("is", $product_id, $misc_attribute);
                        $attr_stmt->execute();
                        $attr_stmt->close();
                    }

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Product updated successfully.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['message'] = 'Failed to update product: ' . $e->getMessage();
                    error_log("Product update error: " . $e->getMessage());
                }
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Delete Product
if (isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $product_id = (int)$_POST['product_id'];
    $response = ['success' => false, 'message' => ''];
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $image_path = $row['image'];
            if ($image_path !== 'https://via.placeholder.com/150' && file_exists($image_path)) {
                unlink($image_path);
            }
        }
        $stmt->close();

        $tables = ['cart', 'order_items', 'reviews', 'inventory', 'miscellaneous_attributes'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM $table WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Product deleted successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Failed to delete product: ' . $e->getMessage();
        error_log("Product deletion error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Add Category
if (isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $category_name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $response = ['success' => false, 'message' => ''];
    if (empty($category_name)) {
        $response['message'] = 'Category name is required.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $category_name, $description);
            $stmt->execute();
            $stmt->close();
            $response['success'] = true;
            $response['message'] = 'Category added successfully.';
        } catch (Exception $e) {
            $response['message'] = 'Failed to add category: ' . $e->getMessage();
            error_log("Category creation error: " . $e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Edit Category
if (isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $category_id = (int)$_POST['category_id'];
    $category_name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $response = ['success' => false, 'message' => ''];
    if (empty($category_name)) {
        $response['message'] = 'Category name is required.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $category_name, $description, $category_id);
            $stmt->execute();
            $stmt->close();
            $response['success'] = true;
            $response['message'] = 'Category updated successfully.';
        } catch (Exception $e) {
            $response['message'] = 'Failed to update category: ' . $e->getMessage();
            error_log("Category update error: " . $e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Delete Category
if (isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $category_id = (int)$_POST['category_id'];
    $response = ['success' => false, 'message' => ''];
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()['count'] > 0) {
            $response['message'] = 'Cannot delete category because it has associated products.';
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $stmt->close();
            $response['success'] = true;
            $response['message'] = 'Category deleted successfully.';
        }
    } catch (Exception $e) {
        $response['message'] = 'Failed to delete category: ' . $e->getMessage();
        error_log("Category deletion error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Add User
if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $password = trim($_POST['password']);
    $response = ['success' => false, 'message' => ''];
    if (empty($email) || empty($full_name) || empty($phone) || empty($password)) {
        $response['message'] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
    } elseif (strlen($password) < 8) {
        $response['message'] = 'Password must be at least 8 characters.';
    } else {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
                $response['message'] = 'Email already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, phone, is_admin, address) VALUES (?, ?, ?, ?, ?, 'No address provided')");
                $stmt->bind_param("ssssi", $email, $hashed_password, $full_name, $phone, $is_admin);
                $stmt->execute();
                $stmt->close();
                $response['success'] = true;
                $response['message'] = 'User added successfully.';
            }
        } catch (Exception $e) {
            $response['message'] = 'Failed to add user: ' . $e->getMessage();
            error_log("User addition error: " . $e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Edit User
if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $user_id = (int)$_POST['user_id'];
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $response = ['success' => false, 'message' => ''];
    if (empty($email) || empty($full_name) || empty($phone)) {
        $response['message'] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ?, phone = ?, is_admin = ? WHERE id = ?");
            $stmt->bind_param("sssii", $email, $full_name, $phone, $is_admin, $user_id);
            $stmt->execute();
            $stmt->close();
            $response['success'] = true;
            $response['message'] = 'User updated successfully.';
        } catch (Exception $e) {
            $response['message'] = 'Failed to update user: ' . $e->getMessage();
            error_log("User update error: " . $e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Delete User
if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = (int)$_POST['user_id'];
    $response = ['success' => false, 'message' => ''];
    if ($user_id === $user['id']) {
        $response['message'] = 'Cannot delete the current user.';
    } else {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
                $response['message'] = 'Cannot delete user because they have associated orders.';
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                $response['success'] = true;
                $response['message'] = 'User deleted successfully.';
            }
        } catch (Exception $e) {
            $response['message'] = 'Failed to delete user: ' . $e->getMessage();
            error_log("User deletion error: " . $e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Ship Order
if (isset($_POST['action']) && $_POST['action'] === 'ship_order') {
    $order_id = (int)$_POST['order_id'];
    $response = ['success' => false, 'message' => ''];
    try {
        $stmt = $conn->prepare("UPDATE orders SET status = 'shipped' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();
        $response['success'] = true;
        $response['message'] = 'Order marked as shipped.';
    } catch (Exception $e) {
        $response['message'] = 'Failed to ship order: ' . $e->getMessage();
        error_log("Order ship error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Delete Review
if (isset($_POST['action']) && $_POST['action'] === 'delete_review') {
    $review_id = (int)$_POST['review_id'];
    $response = ['success' => false, 'message' => ''];
    try {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $stmt->close();
        $response['success'] = true;
        $response['message'] = 'Review deleted successfully.';
    } catch (Exception $e) {
        $response['message'] = 'Failed to delete review: ' . $e->getMessage();
        error_log("Review deletion error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Change Password
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $response = ['success' => false, 'message' => ''];
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $response['message'] = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $response['message'] = 'New password and confirmation do not match.';
    } elseif (strlen($new_password) < 8) {
        $response['message'] = 'New password must be at least 8 characters.';
    } else {
        try {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $stored_password = $stmt->get_result()->fetch_assoc()['password'];
            $stmt->close();
            if (password_verify($current_password, $stored_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user['id']);
                $stmt->execute();
                $stmt->close();
                $response['success'] = true;
                $response['message'] = 'Password changed successfully.';
            } else {
                $response['message'] = 'Current password is incorrect.';
            }
        } catch (Exception $e) {
            $response['message'] = 'Failed to change password: ' . $e->getMessage();
            error_log("Password change error: " . $e->getMessage());
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ----- FETCH METRICS -----
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
$total_revenue = $conn->query("SELECT SUM(total) as sum FROM orders WHERE status = 'delivered'")->fetch_assoc()['sum'] ?? 0;
$total_stock = $conn->query("SELECT SUM(stock_quantity) as sum FROM inventory")->fetch_assoc()['sum'] ?? 0;
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;

// ----- FETCH DATA FOR TABLES -----
$products = [];
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

$categories = [];
$result = $conn->query("SELECT id, name, description, created_at FROM categories");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

$users = [];
$result = $conn->query("SELECT id, email, full_name, phone, is_admin, created_at FROM users");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$orders = [];
$result = $conn->query("
    SELECT o.id, o.user_id, u.full_name, o.total, o.delivery_fee, o.status, o.created_at, o.address_id
    FROM orders o
    JOIN users u ON o.user_id = u.id
");
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$reviews = [];
$result = $conn->query("
    SELECT r.id, r.product_id, p.name AS product_name, r.user_id, u.full_name, r.rating, r.review_text, r.created_at
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.user_id = u.id
");
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

$inventory = [];
$result = $conn->query("SELECT p.id, p.name, i.stock_quantity FROM products p JOIN inventory i ON p.id = i.product_id");
while ($row = $result->fetch_assoc()) {
    $inventory[] = $row;
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
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .sidebar-header p {
            opacity: 0.8;
            font-size: 0.9rem;
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
            padding: 1rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            backdrop-filter: blur(10px);
        }

        .nav-link i {
            margin-right: 1rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: var(--background-color);
        }

        .top-bar {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem 2rem;
            background: var(--card-color);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .top-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .theme-toggle {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem;
            cursor: pointer;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: var(--card-color);
            padding: 1.5rem;
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
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .card-change {
            font-size: 0.8rem;
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
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background: var(--surface-color);
            font-weight: 600;
            color: var(--text-primary);
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--background-color);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
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
            padding: 2rem;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            font-size: 1.2rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h1,
            .sidebar-header p,
            .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
            }
            
            .nav-link i {
                margin-right: 0;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
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
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1 class="page-title" id="pageTitle">Dashboard</h1>
                <div class="top-actions">
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </button>
                    <button class="btn btn-primary" onclick="openQuickAdd()">
                        <i class="fas fa-plus"></i> Quick Add
                    </button>
                </div>
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
                        <tbody id="recentOrdersTable">
                            <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo htmlspecialchars(strtolower($order['status'])); ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($order['created_at']))); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="viewOrder(<?php echo htmlspecialchars($order['id']); ?>)">View</button>
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
                        <h3 class="table-title">Products Management</h3>
                        <button class="btn btn-success" onclick="openModal('addProductModal')">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th>Category</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTable">
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['id']); ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick='editProduct(<?php echo json_encode($product, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'>Edit</button>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteProduct(<?php echo htmlspecialchars($product['id']); ?>)">Delete</button>
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
                        <h3 class="table-title">Categories Management</h3>
                        <button class="btn btn-success" onclick="openModal('addCategoryModal')">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTable">
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['id']); ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($category['created_at']))); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="editCategory(<?php echo htmlspecialchars($category['id']); ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description']); ?>')">Edit</button>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteCategory(<?php echo htmlspecialchars($category['id']); ?>)">Delete</button>
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
                        <h3 class="table-title">Orders Management</h3>
                        <div>
                            <select class="form-input" style="width: auto; margin-right: 1rem;" onchange="filterOrders(this.value)">
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Delivery Fee</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTable">
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td>$<?php echo number_format($order['delivery_fee'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo htmlspecialchars(strtolower($order['status'])); ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($order['created_at']))); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="viewOrder(<?php echo htmlspecialchars($order['id']); ?>)">View</button>
                                        <?php if ($order['status'] === 'processing'): ?>
                                            <button class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="shipOrder(<?php echo htmlspecialchars($order['id']); ?>)">Ship</button>
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
                        <h3 class="table-title">Users Management</h3>
                        <button class="btn btn-success" onclick="openModal('addUserModal')">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTable">
                            <?php foreach ($users as $user_item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user_item['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['phone']); ?></td>
                                    <td><?php echo $user_item['is_admin'] ? 'Admin' : 'Customer'; ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user_item['created_at']))); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick='editUser(<?php echo json_encode($user_item, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'>Edit</button>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteUser(<?php echo htmlspecialchars($user_item['id']); ?>)">Delete</button>
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
                        <h3 class="table-title">Inventory Management</h3>
                        <button class="btn btn-primary" onclick="refreshInventory()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Stock Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTable">
                            <?php foreach ($inventory as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['id']); ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['stock_quantity']); ?></td>
                                    <td>
                                        <?php if ($item['stock_quantity'] <= 5): ?>
                                            <span class="status-badge status-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="status-badge status-delivered">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="editProduct(<?php echo htmlspecialchars(json_encode($products[array_search($item['id'], array_column($products, 'id'))], JSON_HEX_QUOT | JSON_HEX_APOS)); ?>)">Edit</button>
                                    </td>
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
                        <h3 class="table-title">Reviews Management</h3>
                        <button class="btn btn-primary" onclick="refreshReviews()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>User</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reviewsTable">
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($review['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($review['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($review['rating']); ?>/5</td>
                                    <td><?php echo htmlspecialchars(substr($review['review_text'], 0, 50) . '...'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($review['created_at']))); ?></td>
                                    <td>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteReview(<?php echo htmlspecialchars($review['id']); ?>)">Delete</button>
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
                        <h3 class="table-title">Security Settings</h3>
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
                    <form class="form-grid" id="passwordForm">
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
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-lock"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modals -->
            <div class="modal" id="addProductModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add Product</h3>
                        <button class="modal-close" onclick="closeModal('addProductModal')">&times;</button>
                    </div>
                    <form id="productForm" enctype="multipart/form-data" class="form-grid">
                        <input type="hidden" id="productId">
                        <input type="hidden" id="existingImage">
                        <div class="form-group">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-input" id="productName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price ($)</label>
                            <input type="number" class="form-input" id="productPrice" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" class="form-input" id="productStock" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select class="form-input" id="productCategory" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
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
                            <label class="form-label">Featured Product</label>
                            <input type="checkbox" id="productFeatured">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-input" id="productImage" accept="image/*">
                            <img id="imagePreview" src="" alt="Image Preview" style="display: none; margin-top: 0.5rem; max-width: 100%;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input" id="productDescription" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Save Product</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal" id="addCategoryModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add Category</h3>
                        <button class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
                    </div>
                    <form id="categoryForm" class="form-grid">
                        <input type="hidden" id="categoryId">
                        <div class="form-group">
                            <label class="form-label">Category Name</label>
                            <input type="text" class="form-input" id="categoryName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input" id="categoryDescription" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Save Category</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal" id="addUserModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add User</h3>
                        <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
                    </div>
                    <form id="userForm" class="form-grid">
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
                            <label class="form-label">Password</label>
                            <input type="password" class="form-input" id="userPassword" placeholder="Required for new users">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select class="form-input" id="userRole">
                                <option value="0">Customer</option>
                                <option value="1">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Save User</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

                    <div class="modal" id="quickAddModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Quick Add</h3>
                        <button class="modal-close" onclick="closeModal('quickAddModal')">×</button>
                    </div>
                    <form id="quickAddForm" class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Type</label>
                            <select class="form-input" id="quickAddType" onchange="toggleQuickAddFields()">
                                <option value="product">Product</option>
                                <option value="category">Category</option>
                            </select>
                        </div>
                        <!-- Product Fields -->
                        <div id="quickAddProductFields">
                            <div class="form-group">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-input" id="quickProductName" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Price ($)</label>
                                <input type="number" class="form-input" id="quickProductPrice" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" class="form-input" id="quickProductStock" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select class="form-input" id="quickProductCategory" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <!-- Category Fields -->
                        <div id="quickAddCategoryFields" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Category Name</label>
                                <input type="text" class="form-input" id="quickCategoryName" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea class="form-input" id="quickCategoryDescription" rows="4"></textarea>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Add</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('quickAddModal')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ----- Theme Toggle -----
        function toggleTheme() {
            const isDark = document.body.dataset.theme === 'dark';
            document.body.dataset.theme = isDark ? 'light' : 'dark';
            document.getElementById('themeIcon').className = isDark ? 'fas fa-moon' : 'fas fa-sun';
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        }

        // Initialize theme
        (function initializeTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.dataset.theme = savedTheme;
            document.getElementById('themeIcon').className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        })();

        // ----- Tab Switching -----
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            document.querySelector(`.nav-link[data-tab="${tabId}"]`).classList.add('active');
            document.getElementById('pageTitle').textContent = tabId.charAt(0).toUpperCase() + tabId.slice(1);
            if (tabId === 'products') fetchProducts();
            if (tabId === 'orders') fetchOrders('all');
            if (tabId === 'categories') fetchCategories();
            if (tabId === 'users') fetchUsers();
            if (tabId === 'reviews') fetchReviews();
        }

        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => switchTab(link.dataset.tab));
        });

        // ----- Modal Handling -----
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            if (modalId === 'addProductModal') document.getElementById('productForm').reset();
            if (modalId === 'addCategoryModal') document.getElementById('categoryForm').reset();
            if (modalId === 'addUserModal') document.getElementById('userForm').reset();
            if (modalId === 'quickAddModal') document.getElementById('quickAddForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
        }

        // ----- Alert Dismiss -----
        function dismissAlert(element) {
            element.parentElement.remove();
        }

        // ----- Image Preview -----
        document.getElementById('productImage')?.addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            if (e.target.files[0]) {
                preview.src = URL.createObjectURL(e.target.files[0]);
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        });

        // ----- Quick Add Toggle Fields -----
        function toggleQuickAddFields() {
            const type = document.getElementById('quickAddType').value;
            document.getElementById('quickAddProductFields').style.display = type === 'product' ? 'block' : 'none';
            document.getElementById('quickAddCategoryFields').style.display = type === 'category' ? 'block' : 'none';
        }

        function openQuickAdd() {
            openModal('quickAddModal');
            toggleQuickAddFields();
        }

        // ----- Fetch Functions -----
        async function fetchProducts(search = '') {
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=search_products&search=${encodeURIComponent(search)}`
                });
                const products = await response.json();
                const tbody = document.getElementById('productsTable');
                tbody.innerHTML = products.map(product => `
                    <tr>
                        <td>${product.id}</td>
                        <td>${product.name}</td>
                        <td>${product.sku}</td>
                        <td>$${parseFloat(product.price).toFixed(2)}</td>
                        <td>${product.category || 'N/A'}</td>
                        <td>${product.stock_quantity}</td>
                        <td>
                            <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick='editProduct(${JSON.stringify(product)})'>Edit</button>
                            <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteProduct(${product.id})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                showAlert('error', 'Failed to fetch products.');
            }
        }

        async function fetchOrders(status) {
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=fetch_orders&status=${encodeURIComponent(status)}`
                });
                const orders = await response.json();
                const tbody = document.getElementById('ordersTable');
                tbody.innerHTML = orders.map(order => `
                    <tr>
                        <td>#${order.id}</td>
                        <td>${order.full_name}</td>
                        <td>$${parseFloat(order.total).toFixed(2)}</td>
                        <td>$${parseFloat(order.delivery_fee).toFixed(2)}</td>
                        <td><span class="status-badge status-${order.status.toLowerCase()}">${order.status}</span></td>
                        <td>${new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="viewOrder(${order.id})">View</button>
                            ${order.status === 'processing' ? `<button class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="shipOrder(${order.id})">Ship</button>` : ''}
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                showAlert('error', 'Failed to fetch orders.');
            }
        }

        async function fetchCategories() {
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=fetch_categories'
                });
                const categories = await response.json();
                const tbody = document.getElementById('categoriesTable');
                tbody.innerHTML = categories.map(category => `
                    <tr>
                        <td>${category.id}</td>
                        <td>${category.name}</td>
                        <td>${category.description || 'N/A'}</td>
                        <td>${new Date(category.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="editCategory(${category.id}, '${category.name}', '${category.description || ''}')">Edit</button>
                            <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteCategory(${category.id})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                showAlert('error', 'Failed to fetch categories.');
            }
        }

        async function fetchUsers() {
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=fetch_users'
                });
                const users = await response.json();
                const tbody = document.getElementById('usersTable');
                tbody.innerHTML = users.map(user => `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.email}</td>
                        <td>${user.full_name}</td>
                        <td>${user.phone}</td>
                        <td>${user.is_admin ? 'Admin' : 'Customer'}</td>
                        <td>${new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick='editUser(${JSON.stringify(user)})'>Edit</button>
                            <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteUser(${user.id})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                showAlert('error', 'Failed to fetch users.');
            }
        }

        async function fetchReviews() {
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=fetch_reviews'
                });
                const reviews = await response.json();
                const tbody = document.getElementById('reviewsTable');
                tbody.innerHTML = reviews.map(review => `
                    <tr>
                        <td>${review.product_name}</td>
                        <td>${review.full_name}</td>
                        <td>${review.rating}/5</td>
                        <td>${review.review_text.substring(0, 50)}${review.review_text.length > 50 ? '...' : ''}</td>
                        <td>${new Date(review.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteReview(${review.id})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                showAlert('error', 'Failed to fetch reviews.');
            }
        }

        // ----- CRUD Operations -----
        document.getElementById('productForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            const productId = document.getElementById('productId').value;
            formData.append('action', productId ? 'edit_product' : 'add_product');
            if (productId) formData.append('product_id', productId);
            formData.append('name', document.getElementById('productName').value);
            formData.append('price', document.getElementById('productPrice').value);
            formData.append('stock_quantity', document.getElementById('productStock').value);
            formData.append('category_id', document.getElementById('productCategory').value);
            formData.append('misc_attribute', document.getElementById('productMiscAttribute').value);
            formData.append('featured', document.getElementById('productFeatured').checked ? 1 : 0);
            formData.append('description', document.getElementById('productDescription').value);
            if (productId) formData.append('existing_image', document.getElementById('existingImage').value);
            const imageFile = document.getElementById('productImage').files[0];
            if (imageFile) formData.append('image', imageFile);

            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) {
                    closeModal('addProductModal');
                    fetchProducts();
                }
            } catch (error) {
                showAlert('error', 'Failed to save product.');
            }
        });

        function editProduct(product) {
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('productCategory').value = product.category_id || '';
            document.getElementById('productMiscAttribute').value = product.misc_attribute || '';
            document.getElementById('productFeatured').checked = product.featured == 1;
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('existingImage').value = product.image;
            const preview = document.getElementById('imagePreview');
            preview.src = product.image;
            preview.style.display = 'block';
            document.querySelector('#addProductModal .modal-title').textContent = 'Edit Product';
            openModal('addProductModal');
        }

        async function deleteProduct(productId) {
            if (!confirm('Are you sure you want to delete this product?')) return;
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_product&product_id=${productId}`
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) fetchProducts();
            } catch (error) {
                showAlert('error', 'Failed to delete product.');
            }
        }

        document.getElementById('categoryForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const categoryId = document.getElementById('categoryId').value;
            const data = new URLSearchParams({
                action: categoryId ? 'edit_category' : 'add_category',
                name: document.getElementById('categoryName').value,
                description: document.getElementById('categoryDescription').value
            });
            if (categoryId) data.append('category_id', categoryId);

            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: data
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) {
                    closeModal('addCategoryModal');
                    fetchCategories();
                }
            } catch (error) {
                showAlert('error', 'Failed to save category.');
            }
        });

        function editCategory(id, name, description) {
            document.getElementById('categoryId').value = id;
            document.getElementById('categoryName').value = name;
            document.getElementById('categoryDescription').value = description;
            document.querySelector('#addCategoryModal .modal-title').textContent = 'Edit Category';
            openModal('addCategoryModal');
        }

        async function deleteCategory(categoryId) {
            if (!confirm('Are you sure you want to delete this category?')) return;
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_category&category_id=${categoryId}`
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) fetchCategories();
            } catch (error) {
                showAlert('error', 'Failed to delete category.');
            }
        }

        document.getElementById('userForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const userId = document.getElementById('userId').value;
            const data = new URLSearchParams({
                action: userId ? 'edit_user' : 'add_user',
                email: document.getElementById('userEmail').value,
                full_name: document.getElementById('userFullName').value,
                phone: document.getElementById('userPhone').value,
                is_admin: document.getElementById('userRole').value
            });
            if (userId) data.append('user_id', userId);
            if (!userId) data.append('password', document.getElementById('userPassword').value);

            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: data
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) {
                    closeModal('addUserModal');
                    fetchUsers();
                }
            } catch (error) {
                showAlert('error', 'Failed to save user.');
            }
        });

        function editUser(user) {
            document.getElementById('userId').value = user.id;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userFullName').value = user.full_name;
            document.getElementById('userPhone').value = user.phone;
            document.getElementById('userRole').value = user.is_admin;
            document.getElementById('userPassword').value = '';
            document.getElementById('userPassword').placeholder = 'Leave blank to keep current password';
            document.querySelector('#addUserModal .modal-title').textContent = 'Edit User';
            openModal('addUserModal');
        }

        async function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user?')) return;
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_user&user_id=${userId}`
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) fetchUsers();
            } catch (error) {
                showAlert('error', 'Failed to delete user.');
            }
        }

        async function shipOrder(orderId) {
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=ship_order&order_id=${orderId}`
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) fetchOrders('all');
            } catch (error) {
                showAlert('error', 'Failed to ship order.');
            }
        }

        async function deleteReview(reviewId) {
            if (!confirm('Are you sure you want to delete this review?')) return;
            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_review&review_id=${reviewId}`
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) fetchReviews();
            } catch (error) {
                showAlert('error', 'Failed to delete review.');
            }
        }

        function viewOrder(orderId) {
            alert(`View order details for Order ID: ${orderId} (Implement detailed view as needed)`);
        }

        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = new URLSearchParams({
                action: 'change_password',
                current_password: document.getElementById('currentPassword').value,
                new_password: document.getElementById('newPassword').value,
                confirm_password: document.getElementById('confirmPassword').value
            });

            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: data
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) document.getElementById('passwordForm').reset();
            } catch (error) {
                showAlert('error', 'Failed to change password.');
            }
        });

        document.getElementById('quickAddForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const type = document.getElementById('quickAddType').value;
            let data;

            if (type === 'product') {
                data = new FormData();
                data.append('action', 'add_product');
                data.append('name', document.getElementById('quickProductName').value);
                data.append('price', document.getElementById('quickProductPrice').value);
                data.append('stock_quantity', document.getElementById('quickProductStock').value);
                data.append('category_id', document.getElementById('quickProductCategory').value);
            } else {
                data = new URLSearchParams({
                    action: 'add_category',
                    name: document.getElementById('quickCategoryName').value,
                    description: document.getElementById('quickCategoryDescription').value
                });
            }

            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    body: data
                });
                const result = await response.json();
                showAlert(result.success ? 'success' : 'error', result.message);
                if (result.success) {
                    closeModal('quickAddModal');
                    if (type === 'product') fetchProducts();
                    else fetchCategories();
                }
            } catch (error) {
                showAlert('error', `Failed to add ${type}.`);
            }
        });

        // ----- Utility Functions -----
        function showAlert(type, message) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
                <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>
            `;
            document.querySelector('.main-content').insertBefore(alert, document.querySelector('.top-bar').nextSibling);
            setTimeout(() => alert.remove(), 5000);
        }

        function filterOrders(status) {
            fetchOrders(status);
        }

        function refreshInventory() {
            window.location.reload(); // Simplified refresh
        }

        function refreshReviews() {
            fetchReviews();
        }

        // Initialize
        fetchProducts();
    </script>
</body>
</html>