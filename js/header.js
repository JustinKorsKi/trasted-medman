// Header JavaScript Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Navigation
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileNav = document.getElementById('mobileNav');
    const mobileNavClose = document.getElementById('mobileNavClose');
    const mobileNavOverlay = document.createElement('div');
    mobileNavOverlay.className = 'mobile-nav-overlay';
    document.body.appendChild(mobileNavOverlay);

    function openMobileNav() {
        mobileNav.classList.add('active');
        mobileNavOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileNav() {
        mobileNav.classList.remove('active');
        mobileNavOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    mobileMenuToggle?.addEventListener('click', openMobileNav);
    mobileNavClose?.addEventListener('click', closeMobileNav);
    mobileNavOverlay?.addEventListener('click', closeMobileNav);

    // Close mobile nav when clicking on links
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', closeMobileNav);
    });

    // User Dropdown
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.querySelector('.user-dropdown');

    function toggleUserDropdown() {
        userDropdown?.classList.toggle('active');
        // Close notification dropdown if open
        const notificationDropdown = document.querySelector('.notification-dropdown');
        if (notificationDropdown && notificationDropdown !== userDropdown) {
            notificationDropdown.classList.remove('active');
        }
    }

    userMenuBtn?.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleUserDropdown();
    });

    // Notification Dropdown
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.querySelector('.notification-dropdown');

    function toggleNotificationDropdown() {
        notificationDropdown?.classList.toggle('active');
        // Close user dropdown if open
        if (userDropdown && userDropdown !== notificationDropdown) {
            userDropdown.classList.remove('active');
        }
    }

    notificationBtn?.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleNotificationDropdown();
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        userDropdown?.classList.remove('active');
        notificationDropdown?.classList.remove('active');
    });

    // Prevent dropdowns from closing when clicking inside them
    userDropdown?.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    notificationDropdown?.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Mark notifications as read
    const markAllReadBtn = document.querySelector('.mark-all-read');
    const notificationItems = document.querySelectorAll('.notification-item');
    
    markAllReadBtn?.addEventListener('click', function() {
        notificationItems.forEach(item => {
            item.classList.remove('unread');
        });
        
        // Remove notification badge
        const notificationBadge = document.querySelector('.notification-badge');
        if (notificationBadge) {
            notificationBadge.style.display = 'none';
        }
        
        // Hide mark all read button
        markAllReadBtn.style.display = 'none';
    });

    // Handle notification item clicks
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            this.classList.remove('unread');
            // You can add navigation logic here
            console.log('Notification clicked:', this);
        });
    });

    // Active navigation highlighting
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
    
    navLinks.forEach(link => {
        const linkPath = new URL(link.href).pathname;
        if (linkPath === currentPath) {
            link.classList.add('active');
        }
    });

    // Smooth scroll for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Header scroll effect
    let lastScrollTop = 0;
    const header = document.querySelector('.site-header');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scrolling down
            header?.classList.add('scrolled-down');
            header?.classList.remove('scrolled-up');
        } else {
            // Scrolling up
            header?.classList.add('scrolled-up');
            header?.classList.remove('scrolled-down');
        }
        
        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    });

    // Search functionality (if search is added)
    const searchToggle = document.querySelector('.search-toggle');
    const searchOverlay = document.querySelector('.search-overlay');
    const searchClose = document.querySelector('.search-close');
    
    function openSearch() {
        searchOverlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
        // Focus on search input
        const searchInput = searchOverlay?.querySelector('input[type="search"]');
        if (searchInput) {
            setTimeout(() => searchInput.focus(), 100);
        }
    }
    
    function closeSearch() {
        searchOverlay?.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    searchToggle?.addEventListener('click', openSearch);
    searchClose?.addEventListener('click', closeSearch);
    
    // Close search on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSearch();
            closeMobileNav();
            userDropdown?.classList.remove('active');
            notificationDropdown?.classList.remove('active');
        }
    });

    // Lazy loading for images (if needed)
    const lazyImages = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });

    lazyImages.forEach(img => imageObserver.observe(img));

    // Performance optimization: Debounce scroll events
    function debounce(func, wait) {
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

    // Apply debouncing to scroll events
    const debouncedScroll = debounce(function() {
        // Your scroll-based logic here
    }, 100);

    window.addEventListener('scroll', debouncedScroll);

    // Touch device detection
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    if (isTouchDevice) {
        document.body.classList.add('touch-device');
    }

    // Print styles handler
    window.addEventListener('beforeprint', function() {
        document.body.classList.add('printing');
    });

    window.addEventListener('afterprint', function() {
        document.body.classList.remove('printing');
    });
});

// Utility functions
const HeaderUtils = {
    // Get cookie value
    getCookie: function(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    },

    // Set cookie
    setCookie: function(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = `expires=${date.toUTCString()}`;
        document.cookie = `${name}=${value};${expires};path=/`;
    },

    // Check if element is in viewport
    isInViewport: function(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    },

    // Animate number
    animateNumber: function(element, start, end, duration) {
        const startTime = performance.now();
        
        function updateNumber(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = Math.floor(start + (end - start) * progress);
            
            element.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(updateNumber);
            }
        }
        
        requestAnimationFrame(updateNumber);
    }
};

// Export for global use
window.HeaderUtils = HeaderUtils;
