 // Close banner functionality
        document.querySelector('.close-btn').addEventListener('click', function() {
            document.querySelector('.top-banner').style.display = 'none';
        });

        // Newsletter subscription
        function subscribeNewsletter() {
            const email = document.getElementById('emailInput').value;
            if (email) {
                console.log('Newsletter subscription for:', email);
                alert('Thank you for subscribing to our newsletter!');
                document.getElementById('emailInput').value = '';
            } else {
                alert('Please enter a valid email address.');
            }
        }

        // Add smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add hover effects for product cards
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.boxShadow = 'none';
            });
        });

        // Add click handlers for View All buttons
        document.querySelectorAll('.view-all-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('View All clicked');
                // In a real app, this would navigate to the full product listing
                alert('Redirecting to full product catalog...');
            });
        });

        // Add click handlers for CTA buttons
        document.querySelectorAll('.cta-button').forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Shop Now clicked');
                // In a real app, this would navigate to the shop page
                alert('Redirecting to shop...');
            });
        });

        // Add mobile menu toggle (hamburger menu)
        const mobileMenuBtn = document.createElement('button');
        mobileMenuBtn.innerHTML = '☰';
        mobileMenuBtn.className = 'mobile-menu-btn';
        mobileMenuBtn.style.cssText = `
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
        `;
        
        // Add mobile menu styles
        const mobileMenuStyles = `
            @media (max-width: 768px) {
                .mobile-menu-btn {
                    display: block !important;
                }
                
                .nav-links.mobile-active {
                    display: flex !important;
                    flex-direction: column;
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid #eee;
                    padding: 20px;
                    gap: 20px;
                    z-index: 1000;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = mobileMenuStyles;
        document.head.appendChild(styleSheet);
        
        // Insert mobile menu button
        const navbar = document.querySelector('.navbar');
        const navIcons = document.querySelector('.nav-icons');
        navbar.insertBefore(mobileMenuBtn, navIcons);
        
        // Mobile menu toggle functionality
        mobileMenuBtn.addEventListener('click', function() {
            const navLinks = document.querySelector('.nav-links');
            navLinks.classList.toggle('mobile-active');
        });

        // Add loading animation for images (simulate image loading)
        document.querySelectorAll('.product-image, .hero-couple').forEach(img => {
            img.style.background = 'linear-gradient(45deg, #f0f0f0 25%, transparent 25%), linear-gradient(-45deg, #f0f0f0 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f0f0f0 75%), linear-gradient(-45deg, transparent 75%, #f0f0f0 75%)';
            img.style.backgroundSize = '20px 20px';
            img.style.backgroundPosition = '0 0, 0 10px, 10px -10px, -10px 0px';
            img.style.animation = 'loading 1s linear infinite';
        });

        // Add loading animation keyframes
        const loadingAnimation = `
            @keyframes loading {
                0% { background-position: 0 0, 0 10px, 10px -10px, -10px 0px; }
                100% { background-position: 20px 20px, 20px 30px, 30px 10px, 10px 20px; }
            }
        `;
        
        const animationSheet = document.createElement('style');
        animationSheet.textContent = loadingAnimation;
        document.head.appendChild(animationSheet);

        // Add intersection observer for scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.product-card, .testimonial-card, .style-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Add form validation for newsletter
        document.getElementById('emailInput').addEventListener('input', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#ff3333';
            } else {
                this.style.borderColor = '#ddd';
            }
        });

        // Add cart counter simulation
        let cartCount = 0;
        const cartBtn = document.querySelector('.nav-icons button');
        
        function updateCartCount() {
            cartBtn.innerHTML = `🛒 ${cartCount > 0 ? cartCount : ''}`;
        }

        // Simulate adding items to cart when clicking product cards
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function() {
                cartCount++;
                updateCartCount();
                
                // Show brief feedback
                const feedback = document.createElement('div');
                feedback.textContent = 'Added to cart!';
                feedback.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #01ab56;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    z-index: 1000;
                    animation: slideIn 0.3s ease;
                `;
                
                document.body.appendChild(feedback);
                
                setTimeout(() => {
                    feedback.remove();
                }, 2000);
            });
        });
        cript>
        function closeBanner() {
            const banner = document.querySelector('.top-banner');
            banner.style.animation = 'slideUp 0.5s ease-out forwards';
            
            // Add slideUp animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideUp {
                    from { transform: translateY(0); }
                    to { transform: translateY(-100%); }
                }
            `;
            document.head.appendChild(style);
            
            setTimeout(() => {
                banner.style.display = 'none';
            }, 500);
        }

        // Add slide-in animation
        const slideInAnimation = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        
        const slideInSheet = document.createElement('style');
        slideInSheet.textContent = slideInAnimation;
        document.head.appendChild(slideInSheet);

        // Initialize cart count display
        updateCartCount();

        console.log('SHOP.CO website loaded successfully!');