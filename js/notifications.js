// notifications.js

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    class CustomNotification {
        constructor() {
            this.init();
        }

        init() {
            this.createNotificationContainer();
        }

        createNotificationContainer() {
            if (!document.getElementById('notification-container')) {
                const container = document.createElement('div');
                container.id = 'notification-container';
                container.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    max-width: 400px;
                `;
                document.body.appendChild(container);
                
                // Add CSS animations
                this.addStyles();
            }
        }

        addStyles() {
            if (!document.getElementById('notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
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
                    
                    @keyframes slideOut {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                    }
                    
                    .notification {
                        animation: slideIn 0.3s ease-out;
                    }
                `;
                document.head.appendChild(style);
            }
        }

        show(message, type = 'info', duration = 5000) {
            // Make sure container exists
            this.createNotificationContainer();
            
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                background: ${this.getBackgroundColor(type)};
                color: white;
                padding: 15px 20px;
                margin-bottom: 10px;
                border-radius: 5px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                cursor: pointer;
                position: relative;
            `;
            
            notification.innerHTML = `
                <div class="notification-content">${message}</div>
            `;

            const container = document.getElementById('notification-container');
            container.appendChild(notification);

            // Auto remove after duration
            if (duration > 0) {
                setTimeout(() => {
                    this.remove(notification);
                }, duration);
            }

            // Click to dismiss
            notification.addEventListener('click', () => {
                this.remove(notification);
            });
            
            return notification;
        }

        remove(notification) {
            notification.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }

        getBackgroundColor(type) {
            const colors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            };
            return colors[type] || colors.info;
        }
    }

    // Create global instance
    window.CustomNotification = new CustomNotification();
    
    console.log('CustomNotification loaded successfully');
});