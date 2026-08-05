<?php
// Enhanced Billing System Page
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

// Check if user is Admin or Cashier
requireAdminOrCashier('Only Admins and Cashiers can process billing.');

$page_title = 'Billing';
$current_page = 'billing.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_bill') {
            $patient_id = $_POST['patient_id'] ?? null;
            $consultation_fee = floatval($_POST['consultation_fee'] ?? 0);
            $medication_fee = floatval($_POST['medication_fee'] ?? 0);
            $lab_fee = floatval($_POST['lab_fee'] ?? 0);
            $drug_sales = floatval($_POST['drug_sales'] ?? 0);
            $payment_method = $_POST['payment_method'] ?? 'Cash';
            $payment_status = $_POST['payment_status'] ?? 'Pending';

            $total_amount = $consultation_fee + $medication_fee + $lab_fee + $drug_sales;

            if ($total_amount > 0) {
                try {
                    $stmt = $conn->prepare("INSERT INTO sales (patient_id, consultation_fee, medication_fee, lab_fee, drug_sales, total_amount, payment_method, payment_status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$patient_id, $consultation_fee, $medication_fee, $lab_fee, $drug_sales, $total_amount, $payment_method, $payment_status, $_SESSION['user_id']]);
                    $bill_id = $conn->lastInsertId();
                    $success_msg = "Bill created successfully! Bill ID: " . $bill_id;
                } catch (PDOException $e) {
                    $error_msg = "Error creating bill: " . $e->getMessage();
                }
            } else {
                $error_msg = "Total amount must be greater than 0.";
            }
        } elseif ($_POST['action'] === 'update_payment') {
            $sale_id = $_POST['sale_id'] ?? null;
            $payment_status = $_POST['payment_status'] ?? 'Completed';

            if ($sale_id) {
                try {
                    $stmt = $conn->prepare("UPDATE sales SET payment_status = ? WHERE id = ?");
                    $stmt->execute([$payment_status, $sale_id]);
                    $success_msg = "Payment status updated!";
                } catch (PDOException $e) {
                    $error_msg = "Error updating payment: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all bills
$bills = [];
try {
    $stmt = $conn->query("SELECT s.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name FROM sales s LEFT JOIN patients p ON s.patient_id = p.id ORDER BY s.created_at DESC");
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching bills: " . $e->getMessage();
}

// Provide dummy bills for demo if DB empty
if (empty($bills)) {
    $bills = [
        ['id'=>101,'patient_name'=>'John Doe','consultation_fee'=>500.00,'medication_fee'=>200.00,'lab_fee'=>0.00,'drug_sales'=>150.00,'total_amount'=>850.00,'payment_method'=>'Cash','payment_status'=>'Pending','created_at'=>date('Y-m-d H:i:s')],
        ['id'=>102,'patient_name'=>'Jane Smith','consultation_fee'=>300.00,'medication_fee'=>100.00,'lab_fee'=>200.00,'drug_sales'=>0.00,'total_amount'=>600.00,'payment_method'=>'M-Pesa','payment_status'=>'Completed','created_at'=>date('Y-m-d H:i:s', strtotime('-1 day'))]
    ];
}

// Fetch patients for dropdown
$patients = [];
try {
    $stmt = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM patients ORDER BY first_name");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching patients: " . $e->getMessage();
}

if (empty($patients)) {
    $patients = [
        ['id'=>1,'name'=>'John Doe'],
        ['id'=>2,'name'=>'Jane Smith']
    ];
}

// Calculate summary statistics
$total_revenue = 0;
$total_pending = 0;
$total_completed = 0;
$total_bills = count($bills);

foreach ($bills as $bill) {
    $amt = isset($bill['total_amount']) ? floatval($bill['total_amount']) : 0.0;
    $total_revenue += $amt;
    $status = $bill['payment_status'] ?? 'Pending';
    if ($status === 'Pending') {
        $total_pending += $amt;
    } elseif ($status === 'Completed') {
        $total_completed += $amt;
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid mt-4 mb-5">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 fw-bold mb-1">Billing Management</h1>
            <p class="text-muted">Manage patient bills and payment tracking</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#billModal">
                <i class="fas fa-plus-circle"></i> New Bill
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">Total Revenue</h6>
                            <h3 class="card-text fw-bold text-primary mb-0">KSh <?php echo number_format($total_revenue, 2); ?></h3>
                        </div>
                        <div class="text-primary opacity-25" style="font-size: 1.25rem;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">Completed Payments</h6>
                            <h3 class="card-text fw-bold text-success mb-0">KSh <?php echo number_format($total_completed, 2); ?></h3>
                        </div>
                        <div class="text-success opacity-25" style="font-size: 1.25rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">Pending Payments</h6>
                            <h3 class="card-text fw-bold text-warning mb-0">KSh <?php echo number_format($total_pending, 2); ?></h3>
                        </div>
                        <div class="text-warning opacity-25" style="font-size: 1.25rem;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">Total Bills</h6>
                            <h3 class="card-text fw-bold text-info mb-0"><?php echo $total_bills; ?></h3>
                        </div>
                        <div class="text-info opacity-25" style="font-size: 1.25rem;">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0 fw-bold">Recent Bills</h5>
                </div>
                <div class="col-auto">
                    <span class="badge bg-secondary"><?php echo count($bills); ?> bills</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold">Bill ID</th>
                            <th class="fw-bold">Patient Name</th>
                            <th class="fw-bold text-end">Consultation</th>
                            <th class="fw-bold text-end">Medication</th>
                            <th class="fw-bold text-end">Lab</th>
                            <th class="fw-bold text-end">Drug Sales</th>
                            <th class="fw-bold text-end">Total</th>
                            <th class="fw-bold">Method</th>
                            <th class="fw-bold">Status</th>
                            <th class="fw-bold">Date</th>
                            <th class="fw-bold text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox"></i> No bills found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bills as $bill): ?>
                                <tr class="align-middle">
                                    <td>
                                        <span class="badge bg-light text-dark fw-bold">#<?php echo $bill['id']; ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($bill['patient_name'] ?? 'Walk-in'); ?></strong>
                                    </td>
                                    <td class="text-end">KSh <?php echo number_format($bill['consultation_fee'] ?? 0, 2); ?></td>
                                    <td class="text-end">KSh <?php echo number_format($bill['medication_fee'] ?? 0, 2); ?></td>
                                    <td class="text-end">KSh <?php echo number_format($bill['lab_fee'] ?? 0, 2); ?></td>
                                    <td class="text-end">KSh <?php echo number_format($bill['drug_sales'] ?? 0, 2); ?></td>
                                    <td class="text-end">
                                        <strong class="text-primary">KSh <?php echo number_format($bill['total_amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($bill['payment_method']); ?></small>
                                    </td>
                                    <td>
                                        <?php $pstatus = $bill['payment_status'] ?? 'Pending'; ?>
                                        <span class="badge bg-<?php echo $pstatus === 'Completed' ? 'success' : ($pstatus === 'Pending' ? 'warning' : 'danger'); ?>">
                                            <i class="fas fa-<?php echo $pstatus === 'Completed' ? 'check-circle' : 'clock'; ?>"></i> <?php echo htmlspecialchars($pstatus); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, Y', strtotime($bill['created_at'])); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-info" onclick="printBill(<?php echo $bill['id']; ?>)" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <?php if (($bill['payment_status'] ?? 'Pending') === 'Pending'): ?>
                                                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#paymentModal" onclick="setPaymentData(<?php echo $bill['id']; ?>, '<?php echo ($bill['payment_status'] ?? 'Pending'); ?>')" title="Update Payment">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

<!-- New Bill Modal -->
<div class="modal fade" id="billModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-file-invoice-dollar"></i> Create New Bill
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_bill">
                    
                    <div class="mb-3">
                        <label for="patient_id" class="form-label fw-bold">Patient <span class="text-muted">(Optional)</span></label>
                        <select class="form-select form-select-lg" id="patient_id" name="patient_id">
                            <option value="">Select Patient or Leave Blank for Walk-in</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="consultation_fee" class="form-label fw-bold">Consultation Fee (KSh)</label>
                            <input type="number" class="form-control form-control-lg" id="consultation_fee" name="consultation_fee" step="0.01" value="0" onchange="calculateTotal()" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="medication_fee" class="form-label fw-bold">Medication Fee (KSh)</label>
                            <input type="number" class="form-control form-control-lg" id="medication_fee" name="medication_fee" step="0.01" value="0" onchange="calculateTotal()" min="0">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="lab_fee" class="form-label fw-bold">Lab Fee (KSh)</label>
                            <input type="number" class="form-control form-control-lg" id="lab_fee" name="lab_fee" step="0.01" value="0" onchange="calculateTotal()" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="drug_sales" class="form-label fw-bold">Drug Sales (KSh)</label>
                            <input type="number" class="form-control form-control-lg" id="drug_sales" name="drug_sales" step="0.01" value="0" onchange="calculateTotal()" min="0">
                        </div>
                    </div>

                    <div class="alert alert-info mb-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <strong>Total Amount:</strong>
                                <h4 class="mb-0 text-primary" id="total_display_text">KSh 0.00</h4>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label fw-bold">Payment Method</label>
                            <select class="form-select form-select-lg" id="payment_method" name="payment_method">
                                <option value="Cash">Cash</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Card">Card</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Insurance">Insurance</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="payment_status" class="form-label fw-bold">Payment Status</label>
                            <select class="form-select form-select-lg" id="payment_status" name="payment_status">
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Create Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Status Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-credit-card"></i> Update Payment Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_payment">
                    <input type="hidden" id="payment_sale_id" name="sale_id">
                    <div class="mb-3">
                        <label for="payment_status_update" class="form-label fw-bold">Payment Status</label>
                        <select class="form-select form-select-lg" id="payment_status_update" name="payment_status">
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </div>
            </form>
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
    .table tbody tr:hover {
        background-color: rgba(0,0,0,0.02);
    }
</style>

<script>
function calculateTotal() {
    const consultation = parseFloat(document.getElementById('consultation_fee').value) || 0;
    const medication = parseFloat(document.getElementById('medication_fee').value) || 0;
    const lab = parseFloat(document.getElementById('lab_fee').value) || 0;
    const drugs = parseFloat(document.getElementById('drug_sales').value) || 0;
    const total = consultation + medication + lab + drugs;
    document.getElementById('total_display_text').textContent = 'KSh ' + total.toFixed(2);
}

function setPaymentData(saleId, status) {
    document.getElementById('payment_sale_id').value = saleId;
    document.getElementById('payment_status_update').value = status;
}

function printBill(billId) {
    window.location.href = 'generate_receipt.php?id=' + billId;
}

// Initialize total on page load
calculateTotal();
</script>

<?php include '../includes/footer.php'; ?>
