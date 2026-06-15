/* ============================================
   TACI PETROLEUM - MAIN UTILITIES
   ============================================ */

// Utility: Intersection Observer for lazy elements
class LazyLoadObserver {
    constructor() {
        this.images = document.querySelectorAll('img[loading="lazy"]');
        this.observe();
    }
    
    observe() {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        });
        
        this.images.forEach(img => imageObserver.observe(img));
    }
}

// Utility: Accessibility improvements
class AccessibilityHelper {
    constructor() {
        this.addAriaLabels();
        this.addSkipLink();
        this.handleKeyboardNavigation();
    }
    
    addAriaLabels() {
        // Add aria-label to icon buttons if not present
        document.querySelectorAll('button svg').forEach(svg => {
            const button = svg.parentElement;
            if (button && !button.getAttribute('aria-label')) {
                // Set default aria-label based on context
                if (button.classList.contains('hamburger')) {
                    button.setAttribute('aria-label', 'Toggle navigation menu');
                }
            }
        });
    }
    
    addSkipLink() {
        const skipLink = document.createElement('a');
        skipLink.href = '#main';
        skipLink.textContent = 'Skip to main content';
        skipLink.style.cssText = `
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--accent-gold);
            padding: 8px;
            z-index: 100;
        `;
        
        skipLink.addEventListener('focus', () => {
            skipLink.style.top = '0';
        });
        
        skipLink.addEventListener('blur', () => {
            skipLink.style.top = '-40px';
        });
        
        document.body.insertBefore(skipLink, document.body.firstChild);
    }
    
    handleKeyboardNavigation() {
        // Allow escape key to close mobile menu
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const navMenu = document.getElementById('nav-menu');
                const hamburger = document.getElementById('hamburger');
                if (navMenu && navMenu.classList.contains('active')) {
                    navMenu.classList.remove('active');
                    hamburger.classList.remove('active');
                }
            }
        });
    }
}

// Performance: Defer non-critical CSS
class DeferredStyles {
    static init() {
        // Preload fonts
        const fontLink = document.createElement('link');
        fontLink.rel = 'preload';
        fontLink.as = 'style';
        fontLink.href = 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&family=Roboto+Mono:wght@400;700&display=swap';
        document.head.appendChild(fontLink);
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    new LazyLoadObserver();
    new AccessibilityHelper();
    DeferredStyles.init();
    
    // Log page performance metrics
    if (window.performance && window.performance.timing) {
        window.addEventListener('load', () => {
            const perfData = window.performance.timing;
            const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            console.log(`✓ TACI Landing Page loaded in ${pageLoadTime}ms`);
        });
    }
});

// Handle visibility changes (pause animations when tab is not visible)
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        // Pause animations
        document.querySelectorAll('svg, [style*="animation"]').forEach(el => {
            el.style.animationPlayState = 'paused';
        });
    } else {
        // Resume animations
        document.querySelectorAll('svg, [style*="animation"]').forEach(el => {
            el.style.animationPlayState = 'running';
        });
    }
});
