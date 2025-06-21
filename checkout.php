<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireLogin();

$user = getCurrentUser();

// ----- HELPER FUNCTIONS FOR DELIVERY FEES -----
function getDeliveryFees($conn, $total_amount) {
    $stmt = $conn->prepare("SELECT id, name, fee, min_order_amount, description FROM delivery_fees WHERE is_active = 1 ORDER BY fee ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $fees = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_applicable'] = ($row['min_order_amount'] === null || $total_amount >= $row['min_order_amount']);
        $fees[] = $row;
    }
    $stmt->close();
    return $fees;
}

function getDeliveryFeeById($conn, $delivery_fee_id, $total_amount) {
    $stmt = $conn->prepare("SELECT id, name, fee, min_order_amount FROM delivery_fees WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $delivery_fee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['min_order_amount'] === null || $total_amount >= $row['min_order_amount']) {
            $stmt->close();
            return $row;
        }
    }
    $stmt->close();
    return null;
}

// ----- ORDER SUBMISSION -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && $user) {
    $name = sanitize($conn, $_POST['name']);
    $street_address = sanitize($conn, $_POST['address']);
    $phone = sanitize($conn, $_POST['phone']);
    $delivery_fee_id = isset($_POST['delivery_fee_id']) ? (int)$_POST['delivery_fee_id'] : 0;
    $subtotal = 0;

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Calculate subtotal and validate stock
        $stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.price, p.name, i.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id LEFT JOIN inventory i ON p.id = i.product_id WHERE c.user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $cart_items = $stmt->get_result();
        $items = [];
        while ($item = $cart_items->fetch_assoc()) {
            if ($item['stock_quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock for {$item['name']}. Available: {$item['stock_quantity']}, Requested: {$item['quantity']}");
            }
            $subtotal += $item['price'] * $item['quantity'];
            $items[] = $item;
        }
        if (empty($items)) {
            throw new Exception("Cart is empty.");
        }
        $stmt->close();

        // Validate delivery fee
        $delivery_fee_data = $delivery_fee_id ? getDeliveryFeeById($conn, $delivery_fee_id, $subtotal) : null;
        if (!$delivery_fee_data) {
            // Fallback to cheapest applicable fee
            $delivery_fees = getDeliveryFees($conn, $subtotal);
            foreach ($delivery_fees as $fee) {
                if ($fee['is_applicable']) {
                    $delivery_fee_data = $fee;
                    break;
                }
            }
            if (!$delivery_fee_data) {
                throw new Exception("No valid delivery options available for your order subtotal of $" . number_format($subtotal, 2));
            }
        }
        $delivery_fee = $delivery_fee_data['fee'];
        $delivery_fee_id = $delivery_fee_data['id'];
        $total = $subtotal + $delivery_fee;

        // Insert or update address
        $city = '';
        $state = '';
        $country = '';
        $postal_code = '';
        $is_default = 1;

        $stmt = $conn->prepare("INSERT INTO addresses (user_id, full_name, street_address, city, state, country, postal_code, phone, is_default, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE full_name = ?, street_address = ?, phone = ?, updated_at = NOW()");
        $stmt->bind_param("issssssissss", $user['id'], $name, $street_address, $city, $state, $country, $postal_code, $phone, $is_default, $name, $street_address, $phone);
        if (!$stmt->execute()) {
            throw new Exception("Failed to save address: " . $stmt->error);
        }
        $address_id = $conn->insert_id;
        $stmt->close();

        // Fetch address_id if updated
        if (!$address_id) {
            $stmt = $conn->prepare("SELECT id FROM addresses WHERE user_id = ? LIMIT 1");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $address_id = $row['id'];
            } else {
                throw new Exception("Failed to retrieve address");
            }
            $stmt->close();
        }

        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, address_id, delivery_fee_id, total, delivery_fee, status, created_at) VALUES (?, ?, ?, ?, ?, 'processing', NOW())");
        $stmt->bind_param("iiidd", $user['id'], $address_id, $delivery_fee_id, $total, $delivery_fee);
        if (!$stmt->execute()) {
            throw new Exception("Failed to create order: " . $stmt->error);
        }
        $order_id = $conn->insert_id;
        $stmt->close();

        // Insert order items and update stock
        $order_item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stock_stmt = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        foreach ($items as $item) {
            $order_item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            if (!$order_item_stmt->execute()) {
                throw new Exception("Failed to insert order item for product {$item['name']}: " . $order_item_stmt->error);
            }

            $stock_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
            if (!$stock_stmt->execute()) {
                throw new Exception("Failed to update stock for product {$item['name']}: " . $stock_stmt->error);
            }
        }
        $order_item_stmt->close();
        $stock_stmt->close();

        // Update user info
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, address = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $street_address, $phone, $user['id']);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user info: " . $stmt->error);
        }
        $stmt->close();

        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        if (!$stmt->execute()) {
            throw new Exception("Failed to clear cart: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();

        // Response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Order placed successfully', 'order_id' => $order_id]);
            exit;
        } else {
            header('Location: order_success.php?order_id=' . $order_id . '&success=' . urlencode('Order placed successfully!'));
            exit;
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to place order: " . $e->getMessage();
        error_log($error, 3, 'logs/checkout_errors.log'); // Log to file
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $error]);
            exit;
        } else {
            header('Location: checkout.php?error=' . urlencode($error));
            exit;
        }
    }
}

