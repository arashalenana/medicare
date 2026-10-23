<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'config/paths.php';  
require_once 'config/db.php';
require_once 'config/rbac.php';

// Role-based landing page redirect
$role = $_SESSION['role'] ?? 'Guest';
if ($role === 'Receptionist') {
    header("Location: pages/customers.php");
    exit();
} elseif ($role === 'Lab Tech') {
    header("Location: pages/lab_tests.php");
    exit();
} elseif ($role === 'Guest') {
    header("Location: pages/customers.php");
    exit();
}

// Require dashboard permission for index.php
requirePermission('dashboard');

include 'includes/header.php';
include 'includes/sidebar.php';


// Fetch Real Stats
$today_sales = 0;
$today_transactions = 0;
$low_stock_count = 0;
$expiring_soon_count = 0;

try {
    // Today's Sales
    $stmt = $conn->query("SELECT SUM(total_amount) as total, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE()");
    $res = $stmt->fetch();
    $today_sales = $res['total'] ?? 0;
    $today_transactions = $res['count'] ?? 0;

    // Low Stock Count
    $stmt = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity < 10");
    $low_stock_count = $stmt->fetchColumn();

    // Expiring Soon Count (within 30 days)
    $stmt = $conn->query("SELECT COUNT(*) FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $expiring_soon_count = $stmt->fetchColumn();

    // Recent Transactions (join patients table via patient_id)
    $stmt = $conn->query("SELECT s.*, p.first_name, p.last_name FROM sales s LEFT JOIN patients p ON s.patient_id = p.id ORDER BY s.created_at DESC LIMIT 5");
    $recent_sales = $stmt->fetchAll();

    // Sales Trend (Last 7 Days) - ensure we always return 7 labels (fill missing days with 0)
    $dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $dates[] = date('Y-m-d', strtotime("-{$i} days"));
    }

    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $stmt = $conn->prepare("SELECT DATE(created_at) as date, SUM(total_amount) as total FROM sales WHERE DATE(created_at) IN ($placeholders) GROUP BY DATE(created_at)");
    $stmt->execute($dates);
    $trend_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalsByDate = [];
    foreach ($trend_rows as $r) {
        $totalsByDate[$r['date']] = (float)$r['total'];
    }

    $labels = [];
    $values = [];
    foreach ($dates as $d) {
        $labels[] = date('D', strtotime($d));
        $values[] = $totalsByDate[$d] ?? 0.0;
    }
} catch (PDOException $e) {}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
        <a href="pages/sales.php" class="btn btn-sm btn-danger shadow-sm mt-2 mt-sm-0">
            <i class="fas fa-plus fa-sm text-white-50"></i> New Sale
        </a>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Total Sales Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow h-100 py-2 border-start border-danger border-4">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Sales (Today)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800 d-flex align-items-center">
                                <span id="salesAmount" data-value="<?php echo formatKSh($today_sales); ?>" data-visible="false">****</span>
                                <button type="button" id="toggleSalesBtn" class="btn btn-link btn-sm text-gray-500 p-0 ms-2" title="Show/Hide amount" aria-label="Show or hide sales amount">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow h-100 py-2 border-start border-primary border-4">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Transactions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $today_transactions; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow h-100 py-2 border-start border-warning border-4">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Low Stock Items</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $low_stock_count; ?> Items</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow h-100 py-2 border-start border-info border-4">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Expiring Soon</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $expiring_soon_count; ?> Items</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-times fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Sales Trend (Last 7 Days)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 300px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_sales)): ?>
                                    <tr><td colspan="4" class="text-center py-3 text-muted small">No recent sales.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td>#<?php echo $sale['id']; ?></td>
                                        <?php
                                            $isPatient = isset($sale['patient_id']) && !empty($sale['patient_id']);
                                            $patientName = $isPatient ? trim(($sale['first_name'] ?? '') . ' ' . ($sale['last_name'] ?? '')) : 'Walk-in';
                                        ?>
                                        <td><?php echo htmlspecialchars($patientName); ?></td>
                                        <td class="fw-bold"><?php echo formatKSh($sale['total_amount']); ?></td>
                                        <td>
                                            <a href="pages/generate_receipt.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top">
                        <a href="pages/reports_enhanced.php" class="btn btn-sm btn-outline-primary w-100">View All Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($_SESSION['role'] === 'Admin'): ?>
    <?php endif; ?>
</div>

    <script src="<?php echo $asset_path; ?>vendor/chartjs/chart.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Sales (KSh)',
                    data: <?php echo json_encode($values); ?>,
                    backgroundColor: 'rgba(237, 28, 36, 0.1)',
                    borderColor: 'rgba(237, 28, 36, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KSh ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Sales: KSh ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Function to update chart data from API
        async function updateChartFromApi() {
            try {
                const res = await fetch('<?php echo $base_path; ?>pages/api/trend.php');
                if (!res.ok) return;
                const data = await res.json();
                if (data.labels && data.values) {
                    salesChart.data.labels = data.labels;
                    salesChart.data.datasets[0].data = data.values;
                    salesChart.update();
                }
            } catch (e) {
                console.error('Error updating sales chart', e);
            }
        }

        // Listen for sale events from other tabs (localStorage)
        window.addEventListener('storage', function(e) {
            if (e.key === 'sale_completed') {
                updateChartFromApi();
            }
        });

        // Optionally poll once after page load to ensure fresh data
        setTimeout(updateChartFromApi, 2000);
    });

    // Toggle visibility of the Total Sales amount
    document.addEventListener('DOMContentLoaded', function() {
        const salesAmount = document.getElementById('salesAmount');
        const toggleBtn = document.getElementById('toggleSalesBtn');
        if (salesAmount && toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            toggleBtn.addEventListener('click', function() {
                const isVisible = salesAmount.dataset.visible === 'true';
                if (isVisible) {
                    salesAmount.textContent = '****';
                    salesAmount.dataset.visible = 'false';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    salesAmount.textContent = salesAmount.dataset.value;
                    salesAmount.dataset.visible = 'true';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>