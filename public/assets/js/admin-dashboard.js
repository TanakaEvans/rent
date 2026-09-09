// Professional Admin Dashboard JavaScript

class AdminDashboard {
    constructor() {
        this.sidebar = document.querySelector('.sidebar');
        this.mainContent = document.querySelector('.main-content');
        this.sidebarToggle = document.querySelector('.sidebar-toggle');
        this.isMobile = window.innerWidth <= 768;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.checkResponsive();
        this.initModuleCards();
    }

    bindEvents() {
        // Sidebar toggle
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        // Window resize
        window.addEventListener('resize', () => {
            this.checkResponsive();
        });

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', (e) => {
            if (this.isMobile && this.sidebar && !this.sidebar.contains(e.target) && !this.sidebarToggle.contains(e.target)) {
                this.closeSidebar();
            }
        });

        // Navigation active states
        this.initNavigation();
    }

    toggleSidebar() {
        if (this.isMobile) {
            this.sidebar.classList.toggle('mobile-open');
        } else {
            this.sidebar.classList.toggle('collapsed');
            this.mainContent.classList.toggle('expanded');
        }
    }

    closeSidebar() {
        if (this.isMobile) {
            this.sidebar.classList.remove('mobile-open');
        }
    }

    checkResponsive() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 768;
        
        if (wasMobile !== this.isMobile) {
            // Reset sidebar state when changing between mobile/desktop
            this.sidebar.classList.remove('mobile-open', 'collapsed');
            this.mainContent.classList.remove('expanded');
        }
    }

    initNavigation() {
        const navItems = document.querySelectorAll('.nav-item');
        const currentPath = window.location.pathname;

        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href === currentPath) {
                item.classList.add('active');
            }
            
            item.addEventListener('click', (e) => {
                // Remove active class from all items
                navItems.forEach(nav => nav.classList.remove('active'));
                // Add active class to clicked item
                item.classList.add('active');
            });
        });
    }

    initModuleCards() {
        const moduleCards = document.querySelectorAll('.module-card');
        
        moduleCards.forEach(card => {
            card.addEventListener('click', () => {
                const href = card.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            });
        });
    }
}

// Notification System
class NotificationSystem {
    constructor() {
        this.container = null;
        this.createContainer();
    }

    createContainer() {
        if (!document.querySelector('.notification-container')) {
            this.container = document.createElement('div');
            this.container.className = 'notification-container';
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
            `;
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.notification-container');
        }
    }

    show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            background: var(--card-bg);
            border-left: 4px solid var(--${type === 'error' ? 'danger' : type}-color);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            max-width: 400px;
            pointer-events: auto;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            color: var(--text-primary);
        `;

        const icon = this.getIcon(type);
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="color: var(--${type === 'error' ? 'danger' : type}-color);">${icon}</div>
                <div>${message}</div>
                <button class="notification-close" style="
                    background: none;
                    border: none;
                    color: var(--text-secondary);
                    cursor: pointer;
                    padding: 0.25rem;
                    margin-left: auto;
                ">×</button>
            </div>
        `;

        this.container.appendChild(notification);

        // Animate in
        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
        });

        // Close button
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            this.remove(notification);
        });

        // Auto close
        if (duration > 0) {
            setTimeout(() => {
                this.remove(notification);
            }, duration);
        }

        return notification;
    }

    remove(notification) {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }
}

// Loading System
class LoadingSystem {
    constructor() {
        this.overlay = null;
        this.createOverlay();
    }

    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'loading-overlay';
        this.overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        `;

        const spinner = document.createElement('div');
        spinner.style.cssText = `
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        `;

        // Add keyframe animation
        if (!document.querySelector('#loading-styles')) {
            const style = document.createElement('style');
            style.id = 'loading-styles';
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        }

        this.overlay.appendChild(spinner);
        document.body.appendChild(this.overlay);
    }

    show() {
        this.overlay.style.opacity = '1';
        this.overlay.style.visibility = 'visible';
    }

    hide() {
        this.overlay.style.opacity = '0';
        this.overlay.style.visibility = 'hidden';
    }
}

// Form Utilities
class FormUtils {
    static validateForm(formElement) {
        const inputs = formElement.querySelectorAll('[required]');
        let isValid = true;
        let firstInvalid = null;

        inputs.forEach(input => {
            this.clearValidation(input);
            
            if (!input.value.trim()) {
                this.showValidationError(input, 'This field is required');
                isValid = false;
                if (!firstInvalid) firstInvalid = input;
            } else if (input.type === 'email' && !this.isValidEmail(input.value)) {
                this.showValidationError(input, 'Please enter a valid email address');
                isValid = false;
                if (!firstInvalid) firstInvalid = input;
            }
        });

        if (firstInvalid) {
            firstInvalid.focus();
        }

        return isValid;
    }

