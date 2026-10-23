<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../config/paths.php';
if (!isset($_SESSION['user_id'])) { header('Location: ' . $base_path . 'login.php'); exit(); }
require_once '../config/rbac.php';
requireAdminOrCashier('Only Admins and Cashiers can process billing.');

$page_title   = 'Billing';
$current_page = 'billing.php';

// ── Complete Sale from POS ──
$complete_sale_id = isset($_GET['complete_sale']) ? (int)$_GET['complete_sale'] : null;
$pos_sale = null;
$pending_lab_fee = 0;
$pos_patient_id  = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;

if ($complete_sale_id) {
    try {
        $stmt = $conn->prepare("SELECT s.*, CONCAT(p.first_name,' ',p.last_name) as patient_name
                                FROM sales s LEFT JOIN patients p ON s.patient_id = p.id
                                WHERE s.id = ?");
        $stmt->execute([$complete_sale_id]);
        $pos_sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pos_sale) $pos_patient_id = $pos_sale['patient_id'];

        // Fetch pending lab tests total for this patient
        if ($pos_patient_id) {
            $lab_stmt = $conn->prepare(
                "SELECT COALESCE(SUM(test_charge),0) as total
                 FROM lab_tests
                 WHERE patient_id = ? AND status = 'Pending'"
            );
            $lab_stmt->execute([$pos_patient_id]);
            $pending_lab_fee = floatval($lab_stmt->fetchColumn());
        }
    } catch (PDOException $e) {}
}

// ── Handle form submissions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create_bill') {
        $patient_id     = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
        $medication_fee = floatval($_POST['medication_fee'] ?? 0);
        $lab_fee        = floatval($_POST['lab_fee']        ?? 0);
        $drug_sales     = floatval($_POST['drug_sales']     ?? 0);
        $total_amount   = $medication_fee + $lab_fee + $drug_sales;
        $amount_paid    = floatval($_POST['amount_paid']    ?? 0);
        $balance        = max(0, $total_amount - $amount_paid);
        $payment_method = $_POST['payment_method'] ?? 'Cash';

        // Auto-set status based on payment
        if ($amount_paid <= 0) {
            $payment_status = 'Pending';
        } elseif ($balance <= 0) {
            $payment_status = 'Completed';
        } else {
            $payment_status = 'Pending'; // partial payment still pending
        }

        if ($total_amount > 0) {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO sales (patient_id, consultation_fee, medication_fee, lab_fee, drug_sales,
                     total_amount, amount_paid, balance, payment_method, payment_status, user_id)
                     VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $patient_id, $medication_fee, $lab_fee, $drug_sales,
                    $total_amount, $amount_paid, $balance,
                    $payment_method, $payment_status, $_SESSION['user_id']
                ]);
                $success_msg = "Bill created successfully!";
            } catch (PDOException $e) {
                $error_msg = "Error creating bill: " . $e->getMessage();
            }
        } else {
            $error_msg = "Total amount must be greater than 0.";
        }

    } elseif ($_POST['action'] === 'update_pos_sale') {
        // Complete billing for a sale that came from POS
        $sale_id      = (int)($_POST['sale_id'] ?? 0);
        $lab_fee      = floatval($_POST['lab_fee']      ?? 0);
        $drug_sales   = floatval($_POST['drug_sales']   ?? 0);
        $amount_paid  = floatval($_POST['amount_paid']  ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $total_amount = $drug_sales + $lab_fee;
        $balance      = max(0, $total_amount - $amount_paid);
        $payment_status = ($amount_paid >= $total_amount && $total_amount > 0) ? 'Completed' : 'Pending';

        if ($sale_id) {
            try {
                $stmt = $conn->prepare(
                    "UPDATE sales SET lab_fee=?, drug_sales=?, total_amount=?,
                     amount_paid=?, balance=?, payment_method=?, payment_status=?
                     WHERE id=?"
                );
                $stmt->execute([$lab_fee, $drug_sales, $total_amount,
                                $amount_paid, $balance, $payment_method, $payment_status, $sale_id]);
                $success_msg = "Bill completed! " . ($payment_status === 'Completed'
                    ? "Payment cleared."
                    : "Balance of KSh " . number_format($balance, 2) . " remaining.");
                $complete_sale_id = null;
            } catch (PDOException $e) {
                $error_msg = "Error: " . $e->getMessage();
            }
        }

    } elseif ($_POST['action'] === 'update_payment') {
        $sale_id     = (int)($_POST['sale_id'] ?? 0);
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);

        if ($sale_id) {
            try {
                $row = $conn->prepare("SELECT total_amount FROM sales WHERE id = ?");
                $row->execute([$sale_id]);
                $sale  = $row->fetch(PDO::FETCH_ASSOC);
                $total = floatval($sale['total_amount'] ?? 0);
                $balance        = max(0, $total - $amount_paid);
                $payment_status = $balance <= 0 ? 'Completed' : 'Pending';

                $stmt = $conn->prepare(
                    "UPDATE sales SET amount_paid=?, balance=?, payment_status=? WHERE id=?"
                );
                $stmt->execute([$amount_paid, $balance, $payment_status, $sale_id]);
                $success_msg = $payment_status === 'Completed'
                    ? "Payment completed! Bill cleared."
                    : "Payment updated. Balance: KSh " . number_format($balance, 2);
            } catch (PDOException $e) {
                $error_msg = "Error updating payment: " . $e->getMessage();
            }
        }
    }
}

