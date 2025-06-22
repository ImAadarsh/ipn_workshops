<header class="app-topbar">
            <div class="page-container topbar-menu">
                <div class="d-flex align-items-center gap-2">

                    <!-- Brand Logo -->
                    <a href="dashboard.php" class="logo">
                        <span class="logo-light">
                            <span class="logo-lg"><span class="logo-text">IPN Academy</span></span>
                            <span class="logo-sm"><span class="logo-text-sm">CC</span></span>
                        </span>

                        <span class="logo-dark">
                            <span class="logo-lg"><span class="logo-text">IPN Academy</span></span>
                            <span class="logo-sm"><span class="logo-text-sm">CC</span></span>
                        </span>
                    </a>

                    <!-- Sidebar Menu Toggle Button -->
                    <button class="sidenav-toggle-button btn btn-secondary btn-icon">
                        <i class="ti ti-menu-deep fs-24"></i>
                    </button>

                    <!-- Horizontal Menu Toggle Button -->
                    <button class="topnav-toggle-button" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                        <i class="ti ti-menu-deep fs-22"></i>
                    </button>

                    <!-- Button Trigger Search Modal -->
                    <?php if ($_SESSION['user_type'] === 'admin'): ?>
                    <div class="topbar-search text-muted d-none d-xl-flex gap-2 align-items-center" data-bs-toggle="modal" data-bs-target="#searchModal" type="button">
                        <i class="ti ti-search fs-18"></i>
                        <span class="me-2">Search users, trainers, bookings...</span>
                        <button type="submit" class="ms-auto btn btn-sm btn-primary shadow-none">⌘K</button>
                    </div>
                    <?php endif; ?>

                </div>

                <div class="d-flex align-items-center gap-2">

                    <!-- Search for small devices -->
                    <?php if ($_SESSION['user_type'] === 'admin'): ?>
                    <div class="topbar-item d-flex d-xl-none">
                        <button class="topbar-link btn btn-outline-primary btn-icon" data-bs-toggle="modal" data-bs-target="#searchModal" type="button">
                            <i class="ti ti-search fs-22"></i>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Language Dropdown -->
                    <div class="topbar-item">
                        <div class="dropdown">
                            <button class="topbar-link btn btn-outline-primary btn-icon" data-bs-toggle="dropdown" data-bs-offset="0,24" type="button" aria-haspopup="false" aria-expanded="false">
                                <img src="assets/images/flags/us.svg" alt="user-image" class="w-100 rounded" height="18" id="selected-language-image">
                            </button>

                            <div class="dropdown-menu dropdown-menu-end">
                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item" data-translator-lang="en">
                                    <img src="assets/images/flags/us.svg" alt="user-image" class="me-1 rounded" height="18" data-translator-image> <span class="align-middle">English</span>
                                </a>

                                <!-- item-->
                                <a href="javascript:void(0);" class="dropdown-item" data-translator-lang="hi">
                                    <img src="assets/images/flags/in.svg" alt="user-image" class="me-1 rounded" height="18" data-translator-image> <span class="align-middle">Hindi</span>
                                </a>



                            </div>
                        </div>
                    </div>

                    <!-- Notification Dropdown -->
                    <div class="topbar-item">
                        <div class="dropdown">
                            <button class="topbar-link btn btn-outline-primary btn-icon dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" data-bs-offset="0,24" type="button" data-bs-auto-close="outside" aria-haspopup="false" aria-expanded="false">
                                <i class="ti ti-bell animate-ring fs-22"></i>
                                <span class="noti-icon-badge"></span>
                            </button>

                            <div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg" style="min-height: 300px;">
                                <div class="p-3 border-bottom border-dashed">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h6 class="m-0 fs-16 fw-semibold">Notifications</h6>
                                        </div>
                                    </div>
                                </div>

                                <div class="position-relative" style="max-height: 300px;" data-simplebar id="notifications-container">
                                    <!-- Notifications will be loaded here -->
                                </div>

                                <a href="javascript:void(0);" class="dropdown-item text-center text-primary notify-item border-top py-2">
                                    View All
                                </a>
                            </div>
                        </div>
                    </div>



                    <!-- Button Trigger Customizer Offcanvas -->
                    <div class="topbar-item d-none d-sm-flex">
                        <button class="topbar-link btn btn-outline-primary btn-icon" data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas" type="button">
                            <i class="ti ti-settings fs-22"></i>
                        </button>
                    </div>

                    <!-- Light/Dark Mode Button -->
                    <div class="topbar-item d-none d-sm-flex">
                        <button class="topbar-link btn btn-outline-primary btn-icon" id="light-dark-mode" type="button">
                            <i class="ti ti-moon fs-22"></i>
                        </button>
                    </div>

                    <!-- User Dropdown -->
                    <div class="topbar-item">
                        <div class="dropdown">
                            <a class="topbar-link btn btn-outline-primary dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" data-bs-offset="0,22" type="button" aria-haspopup="false" aria-expanded="false">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="avatar-xs rounded-circle bg-primary text-white">
                                            <?php 
                                            $initials = isset($_SESSION['user_name']) ? 
                                                substr($_SESSION['user_name'], 0, 2) : 'U';
                                            echo strtoupper($initials);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-2 d-none d-lg-block">
                                        <span class="fw-semibold"><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'; ?></span>
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <!-- item-->
                                <div class="dropdown-header noti-title">
                                    <h6 class="text-overflow m-0">Welcome!</h6>
                                </div>

                                <!-- item-->
                                <a href="profile.php" class="dropdown-item">
                                    <i class="ti ti-user-circle me-1 fs-17 align-middle"></i>
                                    <span class="align-middle">My Profile</span>
                                </a>


                                <div class="dropdown-divider"></div>

                                <!-- item-->
                                <a href="logout.php" class="dropdown-item">
                                    <i class="ti ti-logout me-1 fs-17 align-middle"></i>
                                    <span class="align-middle">Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

