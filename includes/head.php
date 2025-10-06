<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="IPN Academy - Admin Dashboard" name="description" />
    <meta content="Endeavour Digital" name="author" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/tlc_editions/') !== false) ? '../../logo.png' : 'logo.png'; ?>">

    <!-- Theme Config Js -->
    <script src="<?php echo (strpos($_SERVER['REQUEST_URI'], '/tlc_editions/') !== false) ? '../../assets/js/config.js' : 'assets/js/config.js'; ?>"></script>

    <!-- Vendor css -->
    <link href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/tlc_editions/') !== false) ? '../../assets/css/vendor.min.css' : 'assets/css/vendor.min.css'; ?>" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/tlc_editions/') !== false) ? '../../assets/css/app.min.css' : 'assets/css/app.min.css'; ?>" rel="stylesheet" type="text/css" id="app-style" />

    <!-- Icons css -->
    <link href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/tlc_editions/') !== false) ? '../../assets/css/icons.min.css' : 'assets/css/icons.min.css'; ?>" rel="stylesheet" type="text/css" />

    <!-- Custom CSS for logo text -->
    <style>
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #3283f6;
            text-decoration: none;
        }
        .logo-text:hover {
            color: #0e5bb7;
        }
        .logo-text-sm {
            font-size: 1.2rem;
            font-weight: 700;
            color: #3283f6;
            text-decoration: none;
        }
        .logo-text-sm:hover {
            color: #0e5bb7;
        }
        @media (max-width: 767.98px) {
            .logo-text {
                font-size: 1.2rem;
            }
            .logo-text-sm {
                font-size: 1rem;
            }
        }
    </style>