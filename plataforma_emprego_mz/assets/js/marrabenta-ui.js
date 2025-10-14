/* ============================================
   ✨ MARRABENTA UI - JavaScript Interactions
   Micro-interações e Animações Suaves
============================================ */

class MarrabentaUI {
    constructor() {
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeComponents());
        } else {
            this.initializeComponents();
        }
    }

    initializeComponents() {
        this.initLucideIcons();
        this.initScrollEffects();
        this.initCardAnimations();
        this.initCounterAnimations();
        this.initRippleEffects();
        this.initMobileNavigation();
        this.initParallaxEffects();
        this.initSearchEnhancements();
        this.initAccessibilityFeatures();
        
        console.log('🎨 Marrabenta UI initialized successfully!');
    }

    /* ============================================
       🎯 LUCIDE ICONS
    ============================================ */
    initLucideIcons() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    /* ============================================
       📜 SCROLL EFFECTS
    ============================================ */
    initScrollEffects() {
        const header = document.querySelector('.main-header');
        let lastScrollY = window.scrollY;
        let ticking = false;

        const updateHeader = () => {
            const scrollY = window.scrollY;
            
            if (scrollY > 100) {
                header?.classList.add('scrolled');
            } else {
                header?.classList.remove('scrolled');
            }

            // Hide/show header on scroll
            if (scrollY > lastScrollY && scrollY > 200) {
                header?.style.setProperty('transform', 'translateY(-100%)');
            } else {
                header?.style.setProperty('transform', 'translateY(0)');
            }

            lastScrollY = scrollY;
            ticking = false;
        };

        const requestTick = () => {
            if (!ticking) {
                requestAnimationFrame(updateHeader);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestTick, { passive: true });
    }

    /* ============================================
       🎴 CARD ANIMATIONS
    ============================================ */
    initCardAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 100);
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Aplicar animação aos cards
        const cards = document.querySelectorAll('.xima-card, .card');
        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Aplicar animação aos quick links
        const quickLinks = document.querySelectorAll('.quick-link');
        quickLinks.forEach((link, index) => {
            link.style.opacity = '0';
            link.style.transform = 'translateY(20px)';
            link.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            
            setTimeout(() => {
                link.style.opacity = '1';
                link.style.transform = 'translateY(0)';
            }, index * 50);
        });
    }

    /* ============================================
       🔢 COUNTER ANIMATIONS
    ============================================ */
    initCounterAnimations() {
        const animateCounter = (element) => {
            const target = parseInt(element.textContent.replace(/[^\d]/g, ''));
            const duration = 2000;
            const step = target / (duration / 16);
            let current = 0;

            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 16);
        };

        const statNumbers = document.querySelectorAll('.stat-number');
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Add a small delay for better effect
                    setTimeout(() => {
                        animateCounter(entry.target);
                    }, 300);
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        statNumbers.forEach(stat => {
            statsObserver.observe(stat);
        });
    }

    /* ============================================
       💧 RIPPLE EFFECTS
    ============================================ */
    initRippleEffects() {
        const createRipple = (event, element) => {
            const ripple = document.createElement('span');
            const rect = element.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
                z-index: 1;
            `;

            // Ensure relative positioning
            const originalPosition = getComputedStyle(element).position;
            if (originalPosition === 'static') {
                element.style.position = 'relative';
            }
            element.style.overflow = 'hidden';

            element.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        };

        // Add ripple to buttons and interactive elements
        const interactiveElements = document.querySelectorAll(
            '.btn, .quick-link, .card-title a, .header-nav a'
        );
        
        interactiveElements.forEach(element => {
            element.addEventListener('click', (e) => {
                createRipple(e, element);
            });
        });

        // Add ripple animation CSS
        if (!document.getElementById('ripple-styles')) {
            const style = document.createElement('style');
            style.id = 'ripple-styles';
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /* ============================================
       📱 MOBILE NAVIGATION
    ============================================ */
    initMobileNavigation() {
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const mobileNav = document.querySelector('.mobile-nav');
        const headerNav = document.querySelector('.header-nav');

        // Create mobile toggle if it doesn't exist
        if (!mobileToggle && window.innerWidth <= 768) {
            const toggle = document.createElement('button');
            toggle.className = 'mobile-menu-toggle';
            toggle.innerHTML = '<i data-lucide="menu"></i>';
            toggle.setAttribute('aria-label', 'Toggle mobile menu');
            
            const headerContainer = document.querySelector('.header-container');
            headerContainer?.appendChild(toggle);
            
            // Create mobile nav
            const nav = document.createElement('nav');
            nav.className = 'mobile-nav';
            if (headerNav) {
                nav.innerHTML = headerNav.innerHTML;
            }
            
            document.body.appendChild(nav);
            
            // Add toggle functionality
            toggle.addEventListener('click', () => {
                nav.classList.toggle('open');
                toggle.innerHTML = nav.classList.contains('open') 
                    ? '<i data-lucide="x"></i>' 
                    : '<i data-lucide="menu"></i>';
                
                // Re-init lucide icons
                this.initLucideIcons();
            });
            
            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                    nav.classList.remove('open');
                    toggle.innerHTML = '<i data-lucide="menu"></i>';
                    this.initLucideIcons();
                }
            });
        }
    }

    /* ============================================
       🌄 PARALLAX EFFECTS
    ============================================ */
    initParallaxEffects() {
        const hero = document.querySelector('.hero');
        let ticking = false;

        const updateParallax = () => {
            const scrolled = window.pageYOffset;
            
            if (hero && scrolled < 500) {
                const parallaxSpeed = 0.3;
                const yPos = scrolled * parallaxSpeed;
                const opacity = 1 - (scrolled / 500);
                
                hero.style.transform = `translateY(${yPos}px)`;
                hero.style.opacity = Math.max(opacity, 0.3);
            }
            
            ticking = false;
        };

        const requestTick = () => {
            if (!ticking) {
                requestAnimationFrame(updateParallax);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestTick, { passive: true });
    }

    /* ============================================
       🔍 SEARCH ENHANCEMENTS
    ============================================ */
    initSearchEnhancements() {
        const searchForm = document.querySelector('.search-form');
        const searchInput = document.querySelector('.search-input');
        const searchBtn = document.querySelector('.btn-search');

        if (searchInput) {
            // Add focus effects
            searchInput.addEventListener('focus', () => {
                searchForm?.classList.add('focused');
            });

            searchInput.addEventListener('blur', () => {
                searchForm?.classList.remove('focused');
            });

            // Add loading state on form submit
            searchForm?.addEventListener('submit', (e) => {
                if (searchBtn) {
                    searchBtn.style.opacity = '0.7';
                    searchBtn.style.pointerEvents = 'none';
                    
                    const originalText = searchBtn.innerHTML;
                    searchBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Buscando...';
                    
                    this.initLucideIcons();
                    
                    // Reset after a delay (in case of client-side navigation)
                    setTimeout(() => {
                        searchBtn.style.opacity = '1';
                        searchBtn.style.pointerEvents = 'auto';
                        searchBtn.innerHTML = originalText;
                        this.initLucideIcons();
                    }, 3000);
                }
            });
        }
    }

    /* ============================================
       ♿ ACCESSIBILITY FEATURES
    ============================================ */
    initAccessibilityFeatures() {
        // Add focus-visible polyfill behavior
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                document.body.classList.add('using-keyboard');
            }
        });

        document.addEventListener('mousedown', () => {
            document.body.classList.remove('using-keyboard');
        });

        // Improve button accessibility
        const buttons = document.querySelectorAll('.btn, .quick-link');
        buttons.forEach(button => {
            if (!button.hasAttribute('role')) {
                button.setAttribute('role', 'button');
            }
            
            // Add keyboard support for non-button elements
            if (button.tagName !== 'BUTTON' && button.tagName !== 'A') {
                button.setAttribute('tabindex', '0');
                button.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        button.click();
                    }
                });
            }
        });

        // Announce page changes for screen readers
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        announcer.className = 'sr-only';
        document.body.appendChild(announcer);
        
        window.MarrabentaUI.announcer = announcer;
    }

    /* ============================================
       🛠️ UTILITY METHODS
    ============================================ */
    static announce(message) {
        if (window.MarrabentaUI?.announcer) {
            window.MarrabentaUI.announcer.textContent = message;
        }
    }

    static addFloatingAnimation(element) {
        if (element) {
            element.classList.add('animate-float');
        }
    }

    static removeFloatingAnimation(element) {
        if (element) {
            element.classList.remove('animate-float');
        }
    }

    static showToast(message, type = 'info') {
        // Simple toast notification system
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        toast.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: var(--verde-esperanca);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-strong);
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;

        if (type === 'error') {
            toast.style.background = 'var(--coral-vivo)';
        } else if (type === 'success') {
            toast.style.background = 'var(--verde-esperanca)';
        } else if (type === 'warning') {
            toast.style.background = 'var(--dourado-sol)';
        }

        document.body.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);

        // Animate out and remove
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }
}

/* ============================================
   🚀 INITIALIZATION
============================================ */

// Initialize Marrabenta UI
window.MarrabentaUI = new MarrabentaUI();

// Add additional CSS for animations
const additionalStyles = document.createElement('style');
additionalStyles.textContent = `
    .search-form.focused {
        box-shadow: var(--shadow-glow) !important;
        transform: translateY(-2px) !important;
    }
    
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .using-keyboard .btn:focus,
    .using-keyboard .quick-link:focus,
    .using-keyboard .header-nav a:focus {
        outline: 3px solid var(--dourado-sol) !important;
        outline-offset: 2px !important;
    }
    
    .main-header {
        transition: transform 0.3s ease, background 0.3s ease !important;
    }
    
    .toast {
        font-family: var(--font-body) !important;
        font-weight: 500 !important;
    }
`;

document.head.appendChild(additionalStyles);

// Export for external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MarrabentaUI;
}