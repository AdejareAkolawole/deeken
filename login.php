<?php
require_once 'config.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Login handling
$error = '';
if (isset($_POST['login'])) {
    $email = sanitize($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $user = authenticateUser($conn, $email, $password);
    if ($user) {
        $_SESSION['user_id'] = $user['id']; // Set for orders.php
        $_SESSION['user'] = $user;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deeken - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
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
            background: linear-gradient(135deg, #2A2AFF, #BDF3FF);
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
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-30px) rotate(120deg); }
            66% { transform: translateY(15px) rotate(240deg); }
        }

        .navbar {
            background: linear-gradient(135deg, #BDF3FF, #2A2AFF);
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
            margin: 0 auto;
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
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
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
            position: relative;
        }

        .nav-links a:hover {
            color: var(--text-primary);
            background: var(--glass);
        }

        .signup-link {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
            color: white !important;
            font-weight: 600;
        }

        .signup-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .mobile-menu-btn:hover {
            background: var(--glass);
        }

        .main-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 10;
            padding-top: 120px;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 480px;
            position: relative;
        }

        .auth-container {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .form-section {
            padding: 3rem;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .social-login {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .social-btn {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--glass);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            font-size: 1.2rem;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            padding: 0 1rem;
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
            z-index: 2;
            transition: color 0.3s ease;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1rem;
            cursor: pointer;
            z-index: 3;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
        }

        .password-toggle:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .input-group.password-field .form-input {
            padding-right: 3rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            background: rgba(255, 255, 255, 0.08);
        }

        .form-input:focus + i {
            color: var(--primary);
        }

        .form-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.8;
        }

        .input-group.success .form-input {
            border-color: var(--success);
        }

        .input-group.error .form-input {
            border-color: var(--error);
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

        .btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .link:hover {
            color: var(--secondary);
        }

        .forgot-password {
            text-align: center;
            margin: 1rem 0;
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .mode-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            background: var(--primary);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .mode-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }

        @media (max-width: 767px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--surface);
                backdrop-filter: blur(20px);
                border-top: 1px solid var(--border);
                flex-direction: column;
                padding: 1rem;
                gap: 0;
            }

            .nav-links.active {
                display: flex;
            }

            .nav-links a {
                padding: 1rem;
                border-radius: 8px;
                text-align: center;
            }

            .mobile-menu-btn {
                display: block;
            }

            .mode-toggle {
                bottom: 1rem;
                right: 1rem;
                width: 48px;
                height: 48px;
                font-size: 1rem;
            }

            .form-header h1 {
                font-size: 2rem;
            }

            .auth-container {
                margin: 1rem;
            }

            .form-section {
                padding: 2rem;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 1rem;
                padding-top: 100px;
            }

            .form-section {
                padding: 1.5rem;
            }

            .social-login {
                gap: 0.8rem;
            }

            .social-btn {
                width: 44px;
                height: 44px;
            }

            .form-input {
                padding: 0.9rem 0.9rem 0.9rem 2.8rem;
                font-size: 0.9rem;
            }

            .input-group.password-field .form-input {
                padding-right: 2.8rem;
            }

            .btn {
                padding: 0.9rem 1.5rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        .btn:focus-visible,
        .social-btn:focus-visible,
        .link:focus-visible,
        .password-toggle:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .form-input:focus-visible {
            outline: none;
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
    </div>

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

    <div class="main-container">
        <div class="auth-wrapper">
            <div class="auth-container">
                <div class="form-section">
                    <div class="form-header">
                        <h1>Welcome Back</h1>
                        <p>Sign in to continue to your account</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="validation-message error show"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="divider">
                        <span>Continue with email</span>
                    </div>

                    <form class="form" id="loginForm" method="POST">
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" class="form-input" name="email" placeholder="Email address" required>
                            <div class="validation-message"></div>
                        </div>

                        <div class="input-group password-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" class="form-input" name="password" id="passwordInput" placeholder="Password" required>
                            <button type="button" class="password-toggle" data-target="passwordInput" title="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="validation-message"></div>
                        </div>

                        <div class="forgot-password">
                            <a href="forgot_password.php" class="link">Forgot your password?</a>
                        </div>

                        <button type="submit" name="login" class="btn">
                            <span class="btn-text">Sign In</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="form-footer">
                        Don't have an account? 
                        <a href="signup.php" class="link">Create one</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button class="mode-toggle" id="modeToggle" title="Toggle theme">
        <i class="fas fa-palette"></i>
    </button>

    <script>
        const app = {
            isLoading: false
        };

        const elements = {
            loginForm: document.getElementById('loginForm'),
            mobileMenuBtn: document.getElementById('mobileMenuBtn'),
            navLinks: document.getElementById('navLinks'),
            modeToggle: document.getElementById('modeToggle'),
            passwordToggles: document.querySelectorAll('.password-toggle')
        };

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

        function setupFormValidation() {
            document.querySelectorAll('input[type="email"]').forEach(input => {
                const inputGroup = input.parentElement;
                
                input.addEventListener('blur', () => {
                    if (input.value) {
                        if (utils.validateEmail(input.value)) {
                            utils.showValidation(inputGroup, 'success', 'Valid email address');
                        } else {
                            utils.showValidation(inputGroup, 'error', 'Please enter a valid email address');
                        }
                    } else {
                        utils.hideValidation(inputGroup);
                    }
                });
            });
        }

        function setupPasswordToggle() {
            elements.passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', () => {
                    const targetId = toggle.dataset.target;
                    const input = document.getElementById(targetId);
                    const icon = toggle.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'fas fa-eye-slash';
                        toggle.title = 'Hide password';
                    } else {
                        input.type = 'password';
                        icon.className = 'fas fa-eye';
                        toggle.title = 'Show password';
                    }
                });
            });
        }

        function setupMobileMenu() {
            elements.mobileMenuBtn.addEventListener('click', () => {
                elements.navLinks.classList.toggle('active');
                const icon = elements.mobileMenuBtn.querySelector('i');
                icon.className = elements.navLinks.classList.contains('active') 
                    ? 'fas fa-times' 
                    : 'fas fa-bars';
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.navbar')) {
                    elements.navLinks.classList.remove('active');
                    elements.mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                }
            });
        }

        function setupThemeToggle() {
            let themeIndex = 0;
            const themes = [
                {
                    name: 'Dark Blue',
                    primary: '#6366f1',
                    secondary: '#06b6d4',
                    background: '#0f172a'
                },
                {
                    name: 'Purple',
                    primary: '#8b5cf6',
                    secondary: '#ec4899',
                    background: '#1e1b4b'
                },
                {
                    name: 'Green',
                    primary: '#10b981',
                    secondary: '#06b6d4',
                    background: '#064e3b'
                },
                {
                    name: 'Orange',
                    primary: '#f59e0b',
                    secondary: '#ef4444',
                    background: '#7c2d12'
                }
            ];

            elements.modeToggle.addEventListener('click', () => {
                themeIndex = (themeIndex + 1) % themes.length;
                const theme = themes[themeIndex];
                
                document.documentElement.style.setProperty('--primary', theme.primary);
                document.documentElement.style.setProperty('--secondary', theme.secondary);
                document.documentElement.style.setProperty('--background', theme.background);
                
                showNotification(`Switched to ${theme.name} theme`, 'info');
            });
        }

        function showNotification(message, type = 'info') {
            document.querySelectorAll('.notification').forEach(n => n.remove());

            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas ${getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                    <button class="notification-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            Object.assign(notification.style, {
                position: 'fixed',
                top: '2rem',
                right: '2rem',
                background: type === 'error' ? '#ef4444' : type === 'success' ? '#10b981' : '#6366f1',
                color: 'white',
                padding: '1rem 1.5rem',
                borderRadius: '12px',
                boxShadow: '0 10px 25px rgba(0, 0, 0, 0.2)',
                zIndex: '10000',
                transform: 'translateX(100%)',
                transition: 'transform 0.3s ease',
                maxWidth: '300px',
                wordWrap: 'break-word'
            });

            notification.querySelector('.notification-content').style.cssText = `
                display: flex;
                align-items: center;
                gap: 0.75rem;
            `;

            notification.querySelector('.notification-close').style.cssText = `
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                padding: 0.25rem;
                margin-left: auto;
            `;

            document.body.appendChild(notification);

            requestAnimationFrame(() => {
                notification.style.transform = 'translateX(0)';
            });

            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 4000);

            notification.querySelector('.notification-close').addEventListener('click', () => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            });
        }

        function getNotificationIcon(type) {
            switch (type) {
                case 'success': return 'fa-check-circle';
                case 'error': return 'fa-exclamation-circle';
                case 'warning': return 'fa-exclamation-triangle';
                default: return 'fa-info-circle';
            }
        }

        function initApp() {
            setupFormValidation();
            setupPasswordToggle();
            setupMobileMenu();
            setupThemeToggle();
            
            document.body.classList.add('loaded');
        }

        document.addEventListener('DOMContentLoaded', initApp);
    </script>
</body>
</html>