/* ============================================
   TACI PETROLEUM - FORM HANDLING & VALIDATION
   ============================================ */

class FormValidator {
    constructor() {
        this.contactForm = document.getElementById('contact-form');
        this.newsletterForm = document.getElementById('newsletter-form');
        
        if (this.contactForm) {
            this.contactForm.addEventListener('submit', (e) => this.handleContactSubmit(e));
        }
        
        if (this.newsletterForm) {
            this.newsletterForm.addEventListener('submit', (e) => this.handleNewsletterSubmit(e));
        }
    }
    
    // Validation Rules
    validateField(name, value) {
        const validations = {
            name: (val) => val.trim().length >= 3 ? null : 'Name must be at least 3 characters',
            email: (val) => {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(val) ? null : 'Please enter a valid email address';
            },
            phone: (val) => {
                if (!val) return null; // Phone is optional
                const phoneRegex = /^[\d\s\-\+\(\)]+$/;
                return phoneRegex.test(val) && val.replace(/\D/g, '').length >= 10 
                    ? null 
                    : 'Please enter a valid phone number';
            },
            subject: (val) => val ? null : 'Please select a subject',
            message: (val) => val.trim().length >= 10 ? null : 'Message must be at least 10 characters'
        };
        
        const validator = validations[name];
        return validator ? validator(value) : null;
    }
    
    // Clear error message
    clearError(fieldName) {
        const errorElement = document.getElementById(`${fieldName}-error`);
        if (errorElement) {
            errorElement.textContent = '';
        }
        const field = document.getElementById(fieldName);
        if (field) {
            field.style.borderColor = '';
        }
    }
    
    // Show error message
    showError(fieldName, message) {
        const errorElement = document.getElementById(`${fieldName}-error`);
        if (errorElement) {
            errorElement.textContent = message;
        }
        const field = document.getElementById(fieldName);
        if (field) {
            field.style.borderColor = '#dc3545';
        }
    }
    
    // Validate contact form
    validateContactForm() {
        const fields = ['name', 'email', 'subject', 'message'];
        let isValid = true;
        
        fields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                const value = field.value;
                const error = this.validateField(fieldName, value);
                
                if (error) {
                    this.showError(fieldName, error);
                    isValid = false;
                } else {
                    this.clearError(fieldName);
                }
            }
        });
        
        return isValid;
    }
    
    // Handle contact form submission
    async handleContactSubmit(e) {
        e.preventDefault();
        
        if (!this.validateContactForm()) {
            return;
        }
        
        const formData = {
            name: document.getElementById('name').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            subject: document.getElementById('subject').value,
            message: document.getElementById('message').value
        };
        
        const messageEl = document.getElementById('form-message');
        messageEl.className = 'form-message';
        messageEl.textContent = 'Sending...';
        messageEl.style.display = 'block';
        
        try {
            // Simulate API call (replace with actual backend endpoint)
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            // Success
            messageEl.className = 'form-message success';
            messageEl.textContent = '✓ Message sent successfully! We\'ll get back to you soon.';
            this.contactForm.reset();
            
            // Clear message after 5 seconds
            setTimeout(() => {
                messageEl.style.display = 'none';
            }, 5000);
            
        } catch (error) {
            messageEl.className = 'form-message error';
            messageEl.textContent = '✗ Failed to send message. Please try again.';
        }
    }
    
    // Handle newsletter form submission
    async handleNewsletterSubmit(e) {
        e.preventDefault();
        
        const emailInput = document.getElementById('newsletter-email');
        const email = emailInput.value;
        const messageEl = document.getElementById('newsletter-message');
        
        // Validate email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            messageEl.className = 'newsletter-message error';
            messageEl.textContent = 'Please enter a valid email address';
            return;
        }
        
        messageEl.className = 'newsletter-message';
        messageEl.textContent = 'Subscribing...';
        
        try {
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            messageEl.className = 'newsletter-message success';
            messageEl.textContent = '✓ Successfully subscribed!';
            emailInput.value = '';
            
            setTimeout(() => {
                messageEl.textContent = '';
            }, 5000);
            
        } catch (error) {
            messageEl.className = 'newsletter-message error';
            messageEl.textContent = '✗ Subscription failed. Please try again.';
        }
    }
}

// Carousel Manager
class CarouselManager {
    constructor(trackSelector, prevBtnSelector, nextBtnSelector) {
        this.track = document.querySelector(trackSelector);
        this.prevBtn = document.querySelector(prevBtnSelector);
        this.nextBtn = document.querySelector(nextBtnSelector);
        
        if (this.track && this.prevBtn && this.nextBtn) {
            this.itemWidth = this.track.querySelector('.carousel-item').offsetWidth;
            this.currentIndex = 0;
            
            this.prevBtn.addEventListener('click', () => this.scroll(-1));
            this.nextBtn.addEventListener('click', () => this.scroll(1));
        }
    }
    
    scroll(direction) {
        const items = this.track.querySelectorAll('.carousel-item');
        this.currentIndex += direction;
        
        // Wrap around
        if (this.currentIndex < 0) {
            this.currentIndex = items.length - 1;
        } else if (this.currentIndex >= items.length) {
            this.currentIndex = 0;
        }
        
        const scrollAmount = this.currentIndex * (this.itemWidth + 32); // 32px is the gap
        this.track.style.transform = `translateX(-${scrollAmount}px)`;
    }
}

// Auto-scroll carousels
class AutoScrollCarousel {
    constructor(trackSelector, autoScrollInterval = 5000) {
        this.track = document.querySelector(trackSelector);
        this.items = this.track ? this.track.querySelectorAll('.carousel-item') : [];
        this.currentIndex = 0;
        this.isAutoScrolling = true;
        
        if (this.track && this.items.length > 0) {
            // Start auto-scroll
            this.autoScrollInterval = setInterval(() => this.autoScroll(), autoScrollInterval);
            
            // Pause on interaction
            this.track.addEventListener('mouseenter', () => this.pause());
            this.track.addEventListener('mouseleave', () => this.resume());
        }
    }
    
    autoScroll() {
        if (!this.isAutoScrolling) return;
        
        this.currentIndex = (this.currentIndex + 1) % this.items.length;
        const scrollAmount = this.currentIndex * (this.items[0].offsetWidth + 32);
        this.track.style.transform = `translateX(-${scrollAmount}px)`;
    }
    
    pause() {
        this.isAutoScrolling = false;
    }
    
    resume() {
        this.isAutoScrolling = true;
    }
}

// Initialize forms and carousels
document.addEventListener('DOMContentLoaded', () => {
    new FormValidator();
    new CarouselManager('#fleet-carousel .carousel-track', '.carousel-prev', '.carousel-next');
    new AutoScrollCarousel('#testimonials-carousel .testimonials-track', 4000);
});
