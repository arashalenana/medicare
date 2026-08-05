<?php
// Sidebar Include

// Load centralized path configuration (safe, idempotent)
if (!defined('FAMILYMEDICARE_PATHS_LOADED')) {
    require_once dirname(__FILE__) . '/../config/paths.php';
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role    = isset($_SESSION['role']) ? $_SESSION['role'] : 'Guest';

// Fetch real-time alert counts from database
$low_stock_count = 0;
$expiring_count  = 0;

try {
    // Low stock count (< 10)
    $stmt = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity < 10");
    $low_stock_count = $stmt->fetchColumn();

    // Expiring soon count (within 30 days)
    $stmt = $conn->query("SELECT COUNT(*) FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $expiring_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Fallback silently if database error
}

$total_alerts = $low_stock_count + $expiring_count;
?>
<nav id="sidebar">
    <div class="sidebar-header">
        <img src="<?php echo $asset_path; ?>images/favicon.png" alt="Family Medicare Logo" style="max-width: 300px; height: 80px; display: block; margin: 0 auto; filter: drop-shadow(0 0 10px rgba(0,174,219,0.3));">
        <h5 class="mt-3 text-white text-center" style="font-size: 1.1em; font-weight: bold;">Family Medicare</h5>
        <div class="mt-3 small text-white-50 text-uppercase letter-spacing-1" style="font-size: 0.7em;">Logged in as</div>
        <div class="text-white fw-bold"><?php echo htmlspecialchars($user_role); ?></div>
    </div>

    <ul class="list-unstyled components">
        <?php if (hasPermission('dashboard')): ?>
        <li class="<?php echo ($current_page == 'index.php' || $current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>index.php">
                <span><i class="fas fa-tachometer-alt"></i> Dashboard</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('sales')): ?>
        <li class="<?php echo ($current_page == 'sales.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/sales.php">
                <span><i class="fas fa-shopping-cart"></i> New Sale</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('medicines')): ?>
        <li class="<?php echo ($current_page == 'medicines.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/medicines.php">
                <span><i class="fas fa-pills"></i> Medicines</span>
                <?php if ($total_alerts > 0): ?>
                    <span class="badge badge-notification"><?php echo $total_alerts; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('prescriptions')): ?>
        <li class="<?php echo ($current_page == 'prescriptions_enhanced.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/prescriptions_enhanced.php">
                <span><i class="fas fa-file-prescription"></i> Prescriptions</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('patients')): ?>
        <li class="<?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/customers.php">
                <span><i class="fas fa-users"></i> Patients</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('consultations')): ?>
        <li class="<?php echo ($current_page == 'consultations.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/consultations.php">
                <span><i class="fas fa-stethoscope"></i> Consultations</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('lab_tests')): ?>
        <li class="<?php echo ($current_page == 'lab_tests.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/lab_tests.php">
                <span><i class="fas fa-flask"></i> Lab Tests</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('billing')): ?>
        <li class="<?php echo ($current_page == 'billing.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/billing.php">
                <span><i class="fas fa-receipt"></i> Billing</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('reports')): ?>
        <li class="<?php echo ($current_page == 'reports_enhanced.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/reports_enhanced.php">
                <span><i class="fas fa-chart-bar"></i> Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('users')): ?>
        <li class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
            <a href="<?php echo $base_url; ?>pages/users.php">
                <span><i class="fas fa-user-shield"></i> User Management</span>
            </a>
        </li>
        <?php endif; ?>

    </ul>

    <div class="sidebar-footer">
        <ul class="list-unstyled m-0">
            <li>
                <a href="<?php echo $base_url; ?>logout.php" class="text-danger-emphasis">
                    <span><i class="fas fa-sign-out-alt"></i> Logout</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<div id="content">
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <button type="button" id="sidebarCollapse" class="btn btn-primary">
                <i class="fas fa-bars"></i>
            </button>
            <div class="ms-auto d-flex align-items-center">
                <div class="dropdown me-3">
                    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell fa-lg text-gray-600"></i>
                        <?php if ($total_alerts > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6em;">
                                <?php echo $total_alerts; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow animated--grow-in">
                        <li><h6 class="dropdown-header">Alerts Center</h6></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_path; ?>pages/medicines.php">
                                <div class="me-3">
                                    <div class="icon-circle bg-warning text-white p-2 rounded-circle">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-gray-500">Stock Alert</div>
                                    <span class="font-weight-bold"><?php echo $low_stock_count; ?> items are low in stock!</span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="<?php echo $base_path; ?>pages/medicines.php">
                                <div class="me-3">
                                    <div class="icon-circle bg-danger text-white p-2 rounded-circle">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-gray-500">Expiry Alert</div>
                                    <span class="font-weight-bold"><?php echo $expiring_count; ?> items expiring soon!</span>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle fa-lg"></i> <?php echo htmlspecialchars($user_role); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>