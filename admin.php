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
// ----- AJAX FETCH DELIVERY FEES -----
if (isset($_POST['action']) && $_POST['action'] === 'fetch_delivery_fees') {
    $delivery_fees = [];
    try {
        $result = $conn->query("SELECT id, name, fee, min_order_amount, description, is_active FROM delivery_fees");
        while ($row = $result->fetch_assoc()) {
            $delivery_fees[] = $row;
        }
    } catch (Exception $e) {
        error_log("Fetch delivery fees error: " . $e->getMessage());
    }

    header('Content-Type: application/json');
    echo json_encode($delivery_fees);
    exit;
}

// ----- DELIVERY FEE CREATION HANDLING -----
if (isset($_POST['add_delivery_fee'])) {
    $name = trim($_POST['name']);
    $fee = (float)$_POST['fee'];
    $min_order_amount = !empty($_POST['min_order_amount']) ? (float)$_POST['min_order_amount'] : null;
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $error_message = "Delivery fee name is required.";
    } elseif ($fee < 0) {
        $error_message = "Fee must be non-negative.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO delivery_fees (name, fee, min_order_amount, description, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sddsi", $name, $fee, $min_order_amount, $description, $is_active);
            if ($stmt->execute()) {
                $success_message = "Delivery fee added successfully.";
            } else {
                throw new Exception("Failed to add delivery fee.");
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Failed to add delivery fee: " . $e->getMessage();
            error_log("Delivery fee creation error: " . $e->getMessage());
        }
    }
}

