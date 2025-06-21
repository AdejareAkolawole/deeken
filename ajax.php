<?php
// ajax.php
include 'config.php';

// Enable error logging but disable display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log'); // Ensure this path is writable

ob_start(); // Start output buffering

// Get current user (assuming this function exists in config.php)
$user = getCurrentUser();

// Require admin access (assuming this function exists in config.php)
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

// Helper function to handle image uploads
function handleImageUpload($file_key, $existing_path, $target_dir, $prefix = 'image') {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        error_log("$file_key: No file uploaded.");
        return ['path' => $existing_path, 'error' => null];
    }

    error_log("Processing $file_key upload: " . $_FILES[$file_key]['name']);
    $error_code = $_FILES[$file_key]['error'];
    if ($error_code !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
        ];
        $error_msg = $upload_errors[$error_code] ?? 'Unknown upload error.';
        error_log("$file_key upload error code: $error_code - $error_msg");
        return ['path' => $existing_path, 'error' => $error_msg];
    }

    $imageFileType = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowed_types)) {
        error_log("$file_key: Invalid file type: $imageFileType");
        return ['path' => $existing_path, 'error' => 'Only JPG, JPEG, PNG, and GIF files are allowed.'];
    }

    if ($_FILES[$file_key]['size'] > 5000000) { // 5MB limit
        error_log("$file_key: File size exceeds 5MB: " . $_FILES[$file_key]['size']);
        return ['path' => $existing_path, 'error' => 'Image size exceeds 5MB limit.'];
    }

    if (!is_writable($target_dir)) {
        error_log("$file_key: Directory not writable: $target_dir");
        return ['path' => $existing_path, 'error' => 'Upload directory is not writable.'];
    }

    $unique_name = uniqid("{$prefix}_") . '.' . $imageFileType;
    $target_file = $target_dir . $unique_name;
    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
        if ($existing_path && $existing_path !== "images/{$file_key}.png" && $existing_path !== 'https://via.placeholder.com/150' && $existing_path !== 'https://via.placeholder.com/50' && file_exists($existing_path)) {
            unlink($existing_path);
            error_log("Deleted old $file_key: $existing_path");
        }
        return ['path' => $target_file, 'error' => null];
    } else {
        error_log("$file_key: Failed to move uploaded file to: $target_file");
        return ['path' => $existing_path, 'error' => 'Failed to upload image'];
    }
}

