<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ----- FORGOT PASSWORD HANDLING -----
$message = '';
if (isset($_POST['request_reset'])) {
    $email = sanitize($conn, $_POST['email']);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Generate token
        $token = generateResetToken();
        
        // Store token in password_resets
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $stmt->close();
        
        // Send reset email
        if (sendResetEmail($email, $token)) {
            $message = "A password reset link has been sent to your email.";
        } else {
            $message = "Failed to send reset email. Please try again later.";
        }
    } else {
        $message = "No account found with that email address.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and CSS -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Inline CSS (same as login.php/signup.php for consistency) */
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
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #2A2AFF ,#BDF3FF );
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            color: var(--text-primary);
        }

        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
        }

        .shape {
            position: absolute;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
            opacity: 0.1;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 15%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 50%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-30px) rotate(120deg); }
            66% { transform: translateY(20px) rotate(240deg); }
        }

        .navbar {
            background: linear-gradient(135deg, #2A2AFF ,#BDF3FF );
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 1rem;
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

        .main-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 120px 2rem 2rem;
            position: relative;
            z-index: 10;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 480px;
        }

        .auth-container {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            padding: 2rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 0.95rem;
            color: var(--text-primary);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .validation-message {
            font-size: 0.8rem;
            margin-top: 0.5rem;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .validation-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        .validation-message.success {
            color: var(--success);
        }

        .validation-message.error {
            color: var(--error);
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
        }

        .link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .link:hover {
            color: var(--secondary);
        }

        @media (max-width: 767px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--surface);
                flex-direction: column;
                padding: 1rem;
            }

            .nav-links.active {
                display: flex;
            }

            .mobile-menu-btn {
                display: block;
                background: none;
                border: none;
                color: var(--text-primary);
                font-size: 1.5rem;
                cursor: pointer;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 1.5rem;
            }

            .form-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background Animation -->
    <div class="bg-animation">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
    </div>

    <!-- ----- NAVIGATION ----- -->
    <nav class="navbar">
        <div class="nav-content">
            <a href="index.php" class="logo">
                <i class="fas fa-store"></i>
                Deeken
            </a>
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php">Home</a></li>
                <li><a href="signup.php" class="signup-link">Sign Up</a></li>
            </ul>
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- ----- FORGOT PASSWORD FORM ----- -->
    <div class="main-container">
        <div class="auth-wrapper">
            <div class="auth-container">
                <div class="form-section">
                    <div class="form-header">
                        <h1>Forgot Password</h1>
                        <p>Enter your email to receive a password reset link</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="validation-message <?php echo strpos($message, 'sent') !== false ? 'success' : 'error'; ?> show"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <form class="form" id="forgotPasswordForm" method="POST">
                        <div class="input-group">
                            <input type="email" class="form-input" name="email" placeholder="Email address" required>
                            <i class="fas fa-envelope"></i>
                            <div class="validation-message"></div>
                        </div>

                        <button type="submit" name="request_reset" class="btn">
                            <span>Send Reset Link</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="form-footer">
                        Remember your password? <a href="login.php" class="link">Sign in</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ----- JAVASCRIPT ----- -->
    <script>
        // App State
        const app = { isLoading: false };

        // DOM Elements
        const elements = {
            forgotPasswordForm: document.getElementById('forgotPasswordForm'),
            mobileMenuBtn: document.getElementById('mobileMenuBtn'),
            navLinks: document.getElementById('navLinks')
        };

        // Utility Functions
        const utils = {
            validateEmail: (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email),
            showValidation: (inputGroup, type, message) => {
                const validationMsg = inputGroup.querySelector('.validation-message');
                inputGroup.className = `input-group ${type}`;
                validationMsg.textContent = message;
                validationMsg.className = `validation-message ${type} show`;
            },
            hideValidation: (inputGroup) => {
                const validationMsg = inputGroup.querySelector('.validation-message');
                inputGroup.className = 'input-group';
                validationMsg.className = 'validation-message';
            }
        };

        // Form Validation
        function setupFormValidation() {
            const emailInput = elements.forgotPasswordForm.querySelector('input[type="email"]');
            const inputGroup = emailInput.parentElement;

            emailInput.addEventListener('blur', () => {
                if (emailInput.value) {
                    if (utils.validateEmail(emailInput.value)) {
                        utils.showValidation(inputGroup, 'success', 'Valid email address');
                    } else {
                        utils.showValidation(inputGroup, 'error', 'Please enter a valid email address');
                    }
                } else {
                    utils.hideValidation(inputGroup);
                }
            });
        }

        // Mobile Menu
        function setupMobileMenu() {
            elements.mobileMenuBtn.addEventListener('click', () => {
                elements.navLinks.classList.toggle('active');
                const icon = elements.mobileMenuBtn.querySelector('i');
                icon.className = elements.navLinks.classList.contains('active') ? 'fas fa-times' : 'fas fa-bars';
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.navbar')) {
                    elements.navLinks.classList.remove('active');
                    elements.mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                }
            });
        }

        // Initialize App
        function initApp() {
            setupFormValidation();
            setupMobileMenu();
            document.body.classList.add('loaded');
        }

        document.addEventListener('DOMContentLoaded', initApp);
    </script>
</body>
</html>
<?php
// ----- CLEANUP -----
$conn->close();
?>