<!-- Search Modal -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-light">
            <div class="modal-body p-0">
                <div class="search-box p-3">
                    <div class="input-group">
                        <i class="ti ti-search fs-18 input-group-text bg-transparent border-0"></i>
                        <input type="text" class="form-control bg-transparent border-0 search-input" 
                               placeholder="Search users, trainers, bookings..." autofocus>
                        <button type="button" class="btn btn-link text-muted close-search" data-bs-dismiss="modal">
                            ESC
                        </button>
                    </div>
                </div>
                <div class="search-results p-3 border-top" style="max-height: 400px; overflow-y: auto;">
                    <!-- Search results will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add this JavaScript at the end of the file -->
<!-- <script>
document.addEventListener('DOMContentLoaded', function() {
    // Load notifications
    function loadNotifications() {
        fetch('notifications.php')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('notifications-container');
                container.innerHTML = data.map(notification => `
                    <div class="dropdown-item notification-item py-2">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar-xs bg-primary-subtle text-primary rounded-circle">
                                    <i class="ti ti-${notification.type === 'new_user' ? 'user' : 
                                                      notification.type === 'new_booking' ? 'calendar' : 
                                                      'star'} fs-18"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <h6 class="mb-0">${notification.name}</h6>
                                <p class="mb-0 text-muted">${notification.message}</p>
                                <small class="text-muted">${timeSince(new Date(notification.time))}</small>
                            </div>
                        </div>
                    </div>
                `).join('');
            });
    }

    // Handle search
    const searchInput = document.querySelector('.search-input');
    const searchResults = document.querySelector('.search-results');
    let searchTimeout;

    searchInput?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            searchResults.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    searchResults.innerHTML = data.results.map(result => `
                        <div class="search-item p-2">
                            <a href="${result.link}?id=${result.id}" class="text-dark">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-xs bg-primary-subtle text-primary rounded-circle me-2">
                                        <i class="ti ti-${result.type === 'user' ? 'user' : 
                                                          result.type === 'trainer' ? 'user-check' : 
                                                          'calendar'} fs-18"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">${result.name}</h6>
                                        <small class="text-muted">
                                            ${result.type === 'booking' ? 
                                              `Booking on ${result.booking_date}` : 
                                              result.email}
                                        </small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    `).join('') || '<div class="p-3 text-center">No results found</div>';
                });
        }, 300);
    });

    // Time ago function
    function timeSince(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return Math.floor(seconds) + " seconds ago";
    }

    // Load initial notifications
    loadNotifications();

    // Refresh notifications every minute
    setInterval(loadNotifications, 60000);
});
</script> -->