// ----- DELIVERY FEE EDIT HANDLING -----
if (isset($_POST['edit_delivery_fee'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $fee = (float)$_POST['fee'];
    $min_order_amount = !empty($_POST['min_order_amount']) ? (float)$_POST['min_order_amount'] : null;
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $error_message = "Delivery fee name is required.";
    } elseif ($fee < 0) {
        $error_message = "Fee must be non-negative.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE delivery_fees SET name = ?, fee = ?, min_order_amount = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("sddsii", $name, $fee, $min_order_amount, $description, $is_active, $id);
            if ($stmt->execute()) {
                $success_message = "Delivery fee updated successfully.";
            } else {
                throw new Exception("Failed to update delivery fee.");
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Failed to update delivery fee: " . $e->getMessage();
            error_log("Delivery fee update error: " . $e->getMessage());
        }
    }
}

// ----- DELIVERY FEE DELETION HANDLING -----
if (isset($_POST['delete_delivery_fee'])) {
    $id = (int)$_POST['id'];

    try {
        // Check if delivery fee is associated with orders
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE delivery_fee_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($order_count > 0) {
            $error_message = "Cannot delete delivery fee because it is associated with orders.";
        } else {
            $stmt = $conn->prepare("DELETE FROM delivery_fees WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_message = "Delivery fee deleted successfully.";
            } else {
                throw new Exception("Failed to delete delivery fee.");
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $error_message = "Failed to delete delivery fee: " . $e->getMessage();
        error_log("Delivery fee deletion error: " . $e->getMessage());
    }
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
            error_log("Category creation error: " . $e->getMessage());
        }
    }
}

// ----- CATEGORY EDIT HANDLING -----
if (isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $category_name = trim($_POST['category_name']);
    $category_description = trim($_POST['category_description']);

    if (empty($category_name)) {
        $error_message = "Category name is required.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $category_name, $category_description, $category_id);
            if ($stmt->execute()) {
                $success_message = "Category updated successfully.";
            } else {
                throw new Exception("Failed to update category.");
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Failed to update category: " . $e->getMessage();
            error_log("Category update error: " . $e->getMessage());
        }
    }
}

// ----- CATEGORY DELETION HANDLING -----
if (isset($_POST['delete_category'])) {
    $category_id = (int)$_POST['id'];

    try {
        // Check if category has associated products
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($product_count > 0) {
            $error_message = "Cannot delete category because it has associated products.";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $category_id);
            if ($stmt->execute()) {
                $success_message = "Category deleted successfully.";
            } else {
                throw new Exception("Failed to delete category.");
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $error_message = "Failed to delete category: " . $e->getMessage();
        error_log("Category deletion error: " . $e->getMessage());
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
    $image_url = 'https://via.placeholder.com/150';

    // Validate inputs
    if (empty($product_name)) {
        $error_message = "Product name is required.";
    } elseif ($price <= 0) {
        $error_message = "Price must be greater than 0.";
    } elseif ($category_id <= 0) {
        $error_message = "Please select a valid category.";
    } else {
        // Validate misc_attribute
        $valid_attributes = ['new_arrival', 'featured', 'trending', ''];
        if (!in_array($misc_attribute, $valid_attributes)) {
            $error_message = "Invalid miscellaneous attribute selected.";
        } else {
            // Handle file upload
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                if (!is_writable($target_dir)) {
                    $error_message = "Upload directory is not writable.";
                } else {
                    $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($imageFileType, $allowed_types)) {
                        $error_message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
                    } elseif ($_FILES['image']['size'] > 5000000) {
                        $error_message = "Image size exceeds 5MB limit.";
                    } else {
                // Generate unique file name to prevent overwrites
                $unique_name = uniqid('img_') . '.' . $imageFileType;
                $target_file = $target_dir . $unique_name;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_url = $target_file;
                } else {
                    $error_message = "Failed to upload image.";
                }
            }
                }
            }

            if (!isset($error_message)) {
                $conn->begin_transaction();
                try {
                    // Check for duplicate SKU
                    $sku = "PROD" . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE sku = ?");
                    $stmt->bind_param("s", $sku);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->fetch_assoc()['count'] > 0) {
                        throw new Exception("Generated SKU already exists.");
                    }
                    $stmt->close();

                    // Insert product
                    $stmt = $conn->prepare("
                        INSERT INTO products (category_id, name, sku, price, image, description, featured, rating)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 0.0)
                    ");
                    $description = !empty($product_description) ? $product_description : "No description provided.";
                    $stmt->bind_param("issdssi", $category_id, $product_name, $sku, $price, $image_url, $description, $featured);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to add product: " . $conn->error);
                    }
                    $product_id = $conn->insert_id;
                    $stmt->close();

                    // Insert inventory
                    $inv_stmt = $conn->prepare("INSERT INTO inventory (product_id, stock_quantity) VALUES (?, ?)");
                    $inv_stmt->bind_param("ii", $product_id, $stock_quantity);
                    if (!$inv_stmt->execute()) {
                        throw new Exception("Failed to add inventory: " . $conn->error);
                    }
                    $inv_stmt->close();
                    // Insert miscellaneous attribute
                    if (!empty($misc_attribute)) {
                        $attr_stmt = $conn->prepare("INSERT INTO miscellaneous_attributes (product_id, attribute) VALUES (?, ?)");
                        $attr_stmt->bind_param("is", $product_id, $misc_attribute);
                        if (!$attr_stmt->execute()) {
                            throw new Exception("Failed to add miscellaneous attribute: " . $conn->error);
                        }
                        $attr_stmt->close();
                    }

                    $conn->commit();
                    $success_message = "Product added successfully.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Failed to add product: " . $e->getMessage();
                    error_log("Product addition error: " . $e->getMessage());
                }
            }
        }
    }
}

