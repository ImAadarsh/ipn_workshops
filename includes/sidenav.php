<div class="sidenav-menu">

<!-- Brand Logo -->
<a href="index.php" class="logo">
                    <!-- Brand Logo -->
                    <a href="dashboard.php" class="logo">
                        <span class="logo-light">
                            <span class="logo-lg"><img style="min-height: 55px !important;" src="logo.svg" alt="IPN Academy Logo" ></span>
                            <span class="logo-sm"><img style="min-height: 40px !important;" src="logo.png" alt="IPN Academy Logo" class="logo-img"></span>
                        </span>

                        <span class="logo-dark">
                            <span class="logo-lg"><img style="min-height: 55px !important;" src="logo.svg" alt="IPN Academy Logo" ></span>
                            <span class="logo-sm"><img style="min-height: 40px !important;" src="logo.png" alt="IPN Academy Logo" class="logo-img"></span>
                        </span>
                    </a>
</a>

<!-- Sidebar Hover Menu Toggle Button -->
<button class="button-sm-hover">
    <i class="ti ti-circle align-middle"></i>
</button>

<!-- Full Sidebar Menu Close Button -->
<button class="button-close-fullsidebar">
    <i class="ti ti-x align-middle"></i>
</button>

<div data-simplebar>

    <!--- Sidenav Menu -->
    <ul class="side-nav">

        <li class="side-nav-item">
            <a href="dashboard.php" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-dashboard"></i></span>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>

        <li class="side-nav-title">Management</li>

        <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarTLC" aria-expanded="false" aria-controls="sidebarTLC" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-users"></i></span>
                <span class="menu-text">TLC 2025</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarTLC">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="tlc_2025.php" class="side-nav-link">
                            <span class="menu-text">Users</span>
            </a>
        </li>
        <li class="side-nav-item">
            <a href="tlc_feedback_day_01.php" class="side-nav-link">
                            <span class="menu-text">Feedback Day 01</span>
            </a>
        </li>
        <li class="side-nav-item">
            <a href="tlc_feedback_day_02.php" class="side-nav-link">
                            <span class="menu-text">Feedback Day 02</span>
            </a>
        </li>
        <li class="side-nav-item">
            <a href="tlc_durations.php" class="side-nav-link">
                            <span class="menu-text">Join Durations</span>
            </a>
        </li>
        <li class="side-nav-item">
            <a href="tlc_grant.php" class="side-nav-link">
                            <span class="menu-text">Grace Grant</span>
            </a>
        </li>
        <li class="side-nav-item">
            <a href="tlc_analytics.php" class="side-nav-link">
                            <span class="menu-text">Analytics</span>
            </a>
                    </li>
                </ul>
            </div>
        </li>

        <li class="side-nav-item">
            <a href="generate_school_links.php" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-link"></i></span>
                <span class="menu-text">Generate School Links</span>
            </a>
        </li>

        <!-- Users Management -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarUsers" aria-expanded="false" aria-controls="sidebarUsers" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-users"></i></span>
                <span class="menu-text">Users</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarUsers">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="users.php" class="side-nav-link">
                            <span class="menu-text">All Users</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="admin_view.php" class="side-nav-link">
                            <span class="menu-text">All Admins</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="add_user.php" class="side-nav-link">
                            <span class="menu-text">Add New User</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li> -->

        <!-- Trainers Management -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarTrainers" aria-expanded="false" aria-controls="sidebarTrainers" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-user-star"></i></span>
                <span class="menu-text">Trainers</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarTrainers">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="trainers.php" class="side-nav-link">
                            <span class="menu-text">All Trainers</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="add_trainer.php" class="side-nav-link">
                            <span class="menu-text">Add New Trainer</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <-- Bookings Management -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarBookings" aria-expanded="false" aria-controls="sidebarBookings" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-calendar"></i></span>
                <span class="menu-text">Bookings</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarBookings">
                <ul class="sub-menu">
                <li class="side-nav-item">
                        <a href="admin_booking.php" class="side-nav-link">
                            <span class="menu-text">Create Booking</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="bookings.php" class="side-nav-link">
                            <span class="menu-text">All Bookings</span>
                        </a>
                    </li>
                    
                    <li class="side-nav-item">
                        <a href="calendar.php" class="side-nav-link">
                            <span class="menu-text">Calendar View</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <li class="side-nav-title">Payments & Transactions</li>  -->

        <!-- Coupons -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarCoupons" aria-expanded="false" aria-controls="sidebarCoupons" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-discount"></i></span>
                <span class="menu-text">Coupons</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarCoupons">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="coupons.php" class="side-nav-link">
                            <span class="menu-text">All Coupons</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="coupon_add.php" class="side-nav-link">
                            <span class="menu-text">Add New Coupon</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li> -->

        <!-- Reports -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarTransactions" aria-expanded="false" aria-controls="sidebarTransactions" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-report"></i></span>
                <span class="menu-text">Transactions</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarTransactions">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="transactions.php" class="side-nav-link">
                            <span class="menu-text">All Transactions</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="transactions.php?status=Pending" class="side-nav-link">
                            <span class="menu-text">Pending Payments</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="transactions.php?payment_method=Admin+Scheduled" class="side-nav-link">
                            <span class="menu-text">Admin Payments</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <li class="side-nav-title">Reports & Analytics</li> -->

        <!-- Reports -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarReports" aria-expanded="false" aria-controls="sidebarReports" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-report"></i></span>
                <span class="menu-text">Reports</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarReports">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="reports.php" class="side-nav-link">
                            <span class="menu-text">General Reports</span>
                        </a>
                    </li>

                    <li class="side-nav-item">
                        <a href="admin_finance.php" class="side-nav-link">
                            <span class="menu-text">Financial Reports</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="admin_analytics.php" class="side-nav-link">
                            <span class="menu-text">Analytics Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li> -->

        <!-- Reviews & Ratings -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarReviews" aria-expanded="false" aria-controls="sidebarReviews" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-star"></i></span>
                <span class="menu-text">Reviews</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarReviews">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="reviews.php" class="side-nav-link">
                            <span class="menu-text">All Reviews</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="review_edit.php" class="side-nav-link">
                            <span class="menu-text">Manage Reviews</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <li class="side-nav-title">Content Management</li> -->

        <!-- Blog Management -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarBlogs" aria-expanded="false" aria-controls="sidebarBlogs" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-article"></i></span>
                <span class="menu-text">Blog</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarBlogs">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="blogs.php" class="side-nav-link">
                            <span class="menu-text">All Posts</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="blog_add.php" class="side-nav-link">
                            <span class="menu-text">Add New Post</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="blog_categories.php" class="side-nav-link">
                            <span class="menu-text">Categories</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li> -->

        <!-- Events Management -->
        <!-- <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarEvents" aria-expanded="false" aria-controls="sidebarEvents" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-calendar-event"></i></span>
                <span class="menu-text">Events</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarEvents">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="events.php" class="side-nav-link">
                            <span class="menu-text">All Events</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="event_add.php" class="side-nav-link">
                            <span class="menu-text">Add New Event</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li> -->

        <!-- <li class="side-nav-title">Settings</li> -->

        <!-- Account Settings -->
        <!-- <li class="side-nav-item">
            <a href="profile.php" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-settings"></i></span>
                <span class="menu-text">Settings</span>
            </a>
        </li> -->

        <li class="side-nav-item">
            <a href="workshop_questions.php" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-list-check"></i></span>
                <span class="menu-text">Workshop Questions</span>
            </a>
        </li>



        <li class="side-nav-item">
            <a href="trainer_reports.php" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-report-analytics"></i></span>
                <span class="menu-text">Trainer Reports</span>
            </a>
        </li>

        <li class="side-nav-item">
            <a href="schools_management.php" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-building"></i></span>
                <span class="menu-text">Schools Management</span>
            </a>
        </li>

        <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarInstamojo" aria-expanded="false" aria-controls="sidebarInstamojo" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-credit-card"></i></span>
                <span class="menu-text">Instamojo</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarInstamojo">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="instamojo_dashboard.php" class="side-nav-link">
                            <span class="menu-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="instamojo_links.php" class="side-nav-link">
                            <span class="menu-text">Payment Links</span>
                        </a>
                    </li>
                    <li class="side-nav-item">
                        <a href="instamojo_payments.php" class="side-nav-link">
                            <span class="menu-text">Payment History</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <li class="side-nav-item">
            <a data-bs-toggle="collapse" href="#sidebarInstructions" aria-expanded="false" aria-controls="sidebarInstructions" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-file-text"></i></span>
                <span class="menu-text">Instructions</span>
                <span class="menu-arrow"></span>
            </a>
            <div class="collapse" id="sidebarInstructions">
                <ul class="sub-menu">
                    <li class="side-nav-item">
                        <a href="instructions/MCQ_INSTRUCTIONS.pdf" target="_blank" class="side-nav-link">
                            <span class="menu-text">MCQ Instructions</span>
                        </a>
                    </li>
                    <!-- More instruction items can be added here -->
                </ul>
            </div>
        </li>
        <!-- Logout -->
        <li class="side-nav-item">
            <a href="logout.php" class="side-nav-link">
                <span class="menu-icon"><i class="ti ti-logout"></i></span>
                <span class="menu-text">Logout</span>
            </a>
        </li>
    </ul>

    <div class="clearfix"></div>
</div>
</div>