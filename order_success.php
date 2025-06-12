<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireLogin();

$user = getCurrentUser();

// Check if the user is coming from checkout with a success message
$success_message = isset($_GET['success']) ? urldecode($_GET['success']) : 'Order placed successfully!';

// ----- CART COUNT -----
$cart_count = getCartCount($conn, $user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Fallback meta refresh for redirect -->
    <meta http-equiv="refresh" content="7;url=orders.php?success=<?php echo urlencode($success_message); ?>">
    <title>Order Successful - Deeken</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="stylesheet.css">
    <link rel="stylesheet" href="hamburger.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            animation: fadeIn 0.5s ease-out forwards;
        }

        .modal {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            max-width: 450px;
            width: 90%;
            position: relative;
            transform: scale(0.7) translateY(50px);
            animation: modalSlideUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        .checkmark-container {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: checkmarkPulse 0.8s ease-out 0.3s;
        }

        .checkmark {
            width: 40px;
            height: 40px;
            position: relative;
        }

        .checkmark::after {
            content: '';
            position: absolute;
            left: 12px;
            top: 18px;
            width: 8px;
            height: 16px;
            border: solid white;
            border-width: 0 3px 3px 0;
            transform: rotate(45deg);
            animation: checkmarkDraw 0.5s ease-out 0.8s forwards;
            opacity: 0;
        }

        .success-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            animation: textFadeIn 0.6s ease-out 1s forwards;
            opacity: 0;
        }

        .success-message {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
            animation: textFadeIn 0.6s ease-out 1.2s forwards;
            opacity: 0;
        }

        .payment-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #4CAF50;
            animation: textFadeIn 0.6s ease-out 1.4s forwards;
            opacity: 0;
        }

        .payment-info h4 {
            color: #333;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .payment-info p {
            color: #666;
            font-size: 14px;
        }

        .redirect-info {
            font-size: 14px;
            color: #888;
            margin-bottom: 15px;
            animation: textFadeIn 0.6s ease-out 1.6s forwards;
            opacity: 0;
        }

        .progress-bar {
            width: 100%;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            border-radius: 2px;
            width: 0%;
            animation: progressFill 5s linear 2s forwards;
        }

        .floating-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(76, 175, 80, 0.3);
            border-radius: 50%;
            animation: float 3s infinite ease-in-out;
        }

        .particle:nth-child(1) { width: 10px; height: 10px; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 15px; height: 15px; left: 20%; animation-delay: 0.5s; }
        .particle:nth-child(3) { width: 8px; height: 8px; left: 80%; animation-delay: 1s; }
        .particle:nth-child(4) { width: 12px; height: 12px; left: 90%; animation-delay: 1.5s; }

        .continue-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 12px 24px;
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-top: 15px;
        }

        .continue-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(42, 42, 255, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes modalSlideUp {
            from {
                transform: scale(0.7) translateY(50px);
                opacity: 0;
            }
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        @keyframes checkmarkPulse {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @keyframes checkmarkDraw {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes textFadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        @keyframes progressFill {
            from { width: 0%; }
            to { width: 100%; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(120deg); }
            66% { transform: translateY(-10px) rotate(240deg); }
        }

        @media (max-width: 480px) {
            .modal {
                padding: 30px 20px;
                margin: 20px;
            }
            
            .success-title {
                font-size: 24px;
            }
            
            .checkmark-container {
                width: 70px;
                height: 70px;
            }
        }

        /* Navbar styles */
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .navbar.hidden {
            transform: translateY(-100%);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2A2aff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
            color: #1a1aff;
        }

        .logo i {
            font-size: 1.6rem;
            color: #2a2aff;
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
            color: #2a2aff;
        }

        .cart-count {
            background: #ff6b35;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            margin-left: -5px;
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
            border-color: #2a2aff;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2a2aff, #bdf3ff);
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
            color: #2a2aff;
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
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <a href="index.php" class="logo"><i class="fas fa-store"></i> Deeken</a>
            <div class="nav-right">
                <a href="cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-text">Cart</span>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>    
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
    </header>

    <div class="modal-overlay">
        <div class="modal">
            <div class="floating-particles">
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
            </div>
            
            <div class="checkmark-container">
                <div class="checkmark"></div>
            </div>
            
            <h2 class="success-title">Order Received!</h2>
            
            <p class="success-message">
                <?php echo htmlspecialchars($success_message); ?> We have successfully received your request and it's being processed.
            </p>
            
            <div class="payment-info">
                <h4>Payment Information</h4>
                <p>Payment shall be made by or on delivery. Our delivery team will contact you shortly with further details.</p>
            </div>
            
            <p class="redirect-info">
                Redirecting you to your orders page...
            </p>
            
            <a href="orders.php?success=<?php echo urlencode($success_message); ?>" class="continue-btn">
                <i class="fas fa-arrow-right"></i> Continue to Orders
            </a>
            
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
    </div>

    <footer>
        <p><i class="fas fa-copyright"></i> 2025 Deeken. All rights reserved.</p>
    </footer>

    <script src="utils.js"></script>
    <script src="hamburger.js"></script>
    <script>
        // Redirect to orders page
        function redirectToOrders() {
            console.log('Initiating redirect to orders.php');
            const url = 'orders.php?success=<?php echo urlencode($success_message); ?>';
            window.location.href = url;
        }

        // Auto redirect after 7 seconds (2 seconds delay + 5 seconds progress bar)
        console.log('Setting up redirect timeout');
        setTimeout(redirectToOrders, 7000);

        // Add sparkle effect on checkmark
        document.addEventListener('DOMContentLoaded', function() {
            try {
                console.log('Adding sparkle effect');
                setTimeout(function() {
                    const checkmarkContainer = document.querySelector('.checkmark-container');
                    if (!checkmarkContainer) {
                        console.error('Checkmark container not found');
                        return;
                    }
                    
                    for (let i = 0; i < 6; i++) {
                        const sparkle = document.createElement('div');
                        sparkle.style.cssText = `
                            position: absolute;
                            width: 4px;
                            height: 4px;
                            background: #FFD700;
                            border-radius: 50%;
                            pointer-events: none;
                            animation: sparkle 1s ease-out forwards;
                        `;
                        
                        const angle = (i * 60) * Math.PI / 180;
                        const distance = 50;
                        const x = Math.cos(angle) * distance;
                        const y = Math.sin(angle) * distance;
                        
                        sparkle.style.left = `calc(50% + ${x}px)`;
                        sparkle.style.top = `calc(50% + ${y}px)`;
                        
                        checkmarkContainer.appendChild(sparkle);
                        
                        setTimeout(() => sparkle.remove(), 1000);
                    }
                }, 1200);
            } catch (error) {
                console.error('Error in sparkle effect:', error);
            }
        });

        // Add sparkle animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes sparkle {
                0% {
                    opacity: 1;
                    transform: scale(0) rotate(0deg);
                }
                50% {
                    opacity: 1;
                    transform: scale(1) rotate(180deg);
                }
                100% {
                    opacity: 0;
                    transform: scale(0) rotate(360deg);
                }
            }
        `;
        document.head.appendChild(style);

        // Toggle profile dropdown
        function toggleProfileDropdown() {
            try {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) {
                    dropdown.classList.toggle('show');
                }
            } catch (error) {
                console.error('Error in toggleProfileDropdown:', error);
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            try {
                const profileDropdown = document.querySelector('.profile-dropdown');
                const dropdown = document.getElementById('profileDropdown');
                if (profileDropdown && dropdown && !profileDropdown.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            } catch (error) {
                console.error('Error in dropdown click handler:', error);
            }
        });

        // Navbar scroll behavior
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');

        window.addEventListener('scroll', function() {
            try {
                let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                if (currentScroll > lastScrollTop) {
                    navbar.classList.add('hidden');
                } else {
                    navbar.classList.remove('hidden');
                }
                if (currentScroll <= 0) {
                    navbar.classList.remove('hidden');
                }
                lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
            } catch (error) {
                console.error('Error in scroll handler:', error);
            }
        });
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>