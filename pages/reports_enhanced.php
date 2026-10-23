<?php
// Enhanced Reporting and Analytics Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../config/paths.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_path . 'login.php');
    exit();
}

// Load RBAC
require_once '../config/rbac.php';

// Check if user is Admin (only Admin can view reports)
requireAdmin('Only Admins can view reports.');

$page_title = 'Reports & Analytics';
$current_page = 'reports_enhanced.php';

// Get date range from request
$quick_filter = $_GET['filter'] ?? null;
$has_custom_filter = isset($_GET['start_date']) || isset($_GET['end_date']);

if ($quick_filter === 'today') {
    $start_date = $end_date = date('Y-m-d');
} elseif ($quick_filter === 'week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d');
} elseif ($quick_filter === 'month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
} elseif ($has_custom_filter) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date   = $_GET['end_date']   ?? date('Y-m-d');
} else {
    // Default (Reset / first load): current month
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-d');
    $quick_filter = 'month'; // highlight This Month button as active
}

// Revenue Report
$revenue_data = [];
try {
    $stmt = $conn->prepare("SELECT 
        DATE(created_at) as date,
        SUM(total_amount) as daily_revenue,
        COUNT(*) as transaction_count,
        SUM(consultation_fee) as consultation_revenue,
        SUM(medication_fee) as medication_revenue,
        SUM(lab_fee) as lab_revenue,
        SUM(drug_sales) as drug_revenue
    FROM sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC");
    $stmt->execute([$start_date, $end_date]);
    $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching revenue data: " . $e->getMessage();
}

// No dummy fallback — show real data only

// Sales History (individual transactions, for receipts)
$sales_history = [];
try {
    $stmt = $conn->prepare("SELECT s.*, p.first_name, p.last_name, u.username as staff_name
                            FROM sales s
                            LEFT JOIN patients p ON s.patient_id = p.id
                            JOIN users u ON s.user_id = u.id
                            WHERE DATE(s.created_at) BETWEEN ? AND ? AND u.role IN ('Admin', 'Cashier')
                            ORDER BY s.created_at DESC");
    $stmt->execute([$start_date, $end_date]);
    $sales_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching sales history: " . $e->getMessage();
}

// Patient Statistics
$patient_stats = [];
try {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_patients,
        (SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) BETWEEN ? AND ?) as consultations,
        (SELECT COUNT(*) FROM lab_tests WHERE DATE(test_date) BETWEEN ? AND ?) as lab_tests,
        (SELECT COUNT(*) FROM prescriptions WHERE DATE(prescription_date) BETWEEN ? AND ?) as prescriptions
    FROM patients");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    $patient_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching patient stats: " . $e->getMessage();
}

if (empty($patient_stats)) {
    $patient_stats = ['total_patients'=>0,'consultations'=>0,'lab_tests'=>0,'prescriptions'=>0];
}

// Billing Statistics
$billing_stats = [];
try {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_bills,
        SUM(total_amount) as total_billed,
        COUNT(CASE WHEN payment_status = 'Completed' THEN 1 END) as completed_bills,
        COUNT(CASE WHEN payment_status = 'Pending' THEN 1 END) as pending_bills
    FROM sales
    WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $billing_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching billing stats: " . $e->getMessage();
}

if (empty($billing_stats) || $billing_stats['total_bills'] === null) {
    $billing_stats = ['total_bills' => 0, 'total_billed' => 0.00, 'completed_bills' => 0, 'pending_bills' => 0];
} else {
    $billing_stats['total_billed'] = (float)($billing_stats['total_billed'] ?? 0);
    $billing_stats['total_bills'] = (int)($billing_stats['total_bills'] ?? 0);
    $billing_stats['completed_bills'] = (int)($billing_stats['completed_bills'] ?? 0);
    $billing_stats['pending_bills'] = (int)($billing_stats['pending_bills'] ?? 0);
}

// Inventory Report
$inventory_data = [];
try {
    $stmt = $conn->query("SELECT 
        name,
        quantity,
        selling_price,
        (quantity * selling_price) as total_value,
        expiry_date,
        CASE 
            WHEN quantity < 10 THEN 'Low Stock'
            WHEN expiry_date < CURDATE() THEN 'Expired'
            WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
            ELSE 'OK'
        END as status
    FROM medicines
    ORDER BY quantity ASC");
    $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching inventory data: " . $e->getMessage();
}

// No dummy fallback — show real inventory only

// Consultation Report
$consultation_data = [];
try {
    $stmt = $conn->prepare("SELECT 
        c.doctor_name,
        COUNT(*) as consultation_count,
        COUNT(CASE WHEN c.status = 'Completed' THEN 1 END) as completed,
        COUNT(CASE WHEN c.status = 'Pending' THEN 1 END) as pending
    FROM consultations c
    WHERE DATE(c.consultation_date) BETWEEN ? AND ?
    GROUP BY c.doctor_name
    ORDER BY consultation_count DESC");
    $stmt->execute([$start_date, $end_date]);
    $consultation_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching consultation data: " . $e->getMessage();
}

// No dummy fallback — show real consultation data only

// Lab Tests Report
$lab_data = [];
try {
    $stmt = $conn->prepare("SELECT 
        test_category,
        COUNT(*) as total_tests,
        COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
        SUM(test_charge) as total_revenue
    FROM lab_tests
    WHERE DATE(test_date) BETWEEN ? AND ?
    GROUP BY test_category
    ORDER BY total_tests DESC");
    $stmt->execute([$start_date, $end_date]);
    $lab_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching lab data: " . $e->getMessage();
}

if (empty($lab_data)) {
    $lab_data = [];
}

// Calculate totals
$total_revenue = 0;
$total_consultations = 0;
$total_lab_tests = 0;
$total_transactions = 0;

foreach ($revenue_data as $row) {
    $total_revenue += $row['daily_revenue'];
    $total_transactions += $row['transaction_count'];
}

if ($patient_stats) {
    $total_consultations = $patient_stats['consultations'];
    $total_lab_tests = $patient_stats['lab_tests'];
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid mt-4 mb-5">
    <!-- Print-only Letterhead -->
    <div class="print-letterhead d-none">
        <div class="print-header-inner">
            <div class="print-logo-block">
                <img src="<?php echo $asset_path; ?>images/favicon.png" alt="Family Medicare Logo">
            </div>
            <div class="print-clinic-block">
                <h1>Family Medicare Clinic &amp; Laboratory</h1>
                <p class="subtitle">Quality Healthcare Services &mdash; Nairobi, Kenya</p>
                <p class="contact">Tel: +254 700 000 000 &nbsp;|&nbsp; Email: fammedicare810@gmail.com</p>
            </div>
        </div>
        <div class="print-report-title-bar">
            <h2>Reports &amp; Analytics</h2>
        </div>
        <div class="print-meta">
            <div class="print-meta-item"><strong>Period:</strong> <?php echo date('M d, Y', strtotime($start_date)); ?> &ndash; <?php echo date('M d, Y', strtotime($end_date)); ?></div>
            <div class="print-meta-item"><strong>Generated:</strong> <?php echo date('d/m/Y H:i'); ?></div>
            <div class="print-meta-item"><strong>Prepared by:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
            <div class="print-meta-item"><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></div>
        </div>
    </div>

    <!-- Print-only footer (appended via CSS counter) -->
    <div class="print-page-footer d-none">
        <div class="print-footer-line"></div>
        <div class="print-footer-inner">
            <span>Family Medicare Clinic &amp; Laboratory &mdash; Confidential Internal Report</span>
            <span>Printed: <?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>

    <!-- Page Header -->
    <div class="row mb-4 print-hide-header">
        <div class="col-md-8">
            <h1 class="h2 fw-bold mb-1">Reports & Analytics</h1>
            <p class="text-muted">Comprehensive business intelligence and performance metrics</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-outline-secondary" onclick="printReport()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card border-0 shadow-sm mb-4 no-print-section">
        <div class="card-body">
            <form method="GET" action="reports_enhanced.php" id="dateFilterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="start_date" class="form-label fw-bold">Start Date</label>
                    <input type="date" class="form-control form-control-lg" id="start_date" name="start_date"
                           value="<?php echo ($quick_filter || !$has_custom_filter) ? '' : htmlspecialchars($start_date); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label fw-bold">End Date</label>
                    <input type="date" class="form-control form-control-lg" id="end_date" name="end_date"
                           value="<?php echo ($quick_filter || !$has_custom_filter) ? '' : htmlspecialchars($end_date); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 d-flex gap-2 flex-wrap">
                    <button type="submit" id="filterBtn" class="btn btn-primary btn-lg">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-lg" id="resetBtn">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Filters & PDF Reports -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 no-print-section">
        <div class="btn-group shadow-sm mb-2">
            <a href="reports_enhanced.php?filter=today" class="btn btn-sm <?php echo $quick_filter === 'today' ? 'btn-danger' : 'btn-outline-danger'; ?>">Today</a>
            <a href="reports_enhanced.php?filter=week"  class="btn btn-sm <?php echo $quick_filter === 'week'  ? 'btn-danger' : 'btn-outline-danger'; ?>">This Week</a>
            <a href="reports_enhanced.php?filter=month" class="btn btn-sm <?php echo $quick_filter === 'month' ? 'btn-danger' : 'btn-outline-danger'; ?>">This Month</a>
        </div>
    </div>

    <div class="row mb-4 no-print-section">
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm border-start border-danger border-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-danger text-uppercase mb-1">Sales Report</h6>
                    <div class="h5 mb-3 fw-bold text-gray-800">KSh <?php echo number_format($total_revenue, 2); ?></div>
                    <a href="generate_report.php?type=sales&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-sm btn-danger">
                        <i class="fas fa-file-pdf me-1"></i> Generate PDF
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-warning text-uppercase mb-1">Low Stock</h6>
                    <div class="h5 mb-3 fw-bold text-gray-800"><?php echo count(array_filter($inventory_data, fn($i) => $i['status'] === 'Low Stock')); ?> Items Alerted</div>
                    <a href="generate_report.php?type=low_stock" class="btn btn-sm btn-warning">
                        <i class="fas fa-file-pdf me-1"></i> Generate PDF
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm border-start border-info border-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-info text-uppercase mb-1">Expiry Report</h6>
                    <div class="h5 mb-3 fw-bold text-gray-800"><?php echo count(array_filter($inventory_data, fn($i) => $i['status'] === 'Expiring Soon' || $i['status'] === 'Expired')); ?> Items Expiring</div>
                    <a href="generate_report.php?type=expiry" class="btn btn-sm btn-info">
                        <i class="fas fa-file-pdf me-1"></i> Generate PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales History -->
    <div class="card border-0 shadow-sm mb-4 no-print-section">
        <div class="card-header bg-white border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
            <h5 class="mb-0 fw-bold"><i class="fas fa-receipt text-danger"></i> Sales History</h5>
            <span class="text-muted small"><?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th class="text-end">Amount</th>
                            <th>Method</th>
                            <th>Staff</th>
                            <th>Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales_history)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No sales found for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sales_history as $sale): ?>
                            <tr class="align-middle">
                                <td>#S-<?php echo str_pad($sale['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo $sale['patient_id'] ? htmlspecialchars(trim(($sale['first_name'] ?? '') . ' ' . ($sale['last_name'] ?? ''))) : '<span class="text-muted">Walk-in</span>'; ?></td>
                                <td class="text-end fw-bold">KSh <?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($sale['payment_method']); ?></span></td>
                                <td><?php echo htmlspecialchars($sale['staff_name']); ?></td>
                                <td><?php echo date('H:i', strtotime($sale['created_at'])); ?></td>
                                <td>
                                    <a href="generate_receipt.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-print"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics Cards -->
    <div class="row mb-4">
        <!-- Revenue Card -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100 card-revenue">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">Total Revenue</h6>
                            <h3 class="card-text fw-bold text-primary mb-0">KSh <?php echo number_format($total_revenue, 2); ?></h3>
                        </div>
                        <div class="text-primary opacity-25" style="font-size: 2.5rem;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Card -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100 card-transactions">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">Transactions</h6>
                            <h3 class="card-text fw-bold text-info mb-0"><?php echo $total_transactions; ?></h3>
                        </div>
                        <div class="text-info opacity-25" style="font-size: 2.5rem;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultations Card -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100 card-consultations">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">Consultations</h6>
                            <h3 class="card-text fw-bold text-success mb-0"><?php echo $total_consultations; ?></h3>
                        </div>
                        <div class="text-success opacity-25" style="font-size: 2.5rem;">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lab Tests Card -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100 card-lab">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">Lab Tests</h6>
                            <h3 class="card-text fw-bold text-warning mb-0"><?php echo $total_lab_tests; ?></h3>
                        </div>
                        <div class="text-warning opacity-25" style="font-size: 2.5rem;">
                            <i class="fas fa-flask"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Statistics Row -->
    <div class="row mb-4">
        <!-- Billing Card -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100 card-billing">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Billing Summary</h6>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Bills:</span>
                            <strong><?php echo $billing_stats['total_bills']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Billed:</span>
                            <strong class="text-primary">KSh <?php echo number_format($billing_stats['total_billed'], 2); ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-success"><i class="fas fa-check-circle"></i> Completed:</span>
                            <strong><?php echo $billing_stats['completed_bills']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-warning"><i class="fas fa-clock"></i> Pending:</span>
                            <strong><?php echo $billing_stats['pending_bills']; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultations Summary Card -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100 card-consultations">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Consultation Summary</h6>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Patients:</span>
                            <strong><?php echo $patient_stats['total_patients']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Consultations:</span>
                            <strong class="text-success"><?php echo $patient_stats['consultations']; ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Prescriptions:</span>
                            <strong><?php echo $patient_stats['prescriptions']; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lab Tests Summary Card -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100 card-lab">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-3">Lab Tests Summary</h6>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Tests:</span>
                            <strong><?php echo $total_lab_tests; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Categories:</span>
                            <strong><?php echo count($lab_data); ?></strong>
                        </div>
                        <hr>
                        <?php $total_lab_revenue = 0; foreach ($lab_data as $lab) { $total_lab_revenue += $lab['total_revenue']; } ?>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Lab Revenue:</span>
                            <strong class="text-warning">KSh <?php echo number_format($total_lab_revenue, 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Report -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-chart-line text-primary"></i> Daily Revenue Report
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold">Date</th>
                            <th class="fw-bold text-center">Transactions</th>
                            <th class="fw-bold text-end">Consultation</th>
                            <th class="fw-bold text-end">Medication</th>
                            <th class="fw-bold text-end">Lab</th>
                            <th class="fw-bold text-end">Drug Sales</th>
                            <th class="fw-bold text-end">Total (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($revenue_data)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No revenue data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($revenue_data as $row): ?>
                                <tr class="align-middle">
                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                    <td class="text-center"><span class="badge bg-info"><?php echo $row['transaction_count']; ?></span></td>
                                    <td class="text-end">KSh <?php echo number_format($row['consultation_revenue'], 2); ?></td>
                                    <td class="text-end">KSh <?php echo number_format($row['medication_revenue'], 2); ?></td>
                                    <td class="text-end">KSh <?php echo number_format($row['lab_revenue'], 2); ?></td>
                                    <td class="text-end">KSh <?php echo number_format($row['drug_revenue'], 2); ?></td>
                                    <td class="text-end"><strong class="text-primary">KSh <?php echo number_format($row['daily_revenue'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Consultation Report -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-stethoscope text-success"></i> Consultation Report by Doctor
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold">Doctor</th>
                            <th class="fw-bold text-center">Total Consultations</th>
                            <th class="fw-bold text-center">Completed</th>
                            <th class="fw-bold text-center">Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($consultation_data)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No consultation data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($consultation_data as $row): ?>
                                <tr class="align-middle">
                                    <td><strong><?php echo htmlspecialchars($row['doctor_name']); ?></strong></td>
                                    <td class="text-center"><span class="badge bg-primary"><?php echo $row['consultation_count']; ?></span></td>
                                    <td class="text-center"><span class="badge bg-success"><i class="fas fa-check"></i> <?php echo $row['completed']; ?></span></td>
                                    <td class="text-center"><span class="badge bg-warning"><i class="fas fa-clock"></i> <?php echo $row['pending']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Lab Tests Report -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-flask text-warning"></i> Lab Tests Report by Category
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold">Category</th>
                            <th class="fw-bold text-center">Total Tests</th>
                            <th class="fw-bold text-center">Completed</th>
                            <th class="fw-bold text-center">Pending</th>
                            <th class="fw-bold text-end">Revenue (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lab_data)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No lab test data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lab_data as $row): ?>
                                <tr class="align-middle">
                                    <td><strong><?php echo htmlspecialchars($row['test_category']); ?></strong></td>
                                    <td class="text-center"><span class="badge bg-primary"><?php echo $row['total_tests']; ?></span></td>
                                    <td class="text-center"><span class="badge bg-success"><i class="fas fa-check"></i> <?php echo $row['completed']; ?></span></td>
                                    <td class="text-center"><span class="badge bg-warning"><i class="fas fa-clock"></i> <?php echo $row['pending']; ?></span></td>
                                    <td class="text-end"><strong class="text-warning">KSh <?php echo number_format($row['total_revenue'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Inventory Report -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-boxes text-danger"></i> Inventory Report
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold">Medicine</th>
                            <th class="fw-bold text-center">Quantity</th>
                            <th class="fw-bold text-end">Unit Price</th>
                            <th class="fw-bold text-end">Total Value</th>
                            <th class="fw-bold">Expiry Date</th>
                            <th class="fw-bold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory_data)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No inventory data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inventory_data as $row): ?>
                                <tr class="align-middle <?php echo $row['status'] !== 'OK' ? 'table-warning' : ''; ?>">
                                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                    <td class="text-center"><?php echo $row['quantity']; ?></td>
                                    <td class="text-end">KSh <?php echo number_format($row['selling_price'], 2); ?></td>
                                    <td class="text-end">KSh <?php echo number_format($row['total_value'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['expiry_date'])); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $row['status'] === 'OK' ? 'success' : ($row['status'] === 'Low Stock' ? 'warning' : 'danger'); ?>">
                                            <i class="fas fa-<?php echo $row['status'] === 'OK' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    }
    .card-revenue {
        border-left: 4px solid #0d6efd;
    }
    .card-transactions {
        border-left: 4px solid #0dcaf0;
    }
    .card-consultations {
        border-left: 4px solid #198754;
    }
    .card-lab {
        border-left: 4px solid #ffc107;
    }
    .card-billing {
        border-left: 4px solid #fd7e14;
    }
    .table tbody tr:hover {
        background-color: rgba(0,0,0,0.02);
    }
    @media print {
        /* ── Hide screen-only chrome ── */
        .btn, .card-body .btn-group,
        .no-print-section,
        .print-hide-header,
        #sidebar, .navbar, #sidebarCollapse,
        .card-header .btn, .card-header form,
        #chatbot-container, #msg-widget {
            display: none !important;
        }

        /* ── Layout ── */
        #content, #content.active {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        body { background: #fff !important; font-size: 12px; color: #111; }
        .container-fluid { padding: 8px 12px !important; margin: 0 !important; }

        /* ── Letterhead ── */
        .print-letterhead {
            display: block !important;
            margin-bottom: 18px;
        }
        .print-header-inner {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 3px solid #ED1C24;
            padding-bottom: 12px;
            margin-bottom: 10px;
        }
        .print-logo-block img {
            width: 64px;
            height: 64px;
        }
        .print-clinic-block h1 {
            font-size: 17px;
            font-weight: 800;
            color: #00AEDB;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0 0 2px;
        }
        .print-clinic-block .subtitle {
            font-size: 11px;
            color: #555;
            margin: 0;
        }
        .print-clinic-block .contact {
            font-size: 10px;
            color: #777;
            margin: 2px 0 0;
        }
        .print-report-title-bar {
            background: #ED1C24;
            color: #fff;
            padding: 5px 12px;
            margin-bottom: 8px;
        }
        .print-report-title-bar h2 {
            font-size: 13px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .print-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            font-size: 10.5px;
            color: #444;
            padding: 4px 2px 10px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 14px;
        }
        .print-meta-item strong { color: #111; }

        /* ── Footer ── */
        .print-page-footer {
            display: block !important;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
        }
        .print-footer-line {
            border-top: 2px solid #ED1C24;
            margin-bottom: 4px;
        }
        .print-footer-inner {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #888;
            padding: 0 12px 4px;
        }

        /* ── Cards & Tables ── */
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            page-break-inside: avoid;
            margin-bottom: 12px !important;
        }
        .card:hover { transform: none !important; }
        .card-header {
            background: #f8f9fa !important;
            padding: 6px 12px !important;
        }
        .card-header h5, .card-header h6 {
            font-size: 11px !important;
        }
        table { font-size: 10.5px; }
        th { font-size: 10px; padding: 5px 4px !important; }
        td { padding: 4px !important; }
        .badge { font-size: 9px !important; }

        /* ── Summary stat cards: show inline in a 4-col grid ── */
        .row.mb-4 { display: flex !important; flex-wrap: wrap !important; }
        .col-md-3, .col-md-4 { width: 25% !important; float: left; }
        .card-revenue { border-left: 4px solid #0d6efd !important; }
        .card-transactions { border-left: 4px solid #0dcaf0 !important; }
        .card-consultations { border-left: 4px solid #198754 !important; }
        .card-lab { border-left: 4px solid #ffc107 !important; }
        .card-billing { border-left: 4px solid #fd7e14 !important; }
    }
</style>

<script>
function printReport() {
    window.print();
}

document.addEventListener('DOMContentLoaded', function () {
    // ── Reset button: clear inputs and go to default (current month) ──
    document.getElementById('resetBtn').addEventListener('click', function () {
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value   = '';
        window.location.href = 'reports_enhanced.php';
    });

    // ── Filter button: validate before submitting ──
    document.getElementById('dateFilterForm').addEventListener('submit', function (e) {
        var start = document.getElementById('start_date').value;
        var end   = document.getElementById('end_date').value;

        if (!start && !end) {
            e.preventDefault();
            window.location.href = 'reports_enhanced.php';
            return;
        }

        if (start && !end) {
            document.getElementById('end_date').value = '<?php echo date('Y-m-d'); ?>';
        }

        if (!start && end) {
            document.getElementById('start_date').value = end;
        }

        if (start && end && start > end) {
            e.preventDefault();
            alert('Start date cannot be after end date.');
            return;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>