// Include header after order submission logic
include 'header.php';

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);

// ----- CALCULATE CURRENT SUBTOTAL AND DELIVERY FEES -----
$current_subtotal = 0;
$delivery_fees = [];
$current_delivery_fee = 0;
$current_delivery_fee_id = 0;
if ($user && $cart_count > 0) {
    $stmt = $conn->prepare("SELECT c.quantity, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $current_subtotal += $row['price'] * $row['quantity'];
    }
    $stmt->close();

    // Fetch all delivery fees
    $delivery_fees = getDeliveryFees($conn, $current_subtotal);
    // Select default fee (cheapest applicable)
    foreach ($delivery_fees as $fee) {
        if ($fee['is_applicable']) {
            $current_delivery_fee = $fee['fee'];
            $current_delivery_fee_id = $fee['id'];
            break;
        }
    }
}
$current_total = $current_subtotal + $current_delivery_fee;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Checkout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="stylesheet.css">
    <link rel="stylesheet" href="hamburger.css">
    <style>
          /* Page-specific styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(12px, 2.5vw, 14px);
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0; /* Reset browser default margin */
        }

        .checkout-container {
            max-width: min(1100px, 98vw);
            margin: clamp(1rem, 3vw, 2rem) auto;
            padding: 0 clamp(0.5rem, 2vw, 1rem);
            display: grid;
            grid-template-columns: 1fr minmax(300px, 380px);
            gap: clamp(1rem, 3vw, 2rem);
            align-items: start;
            width: 100%;
            box-sizing: border-box;
        }

        .checkout-main {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: clamp(1rem, 3vw, 2rem);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
        }

        .checkout-sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: clamp(1rem, 2.5vw, 1.5rem);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 100px;
            width: 100%;
        }

        .page-title {
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            font-weight: 700;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #2c3e50;
            background: linear-gradient(135deg, #2a2aff, #4dabf7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            margin-bottom: clamp(1rem, 3vw, 2rem);
            font-weight: 400;
        }

        .section-title {
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 600;
            margin: clamp(1rem, 2.5vw, 1.5rem) 0 clamp(0.8rem, 2vw, 1rem) 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            position: relative;
            padding-bottom: 0.6rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: linear-gradient(135deg, #2a2aff, #4dabf7);
            border-radius: 2px;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        .input-group {
            margin-bottom: clamp(0.8rem, 2vw, 1.2rem);
        }

        .input-field, select {
            width: 100%;
            padding: clamp(12px, 2.5vw, 14px) clamp(14px, 3vw, 18px);
            margin: 0.4rem 0;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: clamp(12px, 2.5vw, 14px);
            font-family: 'Poppins', sans-serif;
            font-weight: 400;
            box-sizing: border-box;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .input-field:focus, select:focus {
            outline: none;
            border-color: #2a2aff;
            box-shadow: 0 0 0 3px rgba(42, 42, 255, 0.08);
            background: rgba(255, 255, 255, 1);
            transform: translateY(-1px);
        }

        .input-field::placeholder {
            color: #adb5bd;
            font-weight: 400;
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 14px;
            padding-right: 45px;
        }

        .order-summary-title {
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 600;
            margin-bottom: clamp(0.8rem, 2vw, 1.2rem);
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .cart-items-container {
            max-height: 350px;
            overflow-y: auto;
            margin-bottom: clamp(0.8rem, 2vw, 1.2rem);
            padding-right: 6px;
        }

        .cart-items-container::-webkit-scrollbar {
            width: 5px;
        }

        .cart-items-container::-webkit-scrollbar-track {
            background: #f8f9fa;
            border-radius: 8px;
        }

        .cart-items-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #2a2aff, #4dabf7);
            border-radius: 8px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: clamp(0.6rem, 2vw, 0.8rem);
            padding: clamp(0.8rem, 2vw, 1rem);
            margin-bottom: 0.8rem;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 14px;
            transition: all 0.3s ease;
            border: 1px solid rgba(233, 236, 239, 0.6);
            width: 100%;
            box-sizing: border-box;
        }

        .cart-item:hover {
            background: rgba(248, 249, 250, 1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
        }

        .cart-item:last-child {
            margin-bottom: 0;
        }

        .cart-item-image {
            width: clamp(60px, 15vw, 70px);
            height: clamp(60px, 15vw, 70px);
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-details h3 {
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            margin: 0 0 0.4rem 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .cart-item-details p {
            font-size: clamp(12px, 2.5vw, 13px);
            margin: 0.2rem 0;
            color: #6c757d;
            font-weight: 400;
        }

        .item-price {
            font-weight: 600;
            color: #2a2aff;
            font-size: clamp(12px, 2.5vw, 14px);
        }

        .stock-info {
            font-size: clamp(11px, 2vw, 12px);
            padding: 3px 6px;
            border-radius: 5px;
            display: inline-block;
            margin-top: 0.2rem;
        }

        .stock-info.out-of-stock {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            font-weight: 600;
        }

        .stock-info:not(.out-of-stock) {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            font-weight: 500;
        }

        .order-totals {
            background: linear-gradient(135deg, rgba(42, 42, 255, 0.05), rgba(77, 171, 247, 0.05));
            padding: clamp(1rem, 2.5vw, 1.5rem);
            border-radius: 14px;
            margin: clamp(0.8rem, 2vw, 1.2rem) 0;
            border: 1px solid rgba(42, 42, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0.8rem 0;
            font-size: clamp(12px, 2.5vw, 14px);
            font-weight: 500;
            color: #495057;
        }

        .total-row .amount {
            font-weight: 600;
            color: #2c3e50;
        }

        .total-row.final {
            border-top: 2px solid rgba(42, 42, 255, 0.2);
            padding-top: 0.8rem;
            margin-top: 1.2rem;
            font-weight: 700;
            font-size: clamp(1rem, 3vw, 1.2rem);
            color: #2a2aff;
        }

        .total-row.final .amount {
            color: #2a2aff;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            padding: clamp(12px, 3vw, 16px) clamp(20px, 5vw, 28px);
            background: linear-gradient(135deg, #2a2aff, #4dabf7);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: clamp(13px, 2.5vw, 15px);
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            touch-action: manipulation;
            transition: all 0.3s ease;
            box-shadow: 0 6px 25px rgba(42, 42, 255, 0.3);
            font-family: 'Poppins', sans-serif;
            min-height: 44px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(42, 42, 255, 0.4);
            background: linear-gradient(135deg, #1e1eff, #339af0);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 4px 12px rgba(42, 42, 255, 0.2);
        }

        .empty-cart {
            text-align: center;
            padding: clamp(2rem, 5vw, 3rem) clamp(1rem, 2.5vw, 1.5rem);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
            grid-column: 1 / -1;
        }

        .empty-cart i {
            font-size: clamp(2.5rem, 8vw, 3.5rem);
            color: #dee2e6;
            margin-bottom: 1.2rem;
        }

        .empty-cart p {
            font-size: clamp(1rem, 2.5vw, 1.1rem);
            color: #6c757d;
            margin-bottom: 1.5rem;
            font-weight: 400;
        }

        .empty-cart .btn {
            max-width: min(280px, 90vw);
            margin: 0 auto;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(248, 215, 218, 0.8));
            color: #721c24;
            padding: clamp(0.6rem, 2vw, 0.8rem) clamp(1rem, 2.5vw, 1.2rem);
            border-radius: 10px;
            margin: 0.8rem 0;
            border: 1px solid rgba(220, 53, 69, 0.2);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(212, 237, 218, 0.8));
            color: #155724;
            padding: clamp(0.6rem, 2vw, 0.8rem) clamp(1rem, 2.5vw, 1.2rem);
            border-radius: 10px;
            margin: 0.8rem 0;
            border: 1px solid rgba(40, 167, 69, 0.2);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .delivery-options {
            margin: clamp(0.8rem, 2vw, 1.2rem) 0;
        }

        .delivery-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: clamp(0.8rem, 2vw, 1rem);
            margin: clamp(0.4rem, 1.5vw, 0.6rem) 0;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            width: 100%;
        }

        .delivery-option:hover {
            border-color: rgba(42, 42, 255, 0.3);
            background: rgba(255, 255, 255, 1);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .delivery-option input[type="radio"] {
            margin-right: 0.8rem;
            scale: 1.1;
            accent-color: #2a2aff;
        }

        .delivery-option.selected {
            border-color: #2a2aff;
            background: rgba(42, 42, 255, 0.05);
        }

        .delivery-option.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: rgba(248, 249, 250, 0.8);
        }

        .delivery-info {
            flex: 1;
        }

        .delivery-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.2rem;
        }

        .delivery-description {
            font-size: clamp(12px, 2.5vw, 13px);
            color: #6c757d;
            margin-bottom: 0.2rem;
        }

        .delivery-min-order {
            font-size: clamp(11px, 2vw, 12px);
            color: #dc3545;
            font-weight: 500;
        }

        .delivery-fee {
            font-weight: 700;
            color: #2a2aff;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 18px;
            height: 18px;
            margin: -9px;
            border: 2px solid #2a2aff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .footer {
            background: linear-gradient(135deg, #1a1a1a, #2A2AFF);
            color: white;
            margin-top: 4rem;
            position: relative;
            overflow: hidden;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .footer-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #BDF3FF;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: #ddd;
            text-decoration: none;
            font-size: 14px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .social-links a {
            color: white;
            font-size: 1.5rem;
        }

        .contact-info p {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 14px;
            margin-bottom: 0.5rem;
            color: #ddd;
        }

        .footer-bottom {
            background: rgba(0, 0, 0, 0.2);
            padding: 1rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-bottom-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-bottom p {
            font-size: 14px;
            color: #ddd;
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-links a {
            color: #ddd;
            text-decoration: none;
            font-size: 14px;
        }

        /* Media Queries for Responsive Design */
        @media (max-width: 1024px) {
            .checkout-container {
                grid-template-columns: 1fr minmax(250px, 320px);
                gap: clamp(0.8rem, 2vw, 1.5rem);
            }
        }

        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
                gap: clamp(0.8rem, 2vw, 1.2rem);
                margin: clamp(0.8rem, 2vw, 1.2rem) auto;
                padding: 0 clamp(0.5rem, 2vw, 0.8rem);
            }

            .checkout-main,
            .checkout-sidebar {
                padding: clamp(0.8rem, 2vw, 1.5rem);
            }

            .checkout-sidebar {
                position: static;
                order: -1;
            }

            .page-title {
                font-size: clamp(1.3rem, 4vw, 1.6rem);
            }

            .section-title {
                font-size: clamp(0.9rem, 3vw, 1.1rem);
            }

            .cart-item {
                flex-direction: row;
                gap: clamp(0.5rem, 1.5vw, 0.6rem);
                padding: clamp(0.6rem, 1.5vw, 0.8rem);
            }

            .cart-item-image {
                width: clamp(50px, 12vw, 60px);
                height: clamp(50px, 12vw, 60px);
            }

            .cart-item-details h3 {
                font-size: clamp(0.8rem, 2vw, 0.9rem);
            }

            .order-totals {
                padding: clamp(0.8rem, 2vw, 1.2rem);
            }

            .total-row.final {
                font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            }

            .total-row.final .amount {
                font-size: clamp(1rem, 2.5vw, 1.2rem);
            }
        }

        @media (max-width: 480px) {
            body {
                font-size: clamp(11px, 3vw, 12px);
            }

            .checkout-main,
            .checkout-sidebar {
                padding: clamp(0.6rem, 2vw, 1rem);
                border-radius: 14px;
            }

            .input-field, select {
                padding: clamp(10px, 2.5vw, 12px) clamp(12px, 3vw, 14px);
                font-size: clamp(11px, 2.5vw, 13px);
            }

            .btn {
                padding: clamp(10px, 2.5vw, 14px) clamp(15px, 4vw, 20px);
                font-size: clamp(12px, 2.5vw, 14px);
                min-height: 40px;
            }

            .cart-item {
                flex-direction: column;
                text-align: center;
                padding: clamp(0.6rem, 1.5vw, 0.8rem);
            }

            .cart-item-image {
                width: clamp(60px, 18vw, 70px);
                height: clamp(60px, 18vw, 70px);
                align-self: center;
            }

            .cart-item-details h3 {
                font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            }
        }
    </style>
</head>

<body>
    <main>
        <?php if (!$user): ?>
            <div class="checkout-container">
                <div class="empty-cart">
                    <i class="fas fa-user-lock"></i>
                    <p>Please log in to proceed with checkout</p>
                    <a href="login.php" class="btn">
                        <i class="fas fa-sign-in-alt"></i>
                        Login to Continue
                    </a>
                </div>
            </div>
        <?php elseif ($cart_count == 0): ?>
            <div class="checkout-container">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Your cart is empty. Add some products before checkout!</p>
                    <a href="index.php" class="btn">
                        <i class="fas fa-shopping-bag"></i>
                        Continue Shopping
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-container">
                <!-- Main Checkout Form -->
                <div class="checkout-main">
                    <h1 class="page-title">
                        <i class="fas fa-credit-card"></i>
                        Checkout
                    </h1>
                    <p class="page-subtitle">Complete your order details below</p>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= htmlspecialchars($_GET['error']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="checkoutForm">
                        <input type="hidden" name="place_order" value="1">
                        <h2 class="section-title">
                            <i class="fas fa-user"></i>
                            Contact Information
                        </h2>
                        
                        <div class="input-group">
                            <input type="text" 
                                   name="name" 
                                   class="input-field" 
                                   placeholder="Full Name" 
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" 
                                   required>
                        </div>

                        <div class="input-group">
                            <input type="tel" 
                                   name="phone" 
                                   class="input-field" 
                                   placeholder="Phone Number" 
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                   required>
                        </div>

                        <h2 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Delivery Address
                        </h2>
                        
                        <div class="input-group">
                            <input type="text" 
                                   name="address" 
                                   class="input-field" 
                                   placeholder="Street Address" 
                                   value="<?= htmlspecialchars($user['address'] ?? '') ?>" 
                                   required>
                        </div>

                        <h2 class="section-title">
                            <i class="fas fa-truck"></i>
                            Delivery Options
                        </h2>
                        
                        <div class="delivery-options">
                            <?php foreach ($delivery_fees as $index => $fee): ?>
                                <label class="delivery-option <?= !$fee['is_applicable'] ? 'disabled' : '' ?> <?= $index === 0 && $fee['is_applicable'] ? 'selected' : '' ?>">
                                    <input type="radio" 
                                           name="delivery_fee_id" 
                                           value="<?= $fee['id'] ?>" 
                                           <?= $index === 0 && $fee['is_applicable'] ? 'checked' : '' ?>
                                           <?= !$fee['is_applicable'] ? 'disabled' : '' ?>
                                           onchange="updateDeliveryFee(<?= $fee['fee'] ?>, <?= $fee['id'] ?>)">
                                    <div class="delivery-info">
                                        <div class="delivery-name"><?= htmlspecialchars($fee['name']) ?></div>
                                        <?php if ($fee['description']): ?>
                                            <div class="delivery-description"><?= htmlspecialchars($fee['description']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!$fee['is_applicable']): ?>
                                            <div class="delivery-min-order">
                                                Minimum order: $<?= number_format($fee['min_order_amount'], 2) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="delivery-fee">$<?= number_format($fee['fee'], 2) ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" name="place_order" class="btn" id="placeOrderBtn">
                            <i class="fas fa-shopping-bag"></i>
                            Place Order
                        </button>
                    </form>
                </div>

                <!-- Order Summary Sidebar -->
                <div class="checkout-sidebar">
                    <h2 class="order-summary-title">
                        <i class="fas fa-receipt"></i>
                        Order Summary
                    </h2>
                    
                    <div class="cart-items-container">
                        <?php
                        if ($user && $cart_count > 0) {
                            $stmt = $conn->prepare("
                                SELECT c.product_id, c.quantity, p.name, p.price, p.image, 
                                       COALESCE(i.stock_quantity, 0) as stock_quantity
                                FROM cart c 
                                JOIN products p ON c.product_id = p.id 
                                LEFT JOIN inventory i ON p.id = i.product_id 
                                WHERE c.user_id = ?
                            ");
                            $stmt->bind_param("i", $user['id']);
                            $stmt->execute();
                            $cart_items = $stmt->get_result();
                            
                            while ($item = $cart_items->fetch_assoc()):
                                $item_total = $item['price'] * $item['quantity'];
                                $is_out_of_stock = $item['stock_quantity'] < $item['quantity'];
                        ?>
                            <div class="cart-item">
                                <img src="<?= htmlspecialchars($item['image'] ?: 'placeholder.jpg') ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>" 
                                     class="cart-item-image">
                                <div class="cart-item-details">
                                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                                    <p>Quantity: <?= $item['quantity'] ?></p>
                                    <p class="item-price">$<?= number_format($item_total, 2) ?></p>
                                    <?php if ($is_out_of_stock): ?>
                                        <span class="stock-info out-of-stock">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Insufficient stock (<?= $item['stock_quantity'] ?> available)
                                        </span>
                                    <?php else: ?>
                                        <span class="stock-info">
                                            <i class="fas fa-check-circle"></i>
                                            In stock
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                            $stmt->close();
                        }
                        ?>
                    </div>

                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span class="amount" id="subtotalAmount">$<?= number_format($current_subtotal, 2) ?></span>
                        </div>
                        <div class="total-row">
                            <span>Delivery Fee:</span>
                            <span class="amount" id="deliveryAmount">$<?= number_format($current_delivery_fee, 2) ?></span>
                        </div>
                        <div class="total-row final">
                            <span>Total:</span>
                            <span class="amount" id="totalAmount">$<?= number_format($current_total, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Deeken</h3>
                <ul>
                    <li><a href="#">Our Story</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Returns</a></li>
                    <li><a href="#">FAQs</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <div class="contact-info">
                    <p><i class="fas fa-envelope"></i> support@deeken.com</p>
                    <p><i class="fas fa-phone"></i> +1-800-123-4567</p>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p>© 2025 Deeken. All rights reserved.</p>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Delivery fee update functionality
        function updateDeliveryFee(fee, feeId) {
            const subtotal = <?= $current_subtotal ?>;
            const newTotal = subtotal + fee;
            
            document.getElementById('deliveryAmount').textContent = `$${fee.toFixed(2)}`;
            document.getElementById('totalAmount').textContent = `$${newTotal.toFixed(2)}`;
            
            // Update selected styling
            document.querySelectorAll('.delivery-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.target.closest('.delivery-option').classList.add('selected');
        }

        // Form submission with loading state
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('placeOrderBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;
        });
    </script>
</body>
</html>