// Dashboard JavaScript file for the Library Management System

document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar
    const toggleBtn = document.querySelector('.toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');
    const contentWrapper = document.querySelector('.content-wrapper');
    const overlay = document.querySelector('.overlay');
    
    if (toggleBtn && sidebar && contentWrapper) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            contentWrapper.classList.toggle('expanded');
            
            // For mobile
            sidebar.classList.toggle('mobile-active');
            if (overlay) {
                overlay.classList.toggle('active');
            }
        });
        
        // Close sidebar when clicking overlay (mobile)
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-active');
                this.classList.remove('active');
            });
        }
        
        // Check screen size and collapse sidebar automatically on small screens
        function checkScreenSize() {
            if (window.innerWidth < 992) {
                sidebar.classList.add('collapsed');
                contentWrapper.classList.add('expanded');
                sidebar.classList.remove('mobile-active');
                if (overlay) overlay.classList.remove('active');
            } else {
                sidebar.classList.remove('collapsed');
                contentWrapper.classList.remove('expanded');
            }
        }
        
        // Initial check
        checkScreenSize();
        
        // Listen for window resize
        window.addEventListener('resize', checkScreenSize);
    }
    
    // Handle view switching (list/grid)
    const viewOptions = document.querySelectorAll('.view-option');
    const booksContainer = document.querySelector('.books-container');
    
    if (viewOptions.length > 0 && booksContainer) {
        viewOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all options
                viewOptions.forEach(opt => opt.classList.remove('active'));
                
                // Add active class to clicked option
                this.classList.add('active');
                
                // Set view mode
                const viewMode = this.getAttribute('data-view');
                booksContainer.className = 'books-container ' + viewMode;
                
                // Save preference in localStorage
                localStorage.setItem('booksViewMode', viewMode);
            });
        });
        
        // Load saved preference
        const savedViewMode = localStorage.getItem('booksViewMode');
        if (savedViewMode) {
            // Set active class on the correct button
            viewOptions.forEach(option => {
                if (option.getAttribute('data-view') === savedViewMode) {
                    option.classList.add('active');
                } else {
                    option.classList.remove('active');
                }
            });
            
            // Apply the view mode
            booksContainer.className = 'books-container ' + savedViewMode;
        }
    }
    
    // Modal functionality
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    const modalCloseButtons = document.querySelectorAll('.modal-close, .modal-cancel');
    const modalOverlays = document.querySelectorAll('.modal-overlay');
    
    // Open modal
    if (modalTriggers.length > 0) {
        modalTriggers.forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                const modalId = this.getAttribute('data-modal-target');
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden'; // Prevent scrolling
                }
            });
        });
    }
    
    // Close modal with close button
    if (modalCloseButtons.length > 0) {
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = ''; // Re-enable scrolling
                }
            });
        });
    }
    
    // Close modal when clicking overlay
    if (modalOverlays.length > 0) {
        modalOverlays.forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = ''; // Re-enable scrolling
                }
            });
        });
    }
    
    // Close modal with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                activeModal.classList.remove('active');
                document.body.style.overflow = ''; // Re-enable scrolling
            }
        }
    });
    
    // Book search functionality
    const searchInput = document.getElementById('book-search');
    const bookItems = document.querySelectorAll('.book-card, .book-item');
    
    if (searchInput && bookItems.length > 0) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            if (query === '') {
                // Show all books if search is cleared
                bookItems.forEach(item => {
                    item.style.display = '';
                });
                return;
            }
            
            // Filter books based on search query
            bookItems.forEach(item => {
                const title = item.querySelector('.book-title').textContent.toLowerCase();
                const author = item.querySelector('.book-author').textContent.toLowerCase();
                
                if (title.includes(query) || author.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // Form file input with preview
    const fileInputs = document.querySelectorAll('.custom-file-input');
    
    if (fileInputs.length > 0) {
        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                const fileLabel = this.nextElementSibling;
                if (fileLabel && fileLabel.classList.contains('custom-file-label')) {
                    if (this.files.length > 0) {
                        fileLabel.textContent = this.files[0].name;
                    } else {
                        fileLabel.textContent = 'Choose file';
                    }
                }
                
                // Preview image if this is an image upload
                const previewContainer = document.querySelector(this.getAttribute('data-preview'));
                if (previewContainer && this.files.length > 0) {
                    const file = this.files[0];
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewContainer.innerHTML = `<img src="${e.target.result}" class="img-preview">`;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        previewContainer.innerHTML = `<div class="file-icon"><i class="fas fa-file"></i> ${file.name}</div>`;
                    }
                }
            });
        });
    }
    
    // Handle book filter
    const filterSelect = document.getElementById('book-filter');
    
    if (filterSelect && bookItems.length > 0) {
        filterSelect.addEventListener('change', function() {
            const filterValue = this.value;
            
            if (filterValue === 'all') {
                // Show all books
                bookItems.forEach(item => {
                    item.style.display = '';
                });
                return;
            }
            
            // Filter books based on selected category
            bookItems.forEach(item => {
                const category = item.getAttribute('data-category').toLowerCase();
                
                if (category === filterValue) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // Mark notification as read
    const notificationItems = document.querySelectorAll('.notification-item');
    
    if (notificationItems.length > 0) {
        notificationItems.forEach(item => {
            item.addEventListener('click', function() {
                // Check if already marked as read
                if (!this.classList.contains('unread')) return;
                
                const notificationId = this.getAttribute('data-id');
                
                // Send AJAX request to mark as read
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        this.classList.remove('unread');
                        
                        // Update notification count
                        const countEl = document.querySelector('.notification-count');
                        if (countEl) {
                            let count = parseInt(countEl.textContent) - 1;
                            if (count <= 0) {
                                countEl.style.display = 'none';
                            } else {
                                countEl.textContent = count;
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
            });
        });
    }
    
    // Dynamic tabs for detailed views
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    if (tabLinks.length > 0 && tabContents.length > 0) {
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all links and content
                tabLinks.forEach(l => l.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Show corresponding content
                const target = this.getAttribute('data-tab');
                const content = document.getElementById(target);
                if (content) {
                    content.classList.add('active');
                }
            });
        });
    }
});

// Dynamic data loading for dashboards
function loadDashboardData() {
    fetch('get_dashboard_data.php')
        .then(response => response.json())
        .then(data => {
            // Update stats
            if (data.stats) {
                Object.keys(data.stats).forEach(key => {
                    const element = document.getElementById(`stat-${key}`);
                    if (element) {
                        element.textContent = data.stats[key];
                    }
                });
            }
            
            // Update recent activity
            if (data.activity && data.activity.length > 0) {
                const activityList = document.querySelector('.activity-list');
                if (activityList) {
                    activityList.innerHTML = '';
                    
                    data.activity.forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'activity-item';
                        
                        li.innerHTML = `
                            <div class="activity-icon">
                                <i class="${getActivityIcon(item.type)}"></i>
                            </div>
                            <div class="activity-info">
                                <h4 class="activity-title">${item.title}</h4>
                                <div class="activity-meta">
                                    <span class="activity-time">${formatTimeAgo(item.timestamp)}</span>
                                    <span class="activity-user">${item.user}</span>
                                </div>
                            </div>
                        `;
                        
                        activityList.appendChild(li);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
        });
}

// Get appropriate icon for activity type
function getActivityIcon(type) {
    switch (type) {
        case 'book_added':
            return 'fas fa-book';
        case 'book_issued':
            return 'fas fa-hand-holding';
        case 'book_returned':
            return 'fas fa-undo';
        case 'fine_paid':
            return 'fas fa-money-bill-wave';
        case 'user_added':
            return 'fas fa-user-plus';
        default:
            return 'fas fa-info-circle';
    }
}

// Format time ago for activity feed
function formatTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = Math.floor((now - time) / 1000); // seconds
    
    if (diff < 60) {
        return 'just now';
    } else if (diff < 3600) {
        const minutes = Math.floor(diff / 60);
        return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
    } else if (diff < 86400) {
        const hours = Math.floor(diff / 3600);
        return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
    } else if (diff < 604800) {
        const days = Math.floor(diff / 86400);
        return `${days} ${days === 1 ? 'day' : 'days'} ago`;
    } else {
        // Format date
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return time.toLocaleDateString(undefined, options);
    }
}