// AJAX Handlers
if (!isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

switch ($_POST['action']) {
    case 'fetch_hero_section':
        $hero = [];
        try {
            $stmt = $conn->prepare("SELECT id, title, description, button_text, main_image, sparkle_image_1, sparkle_image_2 FROM hero_section WHERE id = 1");
            $stmt->execute();
            $hero = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            // Use placeholders if images are missing or invalid
            if ($hero) {
                $hero['main_image'] = $hero['main_image'] && file_exists($hero['main_image']) ? $hero['main_image'] : 'https://via.placeholder.com/150';
                $hero['sparkle_image_1'] = $hero['sparkle_image_1'] && file_exists($hero['sparkle_image_1']) ? $hero['sparkle_image_1'] : 'https://via.placeholder.com/50';
                $hero['sparkle_image_2'] = $hero['sparkle_image_2'] && file_exists($hero['sparkle_image_2']) ? $hero['sparkle_image_2'] : 'https://via.placeholder.com/50';
            }
        } catch (Exception $e) {
            error_log("Fetch hero section error: " . $e->getMessage());
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($hero ?: [
            'id' => 1,
            'title' => 'Welcome to Deeken',
            'description' => 'Discover our amazing products.',
            'button_text' => 'Shop Now',
            'main_image' => 'https://via.placeholder.com/150',
            'sparkle_image_1' => 'https://via.placeholder.com/50',
            'sparkle_image_2' => 'https://via.placeholder.com/50'
        ]);
        exit;

    case 'update_hero_section':
        error_log("Starting update_hero_section handler");
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $button_text = trim($_POST['button_text'] ?? '');
        $existing_main_image = trim($_POST['existing_main_image'] ?? '');
        $existing_sparkle_1 = trim($_POST['existing_sparkle_1'] ?? '');
        $existing_sparkle_2 = trim($_POST['existing_sparkle_2'] ?? '');
        $response = ['success' => false, 'message' => ''];

        error_log("Input data: " . print_r([
            'title' => $title,
            'description' => $description,
            'button_text' => $button_text,
            'existing_main_image' => $existing_main_image,
            'existing_sparkle_1' => $existing_sparkle_1,
            'existing_sparkle_2' => $existing_sparkle_2,
            'files' => array_keys($_FILES)
        ], true));

        if (empty($title) || empty($description) || empty($button_text)) {
            $response['message'] = 'All text fields are required.';
        } else {
            $main_image = $existing_main_image;
            $sparkle_image_1 = $existing_sparkle_1;
            $sparkle_image_2 = $existing_sparkle_2;
            $target_dir = "Uploads/hero/";
            if (!is_dir($target_dir)) {
                if (!mkdir($target_dir, 0755, true)) {
                    error_log("Failed to create directory: $target_dir");
                    $response['message'] = 'Failed to create upload directory.';
                }
            }

            // Handle main image
            if (!$response['message']) {
                $main_result = handleImageUpload('main_image', $main_image, $target_dir, 'hero_main');
                if ($main_result['error']) {
                    $response['message'] = $main_result['error'];
                } else {
                    $main_image = $main_result['path'];
                }
            }

            // Handle sparkle image 1
            if (!$response['message']) {
                $sparkle1_result = handleImageUpload('sparkle_image_1', $sparkle_image_1, $target_dir, 'hero_sparkle1');
                if ($sparkle1_result['error']) {
                    $response['message'] = $sparkle1_result['error'];
                } else {
                    $sparkle_image_1 = $sparkle1_result['path'];
                }
            }

            // Handle sparkle image 2
            if (!$response['message']) {
                $sparkle2_result = handleImageUpload('sparkle_image_2', $sparkle_image_2, $target_dir, 'hero_sparkle2');
                if ($sparkle2_result['error']) {
                    $response['message'] = $sparkle2_result['error'];
                } else {
                    $sparkle_image_2 = $sparkle2_result['path'];
                }
            }

            // Update database if no errors
            if (!$response['message']) {
                $conn->begin_transaction();
                try {
                    // Check if hero_section row exists, insert if not
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM hero_section WHERE id = 1");
                    $stmt->execute();
                    $exists = $stmt->get_result()->fetch_assoc()['count'] > 0;
                    $stmt->close();

                    if ($exists) {
                        $stmt = $conn->prepare("
                            UPDATE hero_section 
                            SET title = ?, description = ?, button_text = ?, main_image = ?, sparkle_image_1 = ?, sparkle_image_2 = ?
                            WHERE id = 1
                        ");
                        $stmt->bind_param("ssssss", $title, $description, $button_text, $main_image, $sparkle_image_1, $sparkle_image_2);
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO hero_section (id, title, description, button_text, main_image, sparkle_image_1, sparkle_image_2)
                            VALUES (1, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->bind_param("ssssss", $title, $description, $button_text, $main_image, $sparkle_image_1, $sparkle_image_2);
                    }

                    if (!$stmt->execute()) {
                        throw new Exception("Database update failed: " . $stmt->error);
                    }
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'search_products':
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $products = [];
        try {
            $query = "
                SELECT p.id, p.name, p.sku, p.price, p.description, i.stock_quantity, p.image, c.name AS category, c.id AS category_id, ma.attribute AS misc_attribute, p.featured
                FROM products p
                LEFT JOIN inventory i ON p.id = i.product_id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN miscellaneous_attributes ma ON p.id = ma.product_id
                WHERE p.name LIKE ? OR c.name LIKE ? OR p.id = ?
            ";
            $stmt = $conn->prepare($query);
            $search_param = "%$search%";
            $id_search = filter_var($search, FILTER_VALIDATE_INT) ?: 0;
            $stmt->bind_param("ssi", $search_param, $search_param, $id_search);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Search products error: " . $e->getMessage());
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($products);
        exit;

    case 'fetch_orders':
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($orders);
        exit;

    case 'fetch_order_details':
        $order_id = (int)($_POST['order_id'] ?? 0);
        $order = [];
        try {
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($order);
        exit;

    case 'fetch_categories':
        $categories = [];
        try {
            $result = $conn->query("SELECT id, name, description, created_at FROM categories");
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        } catch (Exception $e) {
            error_log("Fetch categories error: " . $e->getMessage());
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($categories);
        exit;

    case 'fetch_users':
        $users = [];
        try {
            $result = $conn->query("SELECT id, email, full_name, phone, is_admin, created_at FROM users");
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        } catch (Exception $e) {
            error_log("Fetch users error: " . $e->getMessage());
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($users);
        exit;

    case 'fetch_reviews':
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($reviews);
        exit;

    case 'add_product':
        error_log("Starting add_product handler");
        $product_name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $misc_attribute = trim($_POST['misc_attribute'] ?? '');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');
        $image_url = 'https://via.placeholder.com/150';
        $response = ['success' => false, 'message' => ''];

        error_log("Input data: " . print_r([
            'name' => $product_name,
            'price' => $price,
            'stock_quantity' => $stock_quantity,
            'category_id' => $category_id,
            'misc_attribute' => $misc_attribute,
            'featured' => $featured,
            'description' => $description,
            'files' => array_keys($_FILES)
        ], true));

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
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                    error_log("Created directory: $target_dir");
                }
                if (!empty($_FILES['image']['name'])) {
                    $image_result = handleImageUpload('image', $image_url, $target_dir, 'product');
                    if ($image_result['error']) {
                        $response['message'] = $image_result['error'];
                    } else {
                        $image_url = $image_result['path'];
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'edit_product':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $product_name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $misc_attribute = trim($_POST['misc_attribute'] ?? '');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');
        $image_url = $_POST['existing_image'] ?? 'https://via.placeholder.com/150';
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
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                    error_log("Created directory: $target_dir");
                }
                if (!empty($_FILES['image']['name'])) {
                    $image_result = handleImageUpload('image', $image_url, $target_dir, 'product');
                    if ($image_result['error']) {
                        $response['message'] = $image_result['error'];
                    } else {
                        $image_url = $image_result['path'];
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'delete_product':
        $product_id = (int)($_POST['product_id'] ?? 0);
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'add_category':
        $category_name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'edit_category':
        $category_id = (int)($_POST['category_id'] ?? 0);
        $category_name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'delete_category':
        $category_id = (int)($_POST['category_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'add_user':
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $password = trim($_POST['password'] ?? '');
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'edit_user':
        $user_id = (int)($_POST['user_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'delete_user':
        $user_id = (int)($_POST['user_id'] ?? 0);
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'ship_order':
        $order_id = (int)($_POST['order_id'] ?? 0);
        $estimated_delivery_days = (int)($_POST['estimated_delivery_days'] ?? 0);
        $response = ['success' => false, 'message' => ''];

        if ($order_id <= 0) {
            $response['message'] = 'Invalid order ID.';
        } elseif ($estimated_delivery_days <= 0) {
            $response['message'] = 'Estimated delivery days must be a positive number.';
        } else {
            $conn->begin_transaction();
            try {
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'delete_review':
        $review_id = (int)($_POST['review_id'] ?? 0);
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    case 'change_password':
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
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
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    default:
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

ob_end_clean(); // Clear output buffer
?>