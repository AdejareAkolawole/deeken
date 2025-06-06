<?php
// ----- INITIALIZATION -----
include 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ----- RESET PASSWORD HANDLING -----
$error = '';
$success = '';
$email = isset($_GET['email']) ? sanitize($conn, $_GET['email']) : '';
$token = isset($_GET['token']) ? sanitize($conn, $_GET['token']) : '';

if (isset($_POST['reset_password']) && $email && $token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate passwords
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Verify token
        $stmt = $conn->prepare("SELECT id, created_at FROM password_resets WHERE email = ? AND token = ?");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $reset = $result->fetch_assoc();

        if ($reset) {
            // Check if token is within 1 hour
            $created_at = strtotime($reset['created_at']);
            if (time() - $created_at > 3600) {
                $error = "This reset link has expired.";
            } else {
                // Update password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->bind_param("ss", $hashed_password, $email);
                if ($stmt->execute()) {
                    // Delete used token
                    $conn->query("DELETE FROM password_resets WHERE email = '$email'");
                    $success = "Password reset successfully. <a href='login.php' class='link'>Sign in</a>";
                } else {
                    $error = "Failed to reset password. Please try again.";
                }
            }
        } else {
            $error = "Invalid or expired reset link.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta and CSS -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Reset Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Inline CSS (consistent with login.php, forgot_password.php) */
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

        .shape:nth-child(1) { width: 80px; height: 80px; top: 20%; left: 10%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 120px; height: 120px; top: 60%; right: 15%; animation-delay: 2s; }
        .shape:nth-child(3) { width: 60px; height: 60px; bottom: 20%; left: 50%; animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-30px) rotate(120deg); }
            66% { transform: translateY(20px) rotate(240deg); }
        }

        .navbar {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #BDF3FF #2A2AFF);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--text-primary);
            background: var(--glass);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
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
            padding: 3rem;
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
            transition: color 0.3s ease;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-input:focus + i {
            color: var(--primary);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
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

        .password-strength {
            margin-top: 0.5rem;
            display: none;
        }

        .password-strength.show {
            display: block;
        }

        .strength-bar {
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-text {
            font-size: 0.8rem;
            color: var(--text-secondary);
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
            }

            .auth-container {
                padding: 2rem;
            }

            .form-header h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 100px 1rem 1rem;
            }

            .auth-container {
                padding: 1.5rem;
            }

            .form-input {
                padding: 0.9rem 0.9rem 0.9rem 2.8rem;
            }

            .btn {
                padding: 0.9rem 1.5rem;
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

    <!-- ----- RESET PASSWORD FORM ----- -->
    <div class="main-container">
        <div class="auth-wrapper">
            <div class="auth-container">
                <div class="form-section">
                    <div class="form-header">
                        <h1>Reset Password</h1>
                        <p>Enter your new password</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="validation-message error show"><?php echo htmlspecialchars($error); ?></div>
                    <?php elseif ($success): ?>
                        <div class="validation-message success show"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <form class="form" id="resetPasswordForm" method="POST">
                        <div class="input-group">
                            <input type="password" class="form-input" name="password" placeholder="New password" required>
                            <i class="fas fa-lock"></i>
                            <div class="validation-message"></div>
                            <div class="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-fill"></div>
                                </div>
                                <div class="strength-text">Password strength: <span></span></div>
                            </div>
                        </div>

                        <div class="input-group">
                            <input type="password" class="form-input" name="confirm_password" placeholder="Confirm password" required>
                            <i class="fas fa-lock"></i>
                            <div class="validation-message"></div>
                        </div>

                        <button type="submit" name="reset_password" class="btn">
                            <span>Reset Password</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="form-footer">
                        <a href="login.php" class="link">Back to Sign In</a>
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
            resetPasswordForm: document.getElementById('resetPasswordForm'),
            mobileMenuBtn: document.getElementById('mobileMenuBtn'),
            navLinks: document.getElementById('navLinks')
        };

        // Utility Functions
        const utils = {
            validatePassword: (password) => {
                const checks = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /\d/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };
                const score = Object.values(checks).filter(Boolean).length;
                return { score, checks };
            },
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
            },
            debounce: (func, wait) => {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        };

        // Form Validation
        function setupFormValidation() {
            if (!elements.resetPasswordForm) return;

            const passwordInput = elements.resetPasswordForm.querySelector('input[name="password"]');
            const confirmPasswordInput = elements.resetPasswordForm.querySelector('input[name="confirm_password"]');

            // Password strength
            passwordInput.addEventListener('input', utils.debounce(() => {
                updatePasswordStrength(passwordInput.value);
            }, 300));

            passwordInput.addEventListener('blur', () => {
                const inputGroup = passwordInput.parentElement;
                const { score } = utils.validatePassword(passwordInput.value);
                if (passwordInput.value) {
                    if (score >= 3) {
                        utils.showValidation(inputGroup, 'success', 'Strong password');
                    } else {
                        utils.showValidation(inputGroup, 'error', 'Password must be at least 8 characters with uppercase, lowercase, number, and special character');
                    }
                } else {
                    utils.hideValidation(inputGroup);
                }
            });

            // Confirm password
            confirmPasswordInput.addEventListener('blur', () => {
                const inputGroup = confirmPasswordInput.parentElement;
                if (confirmPasswordInput.value) {
                    if (confirmPasswordInput.value === passwordInput.value) {
                        utils.showValidation(inputGroup, 'success', 'Passwords match');
                    } else {
                        utils.showValidation(inputGroup, 'error', 'Passwords do not match');
                    }
                } else {
                    utils.hideValidation(inputGroup);
                }
            });
        }

        // Password Strength Indicator
        function updatePasswordStrength(password) {
            const strengthIndicator = document.querySelector('.password-strength');
            const strengthFill = document.querySelector('.strength-fill');
            const strengthText = document.querySelector('.strength-text span');

            if (password.length === 0) {
                strengthIndicator.classList.remove('show');
                return;
            }

            strengthIndicator.classList.add('show');

            const { score } = utils.validatePassword(password);
            const percentage = (score / 5) * 100;

            strengthFill.style.width = `${percentage}%`;

            if (score <= 1) {
                strengthFill.style.background = '#ef4444';
                strengthText.textContent = 'Weak';
            } else if (score <= 2) {
                strengthFill.style.background = '#f59e0b';
                strengthText.textContent = 'Fair';
            } else if (score <= 3) {
                strengthFill.style.background = '#06b6d4';
                strengthText.textContent = 'Good';
            } else {
                strengthFill.style.background = '#10b981';
                strengthText.textContent = 'Strong';
            }
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