// ----- PRODUCT EDIT HANDLING -----
if (isset($_POST['edit_product'])) {
    $product_id = (int)$_POST['product_id'];
    $product_name = trim($_POST['product_name']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category_id = (int)$_POST['category_id'];
    $misc_attribute = trim($_POST['misc_attribute']); // Fixed line
    $featured = isset($_POST['featured']) ? 1 : 0;
    $product_description = trim($_POST['product_description']);
    $image_url = $_POST['existing_image'];

    // Validate inputs
    if (empty($product_name)) {
        $error_message = "Product name is required.";
    } elseif ($price <= 0) {
        $error_message = "Price must be greater than 0.";
    } elseif ($category_id <= 0) {
        $error_message = "Please select a valid category.";
    } else {
        // Validate misc_attribute
        $valid_attributes = ['new_arrival', 'featured', 'trending', ''];
        if (!in_array($misc_attribute, $valid_attributes)) {
            $error_message = "Invalid miscellaneous attribute selected.";
        } else {
            // Handle file upload
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                if (!is_writable($target_dir)) {
                    $error_message = "Upload directory is not writable.";
                } else {
                    $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($imageFileType, $allowed_types)) {
                        $error_message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
                    } elseif ($_FILES['image']['size'] > 5000000) {
                        $error_message = "Image size exceeds 5MB limit.";
                    } else {
                        // Generate unique file name
                        $unique_name = uniqid('img_') . '.' . $imageFileType;
                        $target_file = $target_dir . $unique_name;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                            // Delete old image if it exists and is not the default
                            if ($image_url !== 'https://via.placeholder.com/150' && file_exists($image_url)) {
                                unlink($image_url);
                            }
                            $image_url = $target_file;
                        } else {
                            $error_message = "Failed to upload new image.";
                        }
                    }
                }
            }

            if (!isset($error_message)) {
                $conn->begin_transaction();
                try {
                    // Update product
                    $stmt = $conn->prepare("
                        UPDATE products 
                        SET category_id = ?, name = ?, price = ?, image = ?, description = ?, featured = ?
                        WHERE id = ?
                    ");
                    $description = !empty($product_description) ? $product_description : "No description provided.";
                    $stmt->bind_param("isdssii", $category_id, $product_name, $price, $image_url, $description, $featured, $product_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update product: " . $conn->error);
                    }
                    $stmt->close();

                    // Update inventory
                    $inv_stmt = $conn->prepare("UPDATE inventory SET stock_quantity = ? WHERE product_id = ?");
                    $inv_stmt->bind_param("ii", $stock_quantity, $product_id);
                    if (!$inv_stmt->execute()) {
                        throw new Exception("Failed to update inventory: " . $conn->error);
                    }
                    $inv_stmt->close();

                    // Update or insert miscellaneous attribute
                    $attr_stmt = $conn->prepare("DELETE FROM miscellaneous_attributes WHERE product_id = ?");
                    $attr_stmt->bind_param("i", $product_id);
                    $attr_stmt->execute();
                    $attr_stmt->close();

                    if (!empty($misc_attribute)) {
                        $attr_stmt = $conn->prepare("INSERT INTO miscellaneous_attributes (product_id, attribute) VALUES (?, ?)");
                        $attr_stmt->bind_param("is", $product_id, $misc_attribute);
                        if (!$attr_stmt->execute()) {
                            throw new Exception("Failed to update miscellaneous attribute: " . $conn->error);
                        }
                        $attr_stmt->close();
                    }

                    $conn->commit();
                    $success_message = "Product updated successfully.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Failed to update product: " . $e->getMessage();
                    error_log("Product update error: " . $e->getMessage());
                }
            }
        }
    }
}

// ----- PRODUCT DELETION HANDLING -----
if (isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];

    $conn->begin_transaction();
    try {
        // Fetch image path to delete file
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

        // Delete related records using prepared statements
        $tables = ['cart', 'order_items', 'reviews', 'inventory', 'miscellaneous_attributes'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM $table WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete from $table: " . $conn->error);
            }
            $stmt->close();
        }

        // Delete product
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete product: " . $conn->error);
        }
        $stmt->close();

        $conn->commit();
        $success_message = "Product deleted successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Failed to delete product: " . $e->getMessage();
        error_log("Product deletion error: " . $e->getMessage());
    }
}

