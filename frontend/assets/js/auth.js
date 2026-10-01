/**
 * AgriConnect - Shared Authentication JavaScript
 * Handles login state, token management, and role-based redirects
 */

const API_BASE = '../../backend/api';

// Auth utility functions
const Auth = {
    /**
     * Get stored token
     */
    getToken() {
        return localStorage.getItem('agriconnect_token');
    },

    /**
     * Get stored user data
     */
    getUser() {
        const user = localStorage.getItem('agriconnect_user');
        return user ? JSON.parse(user) : null;
    },

    /**
     * Save auth data after login
     */
    saveAuth(data) {
        if (data.token) {
            localStorage.setItem('agriconnect_token', data.token);
        }
        if (data.user) {
            localStorage.setItem('agriconnect_user', JSON.stringify(data.user));
        }
    },

    /**
     * Clear auth data on logout
     */
    clearAuth() {
        localStorage.removeItem('agriconnect_token');
        localStorage.removeItem('agriconnect_user');
    },

    /**
     * Check if user is logged in
     */
    isLoggedIn() {
        return !!this.getToken();
    },

    /**
     * Check if user has a specific role
     */
    hasRole(role) {
        const user = this.getUser();
        return user && user.role === role;
    },

    /**
     * Require authentication - redirect to login if not authenticated
     */
    requireAuth(allowedRoles) {
        if (!this.isLoggedIn()) {
            window.location.href = '../login.php';
            return false;
        }

        const user = this.getUser();
        if (allowedRoles && !allowedRoles.includes(user.role)) {
            alert('Access denied for your role.');
            this.redirectByRole(user.role);
            return false;
        }

        return true;
    },

    /**
     * Redirect user to their dashboard based on role
     */
    redirectByRole(role) {
        const redirects = {
            'admin': '../admin/dashboard.php',
            'extension_officer': '../extension/dashboard.php',
            'farmer': '../farmer/dashboard.php'
        };
        window.location.href = redirects[role] || '../login.php';
    },

    /**
     * Logout - uses PHP server-side session destroy
     */
    async logout() {
        this.clearAuth();
        window.location.href = '../logout.php';
    },

    /**
     * Update user display in sidebar/header
     */
    updateUserDisplay() {
        const user = this.getUser();
        if (!user) return;

        const nameEl = document.getElementById('sidebar-user-name');
        const roleEl = document.getElementById('sidebar-user-role');

        if (nameEl) nameEl.textContent = user.name;
        if (roleEl) roleEl.textContent = user.role.replace('_', ' ');
    }
};

// API utility functions
const API = {
    /**
     * Make authenticated API request
     */
    async request(url, options = {}) {
        const token = Auth.getToken();
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (response.status === 401) {
                Auth.clearAuth();
                window.location.href = '../login.php';
                return null;
            }

            return data;
        } catch (error) {
            console.error('API request failed:', error);
            return { error: 'Network error. Please check your connection.' };
        }
    },

    /**
     * GET request
     */
    async get(url) {
        return this.request(url, { method: 'GET' });
    },

    /**
     * POST request with JSON body
     */
    async post(url, body) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(body)
        });
    },

    /**
     * PUT request with JSON body
     */
    async put(url, body) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(body)
        });
    },

    /**
     * DELETE request
     */
    async delete(url) {
        return this.request(url, { method: 'DELETE' });
    },

    /**
     * Upload file with form data
     */
    async upload(url, formData) {
        const token = Auth.getToken();
        const headers = {};

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData,
                credentials: 'same-origin'
            });

            return await response.json();
        } catch (error) {
            console.error('Upload failed:', error);
            return { error: 'Upload failed. Please try again.' };
        }
    }
};

// UI utility functions
const UI = {
    /**
     * Show a notification/alert
     */
    showAlert(message, type = 'info', container = null) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        const span = document.createElement('span');
        span.textContent = message;
        alertDiv.appendChild(span);

        const target = container || document.querySelector('.content-area');
        if (target) {
            target.insertBefore(alertDiv, target.firstChild);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    },

    /**
     * Show loading spinner
     */
    showLoading(container) {
        container.innerHTML = '<div class="spinner"></div>';
    },

    /**
     * Format date
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    },

    /**
     * Format price in SSP
     */
    formatPrice(amount) {
        return new Intl.NumberFormat('en-SS', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(amount) + ' SSP';
    },

    /**
     * Toggle modal
     */
    toggleModal(modalId, show = true) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.toggle('active', show);
        }
    },

    /**
     * Confirm action
     */
    confirm(message) {
        return confirm(message);
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Update user display in sidebar
    Auth.updateUserDisplay();

    // Setup logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Auth.logout();
        });
    }

    // Setup mobile menu toggle
    const menuToggle = document.getElementById('menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
        });
    }
});
