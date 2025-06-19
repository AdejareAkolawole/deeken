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

// Function to create a notification
function createNotification($conn, $user_id, $message, $type, $order_id) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, type, order_id, is_read)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->bind_param("issi", $user_id, $message, $type, $order_id);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);

// ----- AJAX HANDLING -----

// Fetch Hero Section
if (isset($_POST['action']) && $_POST['action'] === 'fetch_hero_section') {
    $hero = [];
    try {
        $result = $conn->query("SELECT id, title, description, button_text, main_image, sparkle_image_1, sparkle_image_2 FROM hero_section LIMIT 1");
        $hero = $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("Fetch hero section error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($hero ?: []);
    exit;
}

// Update Hero Section
if (isset($_POST['action']) && $_POST['action'] === 'update_hero_section') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $button_text = trim($_POST['button_text']);
    $existing_main_image = trim($_POST['existing_main_image']);
    $existing_sparkle_1 = trim($_POST['existing_sparkle_1']);
    $existing_sparkle_2 = trim($_POST['existing_sparkle_2']);
    $response = ['success' => false, 'message' => ''];

    if (empty($title) || empty($description) || empty($button_text)) {
        $response['message'] = 'All text fields are required.';
    } else {
        $main_image = $existing_main_image;
        $sparkle_image_1 = $existing_sparkle_1;
        $sparkle_image_2 = $existing_sparkle_2;
        $target_dir = "Uploads/hero/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

        // Handle main image upload
        if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            $imageFileType = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($imageFileType, $allowed_types)) {
                $response['message'] = 'Only JPG, JPEG, PNG, and GIF files are allowed for main image.';
            } elseif ($_FILES['main_image']['size'] > 5000000) {
                $response['message'] = 'Main image size exceeds 5MB limit.';
            } else {
                $unique_name = uniqid('hero_main_') . '.' . $imageFileType;
                $target_file = $target_dir . $unique_name;
                if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target_file)) {
                    if ($main_image && $main_image !== 'images/hero-couple.png' && file_exists($main_image)) {
                        unlink($main_image);
                    }
                    $main_image = $target_file;
                } else {
                    $response['message'] = 'Failed to upload main image.';
                }
            }
        }

        // Handle sparkle image 1 upload
        if (!$response['message'] && !empty($_FILES['sparkle_image_1']['name']) && $_FILES['sparkle_image_1']['error'] === UPLOAD_ERR_OK) {
            $imageFileType = strtolower(pathinfo($_FILES['sparkle_image_1']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($imageFileType, $allowed_types)) {
                $response['message'] = 'Only JPG, JPEG, PNG, and GIF files are allowed for sparkle image 1.';
            } elseif ($_FILES['sparkle_image_1']['size'] > 5000000) {
                $response['message'] = 'Sparkle image 1 size exceeds 5MB limit.';
            } else {
                $unique_name = uniqid('hero_sparkle1_') . '.' . $imageFileType;
                $target_file = $target_dir . $unique_name;
                if (move_uploaded_file($_FILES['sparkle_image_1']['tmp_name'], $target_file)) {
                    if ($sparkle_image_1 && $sparkle_image_1 !== 'images/sparkle-1.png' && file_exists($sparkle_image_1)) {
                        unlink($sparkle_image_1);
                    }
                    $sparkle_image_1 = $target_file;
                } else {
                    $response['message'] = 'Failed to upload sparkle image 1.';
                }
            }
        }

        // Handle sparkle image 2 upload
        if (!$response['message'] && !empty($_FILES['sparkle_image_2']['name']) && $_FILES['sparkle_image_2']['error'] === UPLOAD_ERR_OK) {
            $imageFileType = strtolower(pathinfo($_FILES['sparkle_image_2']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($imageFileType, $allowed_types)) {
                $response['message'] = 'Only JPG, JPEG, PNG, and GIF files are allowed for sparkle image 2.';
            } elseif ($_FILES['sparkle_image_2']['size'] > 5000000) {
                $response['message'] = 'Sparkle image 2 size exceeds 5MB limit.';
            } else {
                $unique_name = uniqid('hero_sparkle2_') . '.' . $imageFileType;
                $target_file = $target_dir . $unique_name;
                if (move_uploaded_file($_FILES['sparkle_image_2']['tmp_name'], $target_file)) {
                    if ($sparkle_image_2 && $sparkle_image_2 !== 'images/sparkle-2.png' && file_exists($sparkle_image_2)) {
                        unlink($sparkle_image_2);
                    }
                    $sparkle_image_2 = $target_file;
                } else {
                    $response['message'] = 'Failed to upload sparkle image 2.';
                }
            }
        }

        if (!$response['message']) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    UPDATE hero_section 
                    SET title = ?, description = ?, button_text = ?, main_image = ?, sparkle_image_1 = ?, sparkle_image_2 = ?
                    WHERE id = 1
                ");
                $stmt->bind_param("ssssss", $title, $description, $button_text, $main_image, $sparkle_image_1, $sparkle_image_2);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                $response['success'] = true;
                $response['message'] = 'Hero section updated successfully.';
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Failed to update hero section: ' . $e->getMessage();
                error_log("Hero section update error: " . $e->getMessage());
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

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
            $query .= " ORDER BY FIELD(o.status, 'processing', 'pending', 'shipped', 'delivered', 'cancelled')";
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

// Fetch Order Details
if (isset($_POST['action']) && $_POST['action'] === 'fetch_order_details') {
    $order_id = (int)$_POST['order_id'];
    $order = [];
    try {
        // Fetch order and customer info
        $stmt = $conn->prepare("
            SELECT o.id, o.user_id, u.full_name, u.email, u.phone, u.address, o.total, o.delivery_fee, o.status, o.created_at
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order['info'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Fetch order items
        $stmt = $conn->prepare("
            SELECT oi.quantity, oi.price, p.name, p.sku
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order['items'] = [];
        while ($row = $result->fetch_assoc()) {
            $order['items'][] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Fetch order details error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode($order);
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
    $estimated_delivery_days = isset($_POST['estimated_delivery_days']) ? (int)$_POST['estimated_delivery_days'] : 0;
    $response = ['success' => false, 'message' => ''];

    // Validate inputs
    if ($order_id <= 0) {
        $response['message'] = 'Invalid order ID.';
    } elseif ($estimated_delivery_days <= 0) {
        $response['message'] = 'Estimated delivery days must be a positive number.';
    } else {
        $conn->begin_transaction();
        try {
            // Check if order exists and is in 'processing' status
            $stmt = $conn->prepare("SELECT user_id, status FROM orders WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if (!$order = $result->fetch_assoc()) {
                throw new Exception("Order not found.");
            }
            if ($order['status'] !== 'processing') {
                throw new Exception("Order is not in a shippable state.");
            }
            $stmt->close();

            // Update order status and delivery days
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = 'shipped', estimated_delivery_days = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $estimated_delivery_days, $order_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update order status.");
            }
            $stmt->close();

            // Create notification
            $message = "Order Received and Ready to Ship. Package will be delivered in $estimated_delivery_days days.";
            if (!createNotification($conn, $order['user_id'], $message, 'shipped', $order_id)) {
                throw new Exception("Failed to create notification.");
            }

            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Order marked as shipped and notification sent.';
        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'Failed to ship order: ' . $e->getMessage();
            error_log("Order ship error: " . $e->getMessage());
        }
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

$hero_section = $conn->query("SELECT id, title, description, button_text, main_image, sparkle_image_1, sparkle_image_2 FROM hero_section LIMIT 1")->fetch_assoc() ?? [];

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
    ORDER BY FIELD(o.status, 'processing', 'pending', 'shipped', 'delivered', 'cancelled')
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
        }

        .table-header {
            padding: clamp(1rem, 2vw, 1.5rem);
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

        .form-input {
            width: 100%;
            padding: clamp(0.6rem, 1.2vw, 0.75rem) clamp(0.8rem, 1.5vw, 1rem);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--background-color);
            color: var(--text-primary);
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
                                    <td><span class="status-badge status-<?php echo htmlspecialchars(strtolower($order['status'] === 'processing' ? 'pending' : $order['status'])); ?>"><?php echo htmlspecialchars($order['status'] === 'processing' ? 'Pending' : $order['status']); ?></span></td>
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
                                <th>Stock</th>
                                <th>Category</th>
                                <th>Featured</th>
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
                                    <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><?php echo $product['featured'] ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="editProduct(<?php echo htmlspecialchars($product['id']); ?>)">Edit</button>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteProduct(<?php echo htmlspecialchars($product['id']); ?>)">Delete</button>
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
                        <select id="orderStatusFilter" onchange="fetchOrders()">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
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
                        <tbody id="ordersTable">
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
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
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTable">
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['id']); ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($category['created_at']))); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="editCategory(<?php echo htmlspecialchars($category['id']); ?>)">Edit</button>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteCategory(<?php echo htmlspecialchars($category['id']); ?>)">Delete</button>
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
                                <th>Full Name</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTable">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><?php echo $user['is_admin'] ? 'Admin' : 'User'; ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user['created_at']))); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="editUser(<?php echo htmlspecialchars($user['id']); ?>)">Edit</button>
                                        <button class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="deleteUser(<?php echo htmlspecialchars($user['id']); ?>)">Delete</button>
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
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Stock Quantity</th>
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
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="editProduct(<?php echo htmlspecialchars($item['id']); ?>)">Edit</button>
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
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>User</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reviewsTable">
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($review['id']); ?></td>
                                    <td><?php echo htmlspecialchars($review['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($review['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($review['rating']); ?>/5</td>
                                    <td><?php echo htmlspecialchars($review['review_text']); ?></td>
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
                        <h3 class="table-title">Settings</h3>
                    </div>
                    <form id="changePasswordForm" onsubmit="event.preventDefault(); changePassword();">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="currentPassword">Current Password</label>
                                <input type="password" id="currentPassword" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="newPassword">New Password</label>
                                <input type="password" id="newPassword" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirmPassword">Confirm Password</label>
                                <input type="password" id="confirmPassword" class="form-input" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Hero Section Tab -->
            <div class="tab-content" id="hero">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Hero Section Management</h3>
                    </div>
                    <form id="heroForm" onsubmit="event.preventDefault(); updateHeroSection();">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="heroTitle">Title</label>
                                <input type="text" id="heroTitle" class="form-input" value="<?php echo htmlspecialchars($hero_section['title'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="heroDescription">Description</label>
                                <textarea id="heroDescription" class="form-input" rows="4" required><?php echo htmlspecialchars($hero_section['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="heroButtonText">Button Text</label>
                                <input type="text" id="heroButtonText" class="form-input" value="<?php echo htmlspecialchars($hero_section['button_text'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="heroMainImage">Main Image</label>
                                <input type="file" id="heroMainImage" class="form-input" accept="image/*">
                                <input type="hidden" id="existingMainImage" value="<?php echo htmlspecialchars($hero_section['main_image'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="heroSparkleImage1">Sparkle Image 1</label>
                                <input type="file" id="heroSparkleImage1" class="form-input" accept="image/*">
                                <input type="hidden" id="existingSparkle1" value="<?php echo htmlspecialchars($hero_section['sparkle_image_1'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="heroSparkleImage2">Sparkle Image 2</label>
                                <input type="file" id="heroSparkleImage2" class="form-input" accept="image/*">
                                <input type="hidden" id="existingSparkle2" value="<?php echo htmlspecialchars($hero_section['sparkle_image_2'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="hero-preview">
                            <h1 id="heroTitlePreview"><?php echo htmlspecialchars($hero_section['title'] ?? 'Your Title'); ?></h1>
                            <p id="heroDescriptionPreview"><?php echo htmlspecialchars($hero_section['description'] ?? 'Your Description'); ?></p>
                            <button class="cta-button" id="heroButtonPreview"><?php echo htmlspecialchars($hero_section['button_text'] ?? 'Shop Now'); ?></button>
                            <div class="hero-image-preview">
                                <img id="heroMainImagePreview" src="<?php echo htmlspecialchars($hero_section['main_image'] ?? 'https://via.placeholder.com/150'); ?>" alt="Main Image" style="width: 100px; height: auto;">
                                <img id="heroSparkle1Preview" src="<?php echo htmlspecialchars($hero_section['sparkle_image_1'] ?? 'https://via.placeholder.com/50'); ?>" alt="Sparkle 1" style="width: 50px; height: auto;">
                                <img id="heroSparkle2Preview" src="<?php echo htmlspecialchars($hero_section['sparkle_image_2'] ?? 'https://via.placeholder.com/50'); ?>" alt="Sparkle 2" style="width: 50px; height: auto;">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Hero Section</button>
                    </form>
                </div>
            </div>

            <!-- Modals -->
            <!-- Add Product Modal -->
            <div class="modal" id="addProductModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add Product</h3>
                        <button class="modal-close" onclick="closeModal('addProductModal')">×</button>
                    </div>
                    <form id="addProductForm" onsubmit="event.preventDefault(); addProduct();">
                        <div class="form-group">
                            <label class="form-label" for="productName">Product Name</label>
                            <input type="text" id="productName" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="productPrice">Price</label>
                            <input type="number" id="productPrice" class="form-input" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="productStock">Stock Quantity</label>
                            <input type="number" id="productStock" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="productCategory">Category</label>
                            <select id="productCategory" class="form-input" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="productMiscAttribute">Miscellaneous Attribute</label>
                            <select id="productMiscAttribute" class="form-input">
                                <option value="">None</option>
                                <option value="new_arrival">New Arrival</option>
                                <option value="featured">Featured</option>
                                <option value="trending">Trending</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="productFeatured">Featured</label>
                            <input type="checkbox" id="productFeatured">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="productDescription">Description</label>
                            <textarea id="productDescription" class="form-input" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="productImage">Image</label>
                            <input type="file" id="productImage" class="form-input" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </form>
                </div>
            </div>

            <!-- Edit Product Modal -->
            <div class="modal" id="editProductModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Edit Product</h3>
                        <button class="modal-close" onclick="closeModal('editProductModal')">×</button>
                    </div>
                    <form id="editProductForm" onsubmit="event.preventDefault(); updateProduct();">
                        <input type="hidden" id="editProductId">
                        <div class="form-group">
                            <label class="form-label" for="editProductName">Product Name</label>
                            <input type="text" id="editProductName" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editProductPrice">Price</label>
                            <input type="number" id="editProductPrice" class="form-input" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editProductStock">Stock Quantity</label>
                            <input type="number" id="editProductStock" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editProductCategory">Category</label>
                            <select id="editProductCategory" class="form-input" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editProductMiscAttribute">Miscellaneous Attribute</label>
                            <select id="editProductMiscAttribute" class="form-input">
                                <option value="">None</option>
                                <option value="new_arrival">New Arrival</option>
                                <option value="featured">Featured</option>
                                <option value="trending">Trending</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editProductFeatured">Featured</label>
                            <input type="checkbox" id="editProductFeatured">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editProductDescription">Description</label>
                            <textarea id="editProductDescription" class="form-input" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editProductImage">Image</label>
                            <input type="file" id="editProductImage" class="form-input" accept="image/*">
                            <input type="hidden" id="existingProductImage">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </form>
                </div>
            </div>

            <!-- Add Category Modal -->
            <div class="modal" id="addCategoryModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add Category</h3>
                        <button class="modal-close" onclick="closeModal('addCategoryModal')">×</button>
                    </div>
                    <form id="addCategoryForm" onsubmit="event.preventDefault(); addCategory();">
                        <div class="form-group">
                            <label class="form-label" for="categoryName">Category Name</label>
                            <input type="text" id="categoryName" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="categoryDescription">Description</label>
                            <textarea id="categoryDescription" class="form-input" rows="4"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </form>
                </div>
            </div>

            <!-- Edit Category Modal -->
            <div class="modal" id="editCategoryModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Edit Category</h3>
                        <button class="modal-close" onclick="closeModal('editCategoryModal')">×</button>
                    </div>
                    <form id="editCategoryForm" onsubmit="event.preventDefault(); updateCategory();">
                        <input type="hidden" id="editCategoryId">
                        <div class="form-group">
                            <label class="form-label" for="editCategoryName">Category Name</label>
                            <input type="text" id="editCategoryName" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editCategoryDescription">Description</label>
                            <textarea id="editCategoryDescription" class="form-input" rows="4"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Category</button>
                    </form>
                </div>
            </div>

            <!-- Add User Modal -->
            <div class="modal" id="addUserModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Add User</h3>
                        <button class="modal-close" onclick="closeModal('addUserModal')">×</button>
                    </div>
                    <form id="addUserForm" onsubmit="event.preventDefault(); addUser();">
                        <div class="form-group">
                            <label class="form-label" for="userEmail">Email</label>
                            <input type="email" id="userEmail" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="userFullName">Full Name</label>
                            <input type="text" id="userFullName" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="userPhone">Phone</label>
                            <input type="text" id="userPhone" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="userPassword">Password</label>
                            <input type="password" id="userPassword" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="userIsAdmin">Admin</label>
                            <input type="checkbox" id="userIsAdmin">
                        </div>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </form>
                </div>
            </div>

            <!-- Edit User Modal -->
            <div class="modal" id="editUserModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Edit User</h3>
                        <button class="modal-close" onclick="closeModal('editUserModal')">×</button>
                    </div>
                    <form id="editUserForm" onsubmit="event.preventDefault(); updateUser();">
                        <input type="hidden" id="editUserId">
                        <div class="form-group">
                            <label class="form-label" for="editUserEmail">Email</label>
                            <input type="email" id="editUserEmail" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editUserFullName">Full Name</label>
                            <input type="text" id="editUserFullName" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editUserPhone">Phone</label>
                            <input type="text" id="editUserPhone" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editUserIsAdmin">Admin</label>
                            <input type="checkbox" id="editUserIsAdmin">
                        </div>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </form>
                </div>
            </div>

            <!-- View Order Modal -->
            <div class="modal" id="viewOrderModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Order Details</h3>
                        <button class="modal-close" onclick="closeModal('viewOrderModal')">×</button>
                    </div>
                    <div id="orderDetails">
                        <h4>Order Information</h4>
                        <p><strong>Order ID:</strong> <span id="orderId"></span></p>
                        <p><strong>Customer:</strong> <span id="orderCustomer"></span></p>
                        <p><strong>Email:</strong> <span id="orderEmail"></span></p>
                        <p><strong>Phone:</strong> <span id="orderPhone"></span></p>
                        <p><strong>Address:</strong> <span id="orderAddress"></span></p>
                        <p><strong>Total:</strong> <span id="orderTotal"></span></p>
                        <p><strong>Delivery Fee:</strong> <span id="orderDeliveryFee"></span></p>
                        <p><strong>Status:</strong> <span id="orderStatus"></span></p>
                        <p><strong>Date:</strong> <span id="orderDate"></span></p>
                        <h4>Order Items</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody id="orderItemsTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Ship Order Modal -->
            <div class="modal" id="shipOrderModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Ship Order</h3>
                        <button class="modal-close" onclick="closeModal('shipOrderModal')">×</button>
                    </div>
                    <form id="shipOrderForm" onsubmit="event.preventDefault(); submitShipOrder();">
                        <input type="hidden" id="shipOrderId">
                        <div class="form-group">
                            <label class="form-label" for="estimatedDeliveryDays">Estimated Delivery Days</label>
                            <input type="number" id="estimatedDeliveryDays" class="form-input" min="1" required>
                        </div>
                        <div class="form-group">
                            <p><strong>Preview Notification:</strong> <span id="deliveryPreview">Order Received and Ready to Ship. Package will be delivered in <span id="deliveryDaysPreview">0</span> days.</span></p>
                        </div>
                        <button type="submit" class="btn btn-primary" id="shipOrderSubmit">Ship Order</button>
                    </form>
                </div>
            </div>

            <!-- Quick Add Modal -->
            <div class="modal" id="quickAddModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Quick Add</h3>
                        <button class="modal-close" onclick="closeModal('quickAddModal')">×</button>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;" onclick="openModal('addProductModal'); closeModal('quickAddModal');">Add Product</button>
                        <button class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;" onclick="openModal('addCategoryModal'); closeModal('quickAddModal');">Add Category</button>
                        <button class="btn btn-primary" style="width: 100%;" onclick="openModal('addUserModal'); closeModal('quickAddModal');">Add User</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ----- Theme Toggle -----
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('themeIcon');
            if (body.dataset.theme === 'dark') {
                body.dataset.theme = 'light';
                themeIcon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                body.dataset.theme = 'dark';
                themeIcon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }

        // Apply saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.body.dataset.theme = savedTheme;
        if (savedTheme === 'dark') {
            document.getElementById('themeIcon').className = 'fas fa-sun';
        }

        // ----- Tab Switching -----
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            document.querySelector(`.nav-link[data-tab="${tabId}"]`).classList.add('active');
            document.getElementById('pageTitle').textContent = tabId.charAt(0).toUpperCase() + tabId.slice(1);
            if (tabId === 'orders') fetchOrders();
        }

        // Initialize default tab
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => switchTab(link.dataset.tab));
        });

        // ----- Modal Handling -----
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            if (modalId === 'shipOrderModal') {
                document.getElementById('shipOrderForm').reset();
                document.getElementById('deliveryDaysPreview').textContent = '0';
                document.getElementById('estimatedDeliveryDays').focus();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // ----- Alert Handling -----
        function showAlert(message, type = 'success') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message} <span class="alert-dismiss" onclick="dismissAlert(this)">×</span>`;
            document.querySelector('.main-content').insertBefore(alert, document.querySelector('.main-content').firstChild);
            setTimeout(() => alert.remove(), 5000);
        }

        function dismissAlert(element) {
            element.parentElement.remove();
        }

        // ----- AJAX Helper -----
        async function sendAjaxRequest(url, data, method = 'POST') {
            const formData = new FormData();
            for (const key in data) {
                formData.append(key, data[key]);
            }
            const response = await fetch(url, {
                method,
                body: method === 'POST' ? formData : undefined,
            });
            return response.json();
        }

        // ----- Hero Section Functions -----
        function updateHeroSection() {
            const formData = new FormData();
            formData.append('action', 'update_hero_section');
            formData.append('title', document.getElementById('heroTitle').value);
            formData.append('description', document.getElementById('heroDescription').value);
            formData.append('button_text', document.getElementById('heroButtonText').value);
            formData.append('existing_main_image', document.getElementById('existingMainImage').value);
            formData.append('existing_sparkle_1', document.getElementById('existingSparkle1').value);
            formData.append('existing_sparkle_2', document.getElementById('existingSparkle2').value);
            if (document.getElementById('heroMainImage').files[0]) {
                formData.append('main_image', document.getElementById('heroMainImage').files[0]);
            }
            if (document.getElementById('heroSparkleImage1').files[0]) {
                formData.append('sparkle_image_1', document.getElementById('heroSparkleImage1').files[0]);
            }
            if (document.getElementById('heroSparkleImage2').files[0]) {
                formData.append('sparkle_image_2', document.getElementById('heroSparkleImage2').files[0]);
            }

            sendAjaxRequest('', formData).then(response => {
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    location.reload();
                }
            });
        }

        // Real-time Hero Preview
        document.getElementById('heroTitle').addEventListener('input', function () {
            document.getElementById('heroTitlePreview').textContent = this.value || 'Your Title';
        });
        document.getElementById('heroDescription').addEventListener('input', function () {
            document.getElementById('heroDescriptionPreview').textContent = this.value || 'Your Description';
        });
        document.getElementById('heroButtonText').addEventListener('input', function () {
            document.getElementById('heroButtonPreview').textContent = this.value || 'Shop Now';
        });
        document.getElementById('heroMainImage').addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                document.getElementById('heroMainImagePreview').src = URL.createObjectURL(file);
            }
        });
        document.getElementById('heroSparkleImage1').addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                document.getElementById('heroSparkle1Preview').src = URL.createObjectURL(file);
            }
        });
        document.getElementById('heroSparkleImage2').addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                document.getElementById('heroSparkle2Preview').src = URL.createObjectURL(file);
            }
        });

        // ----- Product Functions -----
        function addProduct() {
            const formData = new FormData();
            formData.append('action', 'add_product');
            formData.append('name', document.getElementById('productName').value);
            formData.append('price', document.getElementById('productPrice').value);
            formData.append('stock_quantity', document.getElementById('productStock').value);
            formData.append('category_id', document.getElementById('productCategory').value);
            formData.append('misc_attribute', document.getElementById('productMiscAttribute').value);
            formData.append('featured', document.getElementById('productFeatured').checked ? 1 : 0);
            formData.append('description', document.getElementById('productDescription').value);
            if (document.getElementById('productImage').files[0]) {
                formData.append('image', document.getElementById('productImage').files[0]);
            }

            sendAjaxRequest('', formData).then(response => {
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    closeModal('addProductModal');
                    location.reload();
                }
            });
        }

        function editProduct(id) {
            sendAjaxRequest('', { action: 'search_products', search: `id:${id}` }).then(products => {
                if (products.length > 0) {
                    const product = products[0];
                    document.getElementById('editProductId').value = product.id;
                    document.getElementById('editProductName').value = product.name;
                    document.getElementById('editProductPrice').value = product.price;
                    document.getElementById('editProductStock').value = product.stock_quantity;
                    document.getElementById('editProductCategory').value = product.category_id || '';
                    document.getElementById('editProductMiscAttribute').value = product.misc_attribute || '';
                    document.getElementById('editProductFeatured').checked = product.featured == 1;
                    document.getElementById('editProductDescription').value = product.description || '';
                    document.getElementById('existingProductImage').value = product.image || '';
                    openModal('editProductModal');
                } else {
                    showAlert('Product not found.', 'error');
                }
            });
        }

        function updateProduct() {
            const formData = new FormData();
            formData.append('action', 'edit_product');
            formData.append('product_id', document.getElementById('editProductId').value);
            formData.append('name', document.getElementById('editProductName').value);
            formData.append('price', document.getElementById('editProductPrice').value);
            formData.append('stock_quantity', document.getElementById('editProductStock').value);
            formData.append('category_id', document.getElementById('editProductCategory').value);
            formData.append('misc_attribute', document.getElementById('editProductMiscAttribute').value);
            formData.append('featured', document.getElementById('editProductFeatured').checked ? 1 : 0);
            formData.append('description', document.getElementById('editProductDescription').value);
            formData.append('existing_image', document.getElementById('existingProductImage').value);
            if (document.getElementById('editProductImage').files[0]) {
                formData.append('image', document.getElementById('editProductImage').files[0]);
            }

            sendAjaxRequest('', formData).then(response => {
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    closeModal('editProductModal');
                    location.reload();
                }
            });
        }

        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                sendAjaxRequest('', { action: 'delete_product', product_id: id }).then(response => {
                    showAlert(response.message, response.success ? 'success' : 'error');
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        }

        // ----- Order Functions -----
        function fetchOrders() {
            const status = document.getElementById('orderStatusFilter').value;
            sendAjaxRequest('', { action: 'fetch_orders', status }).then(orders => {
                const tbody = document.getElementById('ordersTable');
                tbody.innerHTML = '';
                orders.forEach(order => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>#${order.id}</td>
                        <td>${order.full_name}</td>
                        <td>$${parseFloat(order.total).toFixed(2)}</td>
                        <td><span class="status-badge status-${order.status.toLowerCase()}">${order.status}</span></td>
                        <td>${new Date(order.created_at).toLocaleDateString()}</td>
                        <td>
                            <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="viewOrder(${order.id})">View</button>
                            ${order.status === 'processing' ? `<button class="btn btn-success" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick="shipOrder(${order.id})">Ship</button>` : ''}
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            });
        }

        function viewOrder(id) {
            sendAjaxRequest('', { action: 'fetch_order_details', order_id: id }).then(order => {
                if (order.info) {
                    document.getElementById('orderId').textContent = `#${order.info.id}`;
                    document.getElementById('orderCustomer').textContent = order.info.full_name;
                    document.getElementById('orderEmail').textContent = order.info.email;
                    document.getElementById('orderPhone').textContent = order.info.phone;
                    document.getElementById('orderAddress').textContent = order.info.address;
                    document.getElementById('orderTotal').textContent = `$${parseFloat(order.info.total).toFixed(2)}`;
                    document.getElementById('orderDeliveryFee').textContent = `$${parseFloat(order.info.delivery_fee).toFixed(2)}`;
                    document.getElementById('orderStatus').textContent = order.info.status;
                    document.getElementById('orderDate').textContent = new Date(order.info.created_at).toLocaleDateString();
                    const itemsTable = document.getElementById('orderItemsTable');
                    itemsTable.innerHTML = '';
                    order.items.forEach(item => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${item.name}</td>
                            <td>${item.sku}</td>
                            <td>${item.quantity}</td>
                            <td>$${parseFloat(item.price).toFixed(2)}</td>
                        `;
                        itemsTable.appendChild(row);
                    });
                    openModal('viewOrderModal');
                } else {
                    showAlert('Order not found.', 'error');
                }
            });
        }

        function shipOrder(orderId) {
            document.getElementById('shipOrderId').value = orderId;
            document.getElementById('shipOrderForm').reset();
            document.getElementById('estimatedDeliveryDays').value = '';
            document.getElementById('deliveryDaysPreview').textContent = '0';
            openModal('shipOrderModal');
        }

        function submitShipOrder() {
            const orderId = document.getElementById('shipOrderId').value;
            const estimatedDeliveryDays = document.getElementById('estimatedDeliveryDays').value;
            const submitButton = document.getElementById('shipOrderSubmit');

            if (estimatedDeliveryDays <= 0) {
                showAlert('Estimated delivery days must be a positive number.', 'error');
                return;
            }

            submitButton.classList.add('btn-loading');
            submitButton.disabled = true;

            sendAjaxRequest('', {
                action: 'ship_order',
                order_id: orderId,
                estimated_delivery_days: estimatedDeliveryDays
            }).then(response => {
                submitButton.classList.remove('btn-loading');
                submitButton.disabled = false;
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    closeModal('shipOrderModal');
                    fetchOrders();
                }
            }).catch(error => {
                submitButton.classList.remove('btn-loading');
                submitButton.disabled = false;
                showAlert('An error occurred while processing the request.', 'error');
                console.error(error);
            });
        }

        // Real-time delivery days preview
        document.getElementById('estimatedDeliveryDays').addEventListener('input', function () {
            const days = parseInt(this.value) || 0;
            document.getElementById('deliveryDaysPreview').textContent = days;
        });

        // ----- Category Functions -----
        function addCategory() {
            sendAjaxRequest('', {
                action: 'add_category',
                name: document.getElementById('categoryName').value,
                description: document.getElementById('categoryDescription').value
            }).then(response => {
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    closeModal('addCategoryModal');
                    location.reload();
                }
            });
        }

        function editCategory(id) {
            sendAjaxRequest('', { action: 'fetch_categories' }).then(categories => {
                const category = categories.find(c => c.id == id);
                if (category) {
                    document.getElementById('editCategoryId').value = category.id;
                    document.getElementById('editCategoryName').value = category.name;
                    document.getElementById('editCategoryDescription').value = category.description || '';
                    openModal('editCategoryModal');
                } else {
                    showAlert('Category not found.', 'error');
                }
            });
        }

        function updateCategory() {
            sendAjaxRequest('', {
                action: 'edit_category',
                category_id: document.getElementById('editCategoryId').value,
                name: document.getElementById('editCategoryName').value,
                description: document.getElementById('editCategoryDescription').value
            }).then(response => {
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    closeModal('editCategoryModal');
                    location.reload();
                }
            });
        }

        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category?')) {
                sendAjaxRequest('', { action: 'delete_category', category_id: id }).then(response => {
                    showAlert(response.message, response.success ? 'success' : 'error');
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        }

        // ----- User Functions -----
        function addUser() {
            sendAjaxRequest('', {
                action: 'add_user',
                email: document.getElementById('userEmail').value,
                full_name: document.getElementById('userFullName').value,
                phone: document.getElementById('userPhone').value,
                password: document.getElementById('userPassword').value,
                is_admin: document.getElementById('userIsAdmin').checked ? 1 : 0
            }).then(response => {
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    closeModal('addUserModal');
                    location.reload();
                }
            });
        }

        function editUser(id) {
            sendAjaxRequest('', { action: 'fetch_users' }).then(users => {
                const user = users.find(u => u.id == id);
                if (user) {
                    document.getElementById('editUserId').value = user.id;
                    document.getElementById('editUserEmail').value = user.email;
                    document.getElementById('editUserFullName').value = user.full_name;
                    document.getElementById('editUserPhone').value = user.phone;
                    document.getElementById('editUserIsAdmin').checked = user.is_admin == 1;
                    openModal('editUserModal');
                } else {
                    showAlert('User not found.', 'error');
                }
            });
        }

        function updateUser() {
            sendAjaxRequest('', {
                action: 'edit_user',
                user_id: document.getElementById('editUserId').value,
                email: document.getElementById('editUserEmail').value,
                full_name: document.getElementById('editUserFullName').value,
                phone: document.getElementById('editUserPhone').value,
                is_admin: document.getElementById('editUserIsAdmin').checked ? 1 : 0
            }).then(response => {
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    closeModal('editUserModal');
                    location.reload();
                }
            });
        }

        function deleteUser(id) {
            if (confirm('Are you sure you want to delete this user?')) {
                sendAjaxRequest('', { action: 'delete_user', user_id: id }).then(response => {
                    showAlert(response.message, response.success ? 'success' : 'error');
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        }

        // ----- Review Functions -----
        function deleteReview(id) {
            if (confirm('Are you sure you want to delete this review?')) {
                sendAjaxRequest('', { action: 'delete_review', review_id: id }).then(response => {
                    showAlert(response.message, response.success ? 'success' : 'error');
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        }

        // ----- Password Change -----
        function changePassword() {
            sendAjaxRequest('', {
                action: 'change_password',
                current_password: document.getElementById('currentPassword').value,
                new_password: document.getElementById('newPassword').value,
                confirm_password: document.getElementById('confirmPassword').value
            }).then(response => {
                showAlert(response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    document.getElementById('changePasswordForm').reset();
                }
            });
        }

        // ----- Quick Add -----
        function openQuickAdd() {
            openModal('quickAddModal');
        }

        // Initialize
        fetchOrders();
    </script>
</body>
</html>