// ----- CAROUSEL IMAGE UPLOAD HANDLING -----
if (isset($_POST['add_carousel_image'])) {
    $title = trim($_POST['title']);
    $subtitle = trim($_POST['subtitle']);
    $link = trim($_POST['link']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $image_url = 'https://via.placeholder.com/400x300';

    if (empty($title) || empty($subtitle)) {
        $error_message = "Title and subtitle are required.";
    } else {
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "Uploads/carousel/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            if (!is_writable($target_dir)) {
                $error_message = "Carousel upload directory is not writable.";
            } else {
                $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($imageFileType, $allowed_types)) {
                    $error_message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
                } elseif ($_FILES['image']['size'] > 5000000) {
                    $error_message = "Image size exceeds 5MB limit.";
                } else {
                    $unique_name = uniqid('carousel_') . '.' . $imageFileType;
                    $target_file = $target_dir . $unique_name;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $image_url = $target_file;
                    } else {
                        $error_message = "Failed to upload carousel image.";
                    }
                }
            }
        }

        if (!isset($error_message)) {
            try {
                $stmt = $conn->prepare("INSERT INTO carousel_images (title, subtitle, image, link, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssi", $title, $subtitle, $image_url, $link, $is_active);
                if ($stmt->execute()) {
                    $success_message = "Carousel image added successfully.";
                } else {
                    throw new Exception("Failed to add carousel image: " . $conn->error);
                }
                $stmt->close();
            } catch (Exception $e) {
                $error_message = "Failed to add carousel image: " . $e->getMessage();
                error_log("Carousel image addition error: " . $e->getMessage());
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
            if ($image_path !== 'https://via.placeholder.com/400x300' && file_exists($image_path)) {
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
            throw new Exception("Failed to delete carousel image: " . $conn->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Failed to delete carousel image: " . $e->getMessage();
        error_log("Carousel image deletion error: " . $e->getMessage());
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
            throw new Exception("Failed to update carousel image status: " . $conn->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Failed to update carousel image status: " . $e->getMessage();
        error_log("Carousel image status update error: " . $e->getMessage());
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
            error_log("Password change error: " . $e->getMessage());
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
    SELECT p.id, p.name, p.price, p.description, i.stock_quantity, p.image, c.name AS category, ma.attribute AS misc_attribute, p.featured
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
$result = $conn->query("SELECT id, name, description FROM categories");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// ----- FETCH CAROUSEL IMAGES -----
$carousel_images = [];
$result = $conn->query("SELECT * FROM carousel_images ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $carousel_images[] = $row;
}
// ----- FETCH DELIVERY FEES -----
$delivery_fees = [];
$result = $conn->query("SELECT id, name, fee, min_order_amount, description, is_active FROM delivery_fees");
while ($row = $result->fetch_assoc()) {
    $delivery_fees[] = $row;
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

        .btn-edit {
            background: linear-gradient(135deg, #f59e0b, #d97706);
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
            <div class="tab" data-tab="delivery_fees">Delivery Fees</div>
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
                    <button class="btn btn-primary" onclick="toggleCategoryForm('add')">
                        <i class="fas fa-plus"></i>
                        Add Category
                    </button>
                </div>
                <div id="categoryForm" style="display: none;">
                    <form method="POST" id="categoryFormElement">
                        <input type="hidden" name="category_id" id="categoryId">
                        <div class="form-group">
                            <label class="form-label">Category Name</label>
                            <input type="text" class="form-input" name="category_name" id="categoryName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-input" name="category_description" id="categoryDescription" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_category" id="categorySubmitButton" class="btn btn-primary">
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
                                        <button class="btn btn-edit" onclick="editCategory(<?php echo htmlspecialchars($category['id']); ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description']); ?>')">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </button>
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
                    <button class="btn btn-primary" onclick="toggleProductForm('add')">
                        <i class="fas fa-plus"></i>
                        Add Product
                    </button>
                </div>
                <div id="productForm" style="display: none;">
                    <form class="form" method="POST" id="productFormElement" enctype="multipart/form-data">
                        <input type="hidden" name="product_id" id="productId">
                        <input type="hidden" name="existing_image" id="existingImage">
                        <div class="form-group">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-input" name="product_name" id="productName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price ($)</label>
                            <input type="number" class="form-input" name="price" id="productPrice" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" class="form-input" name="stock_quantity" id="productStock" min="0" required>
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
                                <button type="button" class="btn btn-primary" onclick="toggleCategoryForm('add')">
                                    <i class="fas fa-plus"></i>
                                    New Category
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Miscellaneous Attribute</label>
                            <select class="form-input" name="misc_attribute" id="miscAttribute">
                                <option value="">None</option>
                                <option value="new_arrival">New Arrival</option>
                                <option value="featured">Featured</option>
                                <option value="trending">Trending</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Featured Product</label>
                            <input type="checkbox" name="featured" id="productFeatured" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-input" name="image" id="productImage" accept="image/*">
                            <img id="imagePreview" src="" alt="Image Preview" class="product-image" style="display: none; margin-top: 0.5rem;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Description</label>
                            <textarea class="form-input" name="product_description" id="productDescription" rows="5" placeholder="Enter product description"></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="add_product" id="productSubmitButton" class="btn btn-primary">
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
                                    <td>
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($product['description'], 0, 50) . '...'); ?></td>
                                    <td><?php echo htmlspecialchars($product['misc_attribute'] ?? 'None'); ?></td>
                                    <td>
                                        <button class="btn btn-edit" onclick='editProduct(<?php echo json_encode($product, JSON_HEX_QUOT | JSON_HEX_APOS); ?>)'>
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
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
                    <button class="btn btn-primary" onclick="refreshOrders()">
                        <i class="fas fa-sync-alt"></i>
                        Refresh Orders
                    </button>
                </div>
                <div class="table-container" id="orderTable">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer Email</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="orderTableBody">
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['email']); ?></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                    <td>$<?php echo number_format($order['o_total'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars(strtolower($order['status'])); ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order-details.php?order_id=<?php echo htmlspecialchars($order['id']); ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Carousel Tab -->
        <div id="carousel-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Carousel Management</h2>
                </div>
                <form class="form" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-input" name="title" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subtitle</label>
                        <input type="text" class="form-input" name="subtitle" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link</label>
                        <input type="url" class="form-input" name="link" placeholder="https://">
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
                            <i class="fas fa-plus"></i>
                            Add Image
                        </button>
                    </div>
                </form>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Subtitle</th>
                                <th>Link</th>
                                <th>Active</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($carousel_images as $image): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($image['image']); ?>" alt="<?php echo htmlspecialchars($image['title']); ?>" class="product-image">
                                    </td>
                                    <td><?php echo htmlspecialchars($image['title']); ?></td>
                                    <td><?php echo htmlspecialchars($image['subtitle']); ?></td>
                                    <td><?php echo htmlspecialchars($image['link'] ?: 'N/A'); ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="image_id" value="<?php echo htmlspecialchars($image['id']); ?>">
                                            <input type="checkbox" name="is_active" value="1" <?php echo $image['is_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                            <input type="hidden" name="toggle_carousel_image" value="1">
                                        </form>
                                    </td>
                                    <td><?php echo htmlspecialchars($image['created_at']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this carousel image?');">
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

        <!-- Delivery Fees Tab -->
        <div id="delivery_fees-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Delivery Fees Management</h2>
                    <button class="btn btn-primary" onclick="refreshDeliveryFees()">
                        <i class="fas fa-sync-alt"></i>
                        Refresh Delivery Fees
                    </button>
                </div>
                <form class="form" method="POST" id="deliveryFeeForm">
                    <input type="hidden" name="id" id="deliveryFeeId">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-input" name="name" id="deliveryFeeName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fee ($)</label>
                        <input type="number" class="form-input" name="fee" id="deliveryFeeAmount" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Minimum Order Amount ($)</label>
                        <input type="number" class="form-input" name="min_order_amount" id="deliveryFeeMinOrder" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" name="description" id="deliveryFeeDescription" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Active</label>
                        <input type="checkbox" name="is_active" id="deliveryFeeActive" value="1" checked>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_delivery_fee" id="deliveryFeeSubmitButton" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Delivery Fee
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetDeliveryFeeForm()">Cancel</button>
                    </div>
                </form>
                <div class="table-container" id="deliveryFeeTable">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Fee</th>
                                <th>Min Order</th>
                                <th>Description</th>
                                <th>Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="deliveryFeeTableBody">
                            <?php foreach ($delivery_fees as $fee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fee['id']); ?></td>
                                    <td><?php echo htmlspecialchars($fee['name']); ?></td>
                                    <td>$<?php echo number_format($fee['fee'], 2); ?></td>
                                    <td><?php echo $fee['min_order_amount'] ? '$' . number_format($fee['min_order_amount'], 2) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($fee['description'] ?: 'N/A'); ?></td>
                                    <td><?php echo $fee['is_active'] ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <button class="btn btn-edit" onclick="editDeliveryFee(<?php echo htmlspecialchars(json_encode($fee, JSON_HEX_QUOT | JSON_HEX_APOS)); ?>)">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this delivery fee?');">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($fee['id']); ?>">
                                            <button type="submit" name="delete_delivery_fee" class="btn btn-danger">
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

        <!-- Security Tab -->
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
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-input" name="confirm_password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-lock"></i>
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Tab Switching
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                tab.classList.add('active');
                document.getElementById(`${tab.dataset.tab}-tab`).classList.add('active');
            });
        });

        // Profile Dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', (event) => {
            if (!event.target.closest('.profile-dropdown')) {
                const dropdown = document.getElementById('profileDropdown');
                dropdown.classList.remove('show');
            }
        });

        // Search Products
        function searchProducts() {
            const searchInput = document.getElementById('searchInput').value.trim();
            if (searchInput.length > 2) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'admin.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        const products = JSON.parse(xhr.responseText);
                        const productTableBody = document.getElementById('productTableBody');
                        productTableBody.innerHTML = '';
                        products.forEach(product => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td><img src="${product.image}" alt="${product.name}" class="product-image"></td>
                                <td>${product.name}</td>
                                <td>$${parseFloat(product.price).toFixed(2)}</td>
                                <td>${product.stock_quantity}</td>
                                <td>${product.category}</td>
                                <td>${product.description.substring(0, 50)}...</td>
                                <td>${product.misc_attribute || 'None'}</td>
                                <td>
                                    <button class="btn btn-edit" onclick='editProduct(${JSON.stringify(product)})'>Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="product_id" value="${product.id}">
                                        <button type="submit" name="delete_product" class="btn btn-danger">Delete</button>
                                    </form>
                                </td>
                            `;
                            productTableBody.appendChild(row);
                        });
                    }
                };
                xhr.send(`action=search_products&search=${encodeURIComponent(searchInput)}`);
            }
        }

        // Dismiss Alerts
        function dismissAlert(element) {
            element.parentElement.style.opacity = '0';
            setTimeout(() => element.parentElement.remove(), 300);
        }

        // Category Form Handling
        function toggleCategoryForm(mode) {
            const form = document.getElementById('categoryForm');
            const formElement = document.getElementById('categoryFormElement');
            const submitButton = document.getElementById('categorySubmitButton');
            const categoryId = document.getElementById('categoryId');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (mode === 'add') {
                formElement.reset();
                categoryId.value = '';
                submitButton.setAttribute('name', 'add_category');
                submitButton.textContent = 'Save Category';
            }
        }

        function editCategory(id, name, description) {
            toggleCategoryForm('edit');
            document.getElementById('categoryId').value = id;
            document.getElementById('categoryName').value = name;
            document.getElementById('categoryDescription').value = description;
            document.getElementById('categorySubmitButton').setAttribute('name', 'edit_category');
            document.getElementById('categorySubmitButton').textContent = 'Update Category';
        }

        function cancelCategoryForm() {
            document.getElementById('categoryForm').style.display = 'none';
            document.getElementById('categoryFormElement').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('categorySubmitButton').setAttribute('name', 'add_category');
            document.getElementById('categorySubmitButton').textContent = 'Save Category';
        }

        // Product Form Handling
        function toggleProductForm(mode) {
            const form = document.getElementById('productForm');
            const formElement = document.getElementById('productFormElement');
            const submitButton = document.getElementById('productSubmitButton');
            const productId = document.getElementById('productId');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (mode === 'add') {
                formElement.reset();
                productId.value = '';
                document.getElementById('imagePreview').style.display = 'none';
                submitButton.setAttribute('name', 'add_product');
                submitButton.textContent = 'Save Product';
            }
        }

        function editProduct(product) {
            toggleProductForm('edit');
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('categorySelect').value = product.category_id || '';
            document.getElementById('miscAttribute').value = product.misc_attribute || '';
            document.getElementById('productFeatured').checked = product.featured == 1;
            document.getElementById('productDescription').value = product.description;
            document.getElementById('existingImage').value = product.image;
            document.getElementById('imagePreview').src = product.image;
            document.getElementById('imagePreview').style.display = 'block';
            document.getElementById('productSubmitButton').setAttribute('name', 'edit_product');
            document.getElementById('productSubmitButton').textContent = 'Update Product';
        }

        function cancelProductForm() {
            document.getElementById('productForm').style.display = 'none';
            document.getElementById('productFormElement').reset();
            document.getElementById('productId').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('productSubmitButton').setAttribute('name', 'add_product');
            document.getElementById('productSubmitButton').textContent = 'Save Product';
        }

        // Delivery Fee Form Handling
        function editDeliveryFee(fee) {
            document.getElementById('deliveryFeeId').value = fee.id;
            document.getElementById('deliveryFeeName').value = fee.name;
            document.getElementById('deliveryFeeAmount').value = fee.fee;
            document.getElementById('deliveryFeeMinOrder').value = fee.min_order_amount || '';
            document.getElementById('deliveryFeeDescription').value = fee.description || '';
            document.getElementById('deliveryFeeActive').checked = fee.is_active == 1;
            document.getElementById('deliveryFeeSubmitButton').setAttribute('name', 'edit_delivery_fee');
            document.getElementById('deliveryFeeSubmitButton').textContent = 'Update Delivery Fee';
        }

        function resetDeliveryFeeForm() {
            document.getElementById('deliveryFeeForm').reset();
            document.getElementById('deliveryFeeId').value = '';
            document.getElementById('deliveryFeeSubmitButton').setAttribute('name', 'add_delivery_fee');
            document.getElementById('deliveryFeeSubmitButton').textContent = 'Save Delivery Fee';
        }

        // Image Preview
        document.getElementById('productImage').addEventListener('change', function (event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Refresh Orders
        function refreshOrders() {
            const tableContainer = document.getElementById('orderTable');
            tableContainer.classList.add('loading');
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'admin.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const orders = JSON.parse(xhr.responseText);
                    const orderTableBody = document.getElementById('orderTableBody');
                    orderTableBody.innerHTML = '';
                    orders.forEach(order => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${order.id}</td>
                            <td>${order.email}</td>
                            <td>${order.product_name}</td>
                            <td>${order.quantity}</td>
                            <td>$${parseFloat(order.o_total).toFixed(2)}</td>
                            <td>${order.order_date}</td>
                            <td><span class="status-badge status-${order.status.toLowerCase()}">${order.status}</span></td>
                            <td>
                                <a href="order-details.php?order_id=${order.id}" class="btn btn-primary">View</a>
                            </td>
                        `;
                        orderTableBody.appendChild(row);
                    });
                    tableContainer.classList.remove('loading');
                }
            };
            xhr.send('action=fetch_orders');
        }

        // Refresh Delivery Fees
        function refreshDeliveryFees() {
            const tableContainer = document.getElementById('deliveryFeeTable');
            tableContainer.classList.add('loading');
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'admin.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const fees = JSON.parse(xhr.responseText);
                    const feeTableBody = document.getElementById('deliveryFeeTableBody');
                    feeTableBody.innerHTML = '';
                    fees.forEach(fee => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${fee.id}</td>
                            <td>${fee.name}</td>
                            <td>$${parseFloat(fee.fee).toFixed(2)}</td>
                            <td>${fee.min_order_amount ? '$' + parseFloat(fee.min_order_amount).toFixed(2) : 'N/A'}</td>
                            <td>${fee.description || 'N/A'}</td>
                            <td>${fee.is_active == 1 ? 'Yes' : 'No'}</td>
                            <td>
                                <button class="btn btn-edit" onclick='editDeliveryFee(${JSON.stringify(fee)})'>Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this delivery fee?');">
                                    <input type="hidden" name="id" value="${fee.id}">
                                    <button type="submit" name="delete_delivery_fee" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        `;
                        feeTableBody.appendChild(row);
                    });
                    tableContainer.classList.remove('loading');
                }
            };
            xhr.send('action=fetch_delivery_fees');
        }

        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($sales_data['labels']); ?>,
                datasets: [{
                    label: 'Sales ($)',
                    data: <?php echo json_encode($sales_data['totals']); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e2e8f0' },
                        ticks: { color: '#64748b' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b' }
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($category_data)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($category_data)); ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#64748b' }
                    },
                    title: { display: false }
                }
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>