    static showValidationError(input, message) {
        input.classList.add('is-invalid');
        
        let errorElement = input.parentNode.querySelector('.invalid-feedback');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            errorElement.style.cssText = `
                color: var(--danger-color);
                font-size: 0.875rem;
                margin-top: 0.25rem;
            `;
            input.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;

        // Add invalid styles if not exist
        if (!document.querySelector('#validation-styles')) {
            const style = document.createElement('style');
            style.id = 'validation-styles';
            style.textContent = `
                .form-control.is-invalid {
                    border-color: var(--danger-color);
                    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
                }
            `;
            document.head.appendChild(style);
        }
    }

    static clearValidation(input) {
        input.classList.remove('is-invalid');
        const errorElement = input.parentNode.querySelector('.invalid-feedback');
        if (errorElement) {
            errorElement.remove();
        }
    }

    static isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
}

// Data Table Utilities
class DataTable {
    constructor(tableElement, options = {}) {
        this.table = tableElement;
        this.options = {
            sortable: true,
            searchable: true,
            pagination: true,
            pageSize: 10,
            ...options
        };
        
        this.currentPage = 1;
        this.sortColumn = null;
        this.sortDirection = 'asc';
        this.searchTerm = '';
        
        this.init();
    }

    init() {
        if (this.options.searchable) {
            this.createSearchBox();
        }
        
        if (this.options.sortable) {
            this.initSorting();
        }
        
        if (this.options.pagination) {
            this.createPagination();
        }
        
        this.render();
    }

    createSearchBox() {
        const searchBox = document.createElement('div');
        searchBox.className = 'table-search mb-3';
        searchBox.innerHTML = `
            <input type="text" class="form-control" placeholder="Search..." style="max-width: 300px;">
        `;
        
        const searchInput = searchBox.querySelector('input');
        searchInput.addEventListener('input', (e) => {
            this.searchTerm = e.target.value.toLowerCase();
            this.currentPage = 1;
            this.render();
        });
        
        this.table.parentNode.insertBefore(searchBox, this.table);
    }

    initSorting() {
        const headers = this.table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const column = header.dataset.sortable;
                if (this.sortColumn === column) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = column;
                    this.sortDirection = 'asc';
                }
                this.render();
            });
        });
    }

    createPagination() {
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'table-pagination mt-3 d-flex justify-content-between align-items-center';
        paginationContainer.innerHTML = `
            <div class="pagination-info"></div>
            <div class="pagination-controls"></div>
        `;
        
        this.table.parentNode.appendChild(paginationContainer);
        this.paginationContainer = paginationContainer;
    }

    render() {
        // This is a basic implementation - you would extend this based on your needs
        console.log('Rendering table with:', {
            page: this.currentPage,
            sort: this.sortColumn,
            direction: this.sortDirection,
            search: this.searchTerm
        });
    }
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize dashboard
    window.dashboard = new AdminDashboard();
    
    // Initialize notification system
    window.notifications = new NotificationSystem();
    
    // Initialize loading system
    window.loading = new LoadingSystem();
    
    // Initialize tooltips (if any elements have data-tooltip)
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
});

// Tooltip functions
function showTooltip(e) {
    const element = e.target;
    const text = element.dataset.tooltip;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: var(--dark-bg);
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        z-index: 9999;
        pointer-events: none;
        white-space: nowrap;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    element._tooltip = tooltip;
}

function hideTooltip(e) {
    const element = e.target;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

// Export utilities for use in other scripts
window.AdminUtils = {
    FormUtils,
    DataTable,
    showNotification: (message, type, duration) => window.notifications.show(message, type, duration),
    showLoading: () => window.loading.show(),
    hideLoading: () => window.loading.hide()
};