// ── Fetch bills ──
$bills = [];
try {
    $stmt = $conn->query(
        "SELECT s.*,
                COALESCE(CONCAT(p.first_name,' ',p.last_name), 'Walk-in') as patient_name
         FROM sales s
         LEFT JOIN patients p ON s.patient_id = p.id
         ORDER BY s.created_at DESC"
    );
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching bills: " . $e->getMessage();
}

// ── Fetch patients ──
$patients = [];
try {
    $stmt = $conn->query("SELECT id, CONCAT(first_name,' ',last_name) as name FROM patients ORDER BY first_name");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ── Summary stats ──
$total_revenue = $total_pending = $total_completed = $total_balance = 0;
foreach ($bills as $b) {
    $total_revenue += floatval($b['total_amount']);
    if ($b['payment_status'] === 'Completed') {
        $total_completed += floatval($b['total_amount']);
    } else {
        $total_pending += floatval($b['total_amount']);
        $total_balance += floatval($b['balance'] ?? 0);
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid mt-4 mb-5">
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

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="flex-grow-1">
                        <div class="text-muted small">Total Revenue</div>
                        <div class="fw-bold fs-5 text-primary">KSh <?php echo number_format($total_revenue, 2); ?></div>
                    </div>
                    <i class="fas fa-money-bill-wave text-primary opacity-25 fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="flex-grow-1">
                        <div class="text-muted small">Completed</div>
                        <div class="fw-bold fs-5 text-success">KSh <?php echo number_format($total_completed, 2); ?></div>
                    </div>
                    <i class="fas fa-check-circle text-success opacity-25 fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="flex-grow-1">
                        <div class="text-muted small">Pending Bills</div>
                        <div class="fw-bold fs-5 text-warning">KSh <?php echo number_format($total_pending, 2); ?></div>
                    </div>
                    <i class="fas fa-hourglass-half text-warning opacity-25 fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="flex-grow-1">
                        <div class="text-muted small">Outstanding Balance</div>
                        <div class="fw-bold fs-5 text-danger">KSh <?php echo number_format($total_balance, 2); ?></div>
                    </div>
                    <i class="fas fa-credit-card text-danger opacity-25 fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if ($complete_sale_id && $pos_sale): ?>
    <!-- ── Complete Sale from POS Banner ── -->
    <div class="card border-0 shadow mb-4" style="border-left: 5px solid #ED1C24 !important;">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="fas fa-receipt me-2"></i>Complete Billing — Sale #<?php echo $complete_sale_id; ?></h5>
            <span class="badge bg-white text-danger">From POS</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_pos_sale">
                <input type="hidden" name="sale_id" value="<?php echo $complete_sale_id; ?>">

                <?php if ($pos_sale['patient_id']): ?>
                <div class="alert alert-light border mb-3">
                    <i class="fas fa-user text-primary me-2"></i>
                    Patient: <strong><?php echo htmlspecialchars($pos_sale['patient_name'] ?? 'Walk-in'); ?></strong>
                </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Drug Sales (KSh)</label>
                        <input type="number" name="drug_sales" id="pos_drug_sales" class="form-control form-control-lg"
                               step="0.01" value="<?php echo floatval($pos_sale['drug_sales'] ?? $pos_sale['total_amount']); ?>"
                               onchange="calcPosBill()">
                        <div class="form-text text-success"><i class="fas fa-check-circle"></i> Auto-filled from POS</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Lab Fee (KSh)</label>
                        <input type="number" name="lab_fee" id="pos_lab_fee" class="form-control form-control-lg"
                               step="0.01" value="<?php echo $pending_lab_fee; ?>"
                               onchange="calcPosBill()">
                        <?php if ($pending_lab_fee > 0): ?>
                        <div class="form-text text-success"><i class="fas fa-check-circle"></i> Auto-filled from pending lab tests</div>
                        <?php else: ?>
                        <div class="form-text text-muted">Enter lab charges if any</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Amount Paid (KSh)</label>
                        <input type="number" name="amount_paid" id="pos_amount_paid" class="form-control form-control-lg"
                               step="0.01" value="0" min="0" onchange="calcPosBill()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Payment Method</label>
                        <select name="payment_method" class="form-select form-select-lg">
                            <option value="Cash">Cash</option>
                            <option value="M-Pesa">M-Pesa</option>
                            <option value="Card">Card</option>
                            <option value="Insurance">Insurance</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>

                <!-- Live Summary -->
                <div class="card bg-light border-0 mt-3 mb-3">
                    <div class="card-body py-3">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="text-muted small">Total Bill</div>
                                <div class="fw-bold fs-5 text-primary" id="pos_total_display">KSh 0.00</div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">Amount Paid</div>
                                <div class="fw-bold fs-5 text-success" id="pos_paid_display">KSh 0.00</div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">Balance</div>
                                <div class="fw-bold fs-5 text-danger" id="pos_balance_display">KSh 0.00</div>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">Status</div>
                                <div class="fw-bold fs-5" id="pos_status_display">Pending</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger btn-lg px-5">
                        <i class="fas fa-save me-2"></i> Complete Billing
                    </button>
                    <a href="generate_receipt.php?id=<?php echo $complete_sale_id; ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-print me-2"></i> Print Receipt
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
    function calcPosBill() {
        const drugs = parseFloat(document.getElementById('pos_drug_sales').value) || 0;
        const lab   = parseFloat(document.getElementById('pos_lab_fee').value)    || 0;
        const paid  = parseFloat(document.getElementById('pos_amount_paid').value)|| 0;
        const total   = drugs + lab;
        const balance = Math.max(0, total - paid);
        const status  = paid >= total && total > 0 ? 'Completed' : 'Pending';

        document.getElementById('pos_total_display').textContent   = 'KSh ' + total.toFixed(2);
        document.getElementById('pos_paid_display').textContent    = 'KSh ' + paid.toFixed(2);
        document.getElementById('pos_balance_display').textContent = 'KSh ' + balance.toFixed(2);
        const statusEl = document.getElementById('pos_status_display');
        statusEl.textContent  = status;
        statusEl.className    = 'fw-bold fs-5 text-' + (status === 'Completed' ? 'success' : 'warning');
    }
    calcPosBill();
    </script>
    <?php endif; ?>

    <!-- Bills Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Recent Bills</h5>
            <span class="badge bg-secondary"><?php echo count($bills); ?> bills</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Bill ID</th>
                            <th>Patient</th>
                            <th class="text-end">Medication</th>
                            <th class="text-end">Lab</th>
                            <th class="text-end">Drug Sales</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Balance</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr><td colspan="12" class="text-center py-4 text-muted"><i class="fas fa-inbox"></i> No bills found</td></tr>
                        <?php else: ?>
                        <?php foreach ($bills as $b):
                            $balance     = floatval($b['balance']      ?? 0);
                            $amount_paid = floatval($b['amount_paid']  ?? 0);
                            $status      = $b['payment_status'] ?? 'Pending';
                        ?>
                        <tr class="align-middle">
                            <td><span class="badge bg-light text-dark fw-bold">#<?php echo $b['id']; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($b['patient_name']); ?></strong></td>
                            <td class="text-end">KSh <?php echo number_format($b['medication_fee'] ?? 0, 2); ?></td>
                            <td class="text-end">KSh <?php echo number_format($b['lab_fee'] ?? 0, 2); ?></td>
                            <td class="text-end">KSh <?php echo number_format($b['drug_sales'] ?? 0, 2); ?></td>
                            <td class="text-end"><strong class="text-primary">KSh <?php echo number_format($b['total_amount'], 2); ?></strong></td>
                            <td class="text-end text-success">KSh <?php echo number_format($amount_paid, 2); ?></td>
                            <td class="text-end">
                                <?php if ($balance > 0): ?>
                                    <strong class="text-danger">KSh <?php echo number_format($balance, 2); ?></strong>
                                <?php else: ?>
                                    <span class="text-success">—</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($b['payment_method']); ?></small></td>
                            <td>
                                <span class="badge bg-<?php echo $status === 'Completed' ? 'success' : ($status === 'Cancelled' ? 'danger' : 'warning'); ?>">
                                    <i class="fas fa-<?php echo $status === 'Completed' ? 'check-circle' : 'clock'; ?>"></i>
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td><small><?php echo date('M d, Y', strtotime($b['created_at'])); ?></small></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" onclick="printBill(<?php echo $b['id']; ?>)" title="Receipt">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php if ($status !== 'Completed'): ?>
                                    <button class="btn btn-outline-success"
                                        data-bs-toggle="modal" data-bs-target="#paymentModal"
                                        onclick="setPaymentData(<?php echo $b['id']; ?>, <?php echo floatval($b['total_amount']); ?>, <?php echo $amount_paid; ?>)"
                                        title="Record Payment">
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
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice-dollar"></i> Create New Bill</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_bill">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Patient <span class="text-muted">(Optional)</span></label>
                        <select class="form-select form-select-lg" name="patient_id">
                            <option value="">Walk-in / No Patient Selected</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Medication Fee (KSh)</label>
                            <input type="number" class="form-control form-control-lg" name="medication_fee" id="medication_fee" step="0.01" value="0" min="0" onchange="calculateTotal()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Lab Fee (KSh)</label>
                            <input type="number" class="form-control form-control-lg" name="lab_fee" id="lab_fee" step="0.01" value="0" min="0" onchange="calculateTotal()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Drug Sales (KSh)</label>
                            <input type="number" class="form-control form-control-lg" name="drug_sales" id="drug_sales" step="0.01" value="0" min="0" onchange="calculateTotal()">
                        </div>
                    </div>

                    <!-- Total, Amount Paid, Balance -->
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-muted small">Total Bill</div>
                                    <div class="fw-bold fs-5 text-primary" id="bill_total_display">KSh 0.00</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">Amount Paid</div>
                                    <div class="fw-bold fs-5 text-success" id="bill_paid_display">KSh 0.00</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">Balance</div>
                                    <div class="fw-bold fs-5 text-danger" id="bill_balance_display">KSh 0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Amount Paid (KSh)</label>
                            <input type="number" class="form-control form-control-lg" name="amount_paid" id="amount_paid" step="0.01" value="0" min="0" onchange="calculateTotal()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Payment Method</label>
                            <select class="form-select form-select-lg" name="payment_method">
                                <option value="Cash">Cash</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Card">Card</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Insurance">Insurance</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Payment Status</label>
                            <input type="text" class="form-control form-control-lg bg-light" id="status_preview" readonly value="Pending">
                            <div class="form-text">Set automatically based on payment</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Create Bill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-credit-card"></i> Record Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_payment">
                <input type="hidden" id="payment_sale_id" name="sale_id">
                <div class="modal-body">
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-muted small">Total Bill</div>
                                    <div class="fw-bold fs-5 text-primary" id="modal_total">KSh 0.00</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">Previously Paid</div>
                                    <div class="fw-bold fs-5 text-success" id="modal_prev_paid">KSh 0.00</div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">New Balance</div>
                                    <div class="fw-bold fs-5 text-danger" id="modal_new_balance">KSh 0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Total Amount Paid (KSh)</label>
                        <input type="number" class="form-control form-control-lg" name="amount_paid" id="modal_amount_paid"
                               step="0.01" min="0" onchange="updatePaymentPreview()">
                        <div class="form-text">Enter the total amount paid so far (including previous payments)</div>
                    </div>
                    <div class="alert mb-0" id="payment_status_alert">
                        <span id="payment_status_text"></span>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── New Bill: calculate total, balance, auto-status ──
function calculateTotal() {
    const med   = parseFloat(document.getElementById('medication_fee').value) || 0;
    const lab   = parseFloat(document.getElementById('lab_fee').value)        || 0;
    const drugs = parseFloat(document.getElementById('drug_sales').value)     || 0;
    const paid  = parseFloat(document.getElementById('amount_paid').value)    || 0;
    const total   = med + lab + drugs;
    const balance = Math.max(0, total - paid);

    document.getElementById('bill_total_display').textContent   = 'KSh ' + total.toFixed(2);
    document.getElementById('bill_paid_display').textContent    = 'KSh ' + paid.toFixed(2);
    document.getElementById('bill_balance_display').textContent = 'KSh ' + balance.toFixed(2);

    const preview = document.getElementById('status_preview');
    if (total === 0) {
        preview.value = 'Pending';
        preview.className = 'form-control form-control-lg bg-light text-muted';
    } else if (paid >= total) {
        preview.value = 'Completed';
        preview.className = 'form-control form-control-lg bg-light text-success fw-bold';
    } else {
        preview.value = 'Pending';
        preview.className = 'form-control form-control-lg bg-light text-warning fw-bold';
    }
}

// ── Update Payment modal ──
let _modalTotal = 0;
function setPaymentData(saleId, total, prevPaid) {
    _modalTotal = total;
    document.getElementById('payment_sale_id').value  = saleId;
    document.getElementById('modal_amount_paid').value = prevPaid.toFixed(2);
    document.getElementById('modal_total').textContent     = 'KSh ' + total.toFixed(2);
    document.getElementById('modal_prev_paid').textContent = 'KSh ' + prevPaid.toFixed(2);
    updatePaymentPreview();
}

function updatePaymentPreview() {
    const paid    = parseFloat(document.getElementById('modal_amount_paid').value) || 0;
    const balance = Math.max(0, _modalTotal - paid);
    document.getElementById('modal_new_balance').textContent = 'KSh ' + balance.toFixed(2);

    const alert = document.getElementById('payment_status_alert');
    const text  = document.getElementById('payment_status_text');
    if (paid >= _modalTotal) {
        alert.className = 'alert alert-success mb-0';
        text.textContent = '✓ Bill fully paid — status will be set to Completed';
    } else {
        alert.className = 'alert alert-warning mb-0';
        text.textContent = 'Partial payment — balance of KSh ' + balance.toFixed(2) + ' remaining. Status stays Pending.';
    }
}

function printBill(id) {
    window.location.href = 'generate_receipt.php?id=' + id;
}

calculateTotal();
</script>

<?php include '../includes/footer.php'; ?>