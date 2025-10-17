/**
 * Email Subscription System - Frontend JavaScript
 */

class EmailSubscriptionApp {
    constructor() {
        this.form = null;
        this.emailInput = null;
        this.submitBtn = null;
        this.messageContainer = null;
        this.isSlowConnection = false;

        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.setupElements();
            this.setupEventListeners();
            this.checkConnectionSpeed();
            this.setupVideoFallback();
        });
    }

    setupElements() {
        this.form = document.getElementById('subscription-form');
        this.emailInput = document.getElementById('email');
        this.submitBtn = document.getElementById('submit-btn');
        this.messageContainer = document.getElementById('message-container');
    }

    setupEventListeners() {
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        }

        if (this.emailInput) {
            this.emailInput.addEventListener('input', () => this.clearValidation());
            this.emailInput.addEventListener('blur', () => this.validateEmailFormat());
        }

        // Connection status
        window.addEventListener('online', () => this.updateConnectionStatus(true));
        window.addEventListener('offline', () => this.updateConnectionStatus(false));
    }

    async handleSubmit(e) {
        e.preventDefault();

        const email = this.emailInput.value.trim();

        if (!this.validateEmailFormat(email)) {
            return;
        }

        this.setLoading(true);
        this.clearMessages();

        try {
            const response = await fetch('api/subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email })
            });

            const data = await response.json();

            if (data.success) {
                this.showMessage(data.message, 'success');
                this.form.reset();

                // Show corrected email if applicable
                if (data.corrected) {
                    this.showMessage(`Email corrected to: ${data.corrected}`, 'warning');
                }

                // Optional: Track successful subscription
                this.trackEvent('subscription_success', { email });

            } else {
                this.showMessage(data.message || 'Something went wrong. Please try again.', 'error');
            }

        } catch (error) {
            console.error('Subscription error:', error);

            if (!navigator.onLine) {
                this.showMessage('Please check your internet connection and try again.', 'error');
            } else {
                this.showMessage('Something went wrong. Please try again later.', 'error');
            }
        } finally {
            this.setLoading(false);
        }
    }

    validateEmailFormat(email = null) {
        const emailValue = email || this.emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailValue) {
            this.setInputState('error');
            this.showMessage('Email address is required.', 'error');
            return false;
        }

        if (!emailRegex.test(emailValue)) {
            this.setInputState('error');
            this.showMessage('Please enter a valid email address.', 'error');
            return false;
        }

        if (emailValue.length > 254) {
            this.setInputState('error');
            this.showMessage('Email address is too long.', 'error');
            return false;
        }

        this.setInputState('success');
        return true;
    }

    setInputState(state) {
        if (!this.emailInput) return;

        this.emailInput.classList.remove('error', 'success');
        if (state) {
            this.emailInput.classList.add(state);
        }
    }

    clearValidation() {
        this.setInputState(null);
        this.clearMessages();
    }

    setLoading(isLoading) {
        if (!this.submitBtn) return;

        this.submitBtn.disabled = isLoading;
        this.submitBtn.classList.toggle('loading', isLoading);

        if (this.emailInput) {
            this.emailInput.disabled = isLoading;
        }
    }

    showMessage(message, type = 'info') {
        if (!this.messageContainer) return;

        const messageElement = document.createElement('div');
        messageElement.className = `message ${type}`;
        messageElement.textContent = message;

        this.messageContainer.innerHTML = '';
        this.messageContainer.appendChild(messageElement);

        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                if (messageElement.parentNode) {
                    messageElement.remove();
                }
            }, 5000);
        }
    }

    clearMessages() {
        if (this.messageContainer) {
            this.messageContainer.innerHTML = '';
        }
    }

    updateConnectionStatus(isOnline) {
        let indicator = document.getElementById('connection-indicator');

        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'connection-indicator';
            indicator.className = 'connection-indicator';
            document.body.appendChild(indicator);
        }

        if (isOnline) {
            indicator.textContent = 'Online';
            indicator.className = 'connection-indicator online';
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 3000);
        } else {
            indicator.textContent = 'Offline';
            indicator.className = 'connection-indicator offline';
            indicator.style.display = 'block';
        }
    }

    checkConnectionSpeed() {
        // Use Connection API if available for more accurate detection
        if ('connection' in navigator) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (connection) {
                // Consider 2G, slow-2g, or effective type of 'slow-2g' as slow
                this.isSlowConnection = connection.effectiveType === 'slow-2g' ||
                                       connection.effectiveType === '2g' ||
                                       connection.downlink < 1.5; // Less than 1.5 Mbps

                if (this.isSlowConnection) {
                    this.showSlowConnectionNotice();
                }
                return;
            }
        }

        // Fallback: Use Fetch API with a small request and timeout
        const startTime = Date.now();
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3000);

        fetch('data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', {
            signal: controller.signal,
            cache: 'no-cache'
        })
        .then(() => {
            clearTimeout(timeoutId);
            const loadTime = Date.now() - startTime;
            this.isSlowConnection = loadTime > 2000; // Consider 2+ seconds slow

            if (this.isSlowConnection) {
                this.showSlowConnectionNotice();
            }
        })
        .catch(() => {
            clearTimeout(timeoutId);
            this.isSlowConnection = true;
            this.showSlowConnectionNotice();
        });
    }

    showSlowConnectionNotice() {
        // Slow connection detected - video will be disabled silently without user notification
        // This improves user experience by avoiding unnecessary messages
    }

    setupVideoFallback() {
        const video = document.getElementById('bg-video');

        if (!video) return;

        // Hide video on slow connections
        if (this.isSlowConnection) {
            video.style.display = 'none';
            document.body.classList.add('fallback-bg');
            return;
        }

        video.addEventListener('error', () => {
            console.log('Video failed to load, using fallback background');
            video.style.display = 'none';
            document.body.classList.add('fallback-bg');
        });

        video.addEventListener('loadstart', () => {
            // Timeout for video loading
            setTimeout(() => {
                if (video.readyState < 2) {
                    video.style.display = 'none';
                    document.body.classList.add('fallback-bg');
                }
            }, 5000);
        });
    }

    trackEvent(eventName, properties = {}) {
        // Placeholder for analytics tracking
        console.log('Track event:', eventName, properties);

        // You can integrate with Google Analytics, Mixpanel, etc.
        // Example:
        // gtag('event', eventName, properties);
        // mixpanel.track(eventName, properties);
    }
}

// Utility functions
class EmailUtils {
    static isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email) && email.length <= 254;
    }

    static sanitizeEmail(email) {
        return email.toLowerCase().trim();
    }

    static suggestCorrection(email) {
        const corrections = {
            'gmail.co': 'gmail.com',
            'gmail.cm': 'gmail.com',
            'yahoo.co': 'yahoo.com',
            'yahoo.cm': 'yahoo.com',
            'hotmail.co': 'hotmail.com',
            'hotmail.cm': 'hotmail.com'
        };

        for (const [wrong, correct] of Object.entries(corrections)) {
            if (email.includes('@' + wrong)) {
                return email.replace('@' + wrong, '@' + correct);
            }
        }

        return email;
    }
}

// Initialize the application
const app = new EmailSubscriptionApp();