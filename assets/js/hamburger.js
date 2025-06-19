// Hamburger Menu JavaScript - hamburger.js
// E-commerce Platform Mobile Navigation

class HamburgerMenu {
    constructor() {
        this.hamburger = document.getElementById('hamburger');
        this.mobileNav = document.getElementById('mobileNav');
        this.mobileNavOverlay = document.getElementById('mobileNavOverlay');
        this.mobileNavClose = document.getElementById('mobileNavClose');
        this.mobileNavLinks = document.querySelectorAll('.mobile-nav-links a');
        
        this.isOpen = false;
        this.init();
    }

    init() {
        // Hamburger click event
        this.hamburger.addEventListener('click', () => this.toggleMenu());
        
        // Close button click event
        this.mobileNavClose.addEventListener('click', () => this.closeMenu());
        
        // Overlay click event
        this.mobileNavOverlay.addEventListener('click', () => this.closeMenu());
        
        // Navigation link click events (auto-close menu when link is clicked)
        this.mobileNavLinks.forEach(link => {
            link.addEventListener('click', () => this.closeMenu());
        });
        
        // ESC key to close menu
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.closeMenu();
            }
        });
        
        // Prevent body scroll when menu is open
        this.preventBodyScroll();
    }

    toggleMenu() {
        if (this.isOpen) {
            this.closeMenu();
        } else {
            this.openMenu();
        }
    }

    openMenu() {
        this.isOpen = true;
        this.hamburger.classList.add('active');
        this.mobileNav.classList.add('active');
        this.mobileNavOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus management for accessibility
        this.mobileNavClose.focus();
        
        // Add ARIA attributes
        this.hamburger.setAttribute('aria-expanded', 'true');
        this.mobileNav.setAttribute('aria-hidden', 'false');
    }

    closeMenu() {
        this.isOpen = false;
        this.hamburger.classList.remove('active');
        this.mobileNav.classList.remove('active');
        this.mobileNavOverlay.classList.remove('active');
        document.body.style.overflow = '';
        
        // Return focus to hamburger button
        this.hamburger.focus();
        
        // Update ARIA attributes
        this.hamburger.setAttribute('aria-expanded', 'false');
        this.mobileNav.setAttribute('aria-hidden', 'true');
    }

    preventBodyScroll() {
        // Prevent scrolling on touch devices when menu is open
        let startY = 0;
        
        this.mobileNav.addEventListener('touchstart', (e) => {
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        this.mobileNav.addEventListener('touchmove', (e) => {
            const currentY = e.touches[0].clientY;
            const nav = this.mobileNav;
            
            // Prevent scrolling past the top or bottom
            if ((nav.scrollTop <= 0 && currentY > startY) || 
                (nav.scrollTop >= nav.scrollHeight - nav.clientHeight && currentY < startY)) {
                e.preventDefault();
            }
        }, { passive: false });
    }
}

// Initialize the hamburger menu when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new HamburgerMenu();
});

// Handle window resize to close menu if switching to desktop view
window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        const menu = document.querySelector('.mobile-nav');
        const overlay = document.querySelector('.mobile-nav-overlay');
        const hamburger = document.querySelector('.hamburger');
        
        if (menu && menu.classList.contains('active')) {
            menu.classList.remove('active');
            overlay.classList.remove('active');
            hamburger.classList.remove('active');
            document.body.style.overflow = '';
            
            // Reset ARIA attributes
            hamburger.setAttribute('aria-expanded', 'false');
            menu.setAttribute('aria-hidden', 'true');
        }
    }
});

// Optional: Add smooth scroll behavior for anchor links
document.addEventListener('click', (e) => {
    if (e.target.matches('a[href^="#"]')) {
        e.preventDefault();
        const targetId = e.target.getAttribute('href');
        const targetElement = document.querySelector(targetId);
        
        if (targetElement) {
            targetElement.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }
});
// Handle navbar scroll behavior
let lastScrollTop = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', function() {
    let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
    
    if (currentScroll > lastScrollTop && currentScroll > 100) {
        // Scrolling down & past 100px
        navbar.classList.add('hidden');
    } else if (currentScroll <= 100) {
        // At or near top
        navbar.classList.remove('hidden');
    }
    
    lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
});

// Toggle category dropdown
function toggleCategoryDropdown() {
    const categoryToggle = document.getElementById('categoryToggle');
    const categoryList = document.getElementById('categoryList');
    
    if (categoryToggle && categoryList) {
        categoryToggle.classList.toggle('active');
        categoryList.classList.toggle('show');
    }
}

// Event listener for category toggle
document.addEventListener('DOMContentLoaded', function() {
    const categoryToggle = document.getElementById('categoryToggle');
    if (categoryToggle) {
        categoryToggle.addEventListener('click', toggleCategoryDropdown);
    }

    // Close category dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const categoryDropdown = document.querySelector('.category-dropdown');
        const categoryList = document.getElementById('categoryList');
        if (categoryDropdown && categoryList && !categoryDropdown.contains(event.target)) {
            categoryToggle.classList.remove('active');
            categoryList.classList.remove('show');
        }
    });
});
