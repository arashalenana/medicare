<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/paths.php';
require_once '../config/db.php';
require_once '../config/rbac.php';

// Check permission
requirePermission('patient_history');

include '../includes/header.php';
include '../includes/sidebar.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient = null;

if ($patient_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();
    } catch (PDOException $e) {}
}

// Fetch all clinical history
$consultations = [];
$prescriptions = [];
$lab_tests = [];

if ($patient) {
    try {
        // Consultations/Diagnosis
        $stmt = $conn->prepare("SELECT * FROM consultations WHERE patient_id = ? ORDER BY created_at DESC");
        $stmt->execute([$patient_id]);
        $consultations = $stmt->fetchAll();

        // Prescriptions (doctor_name is stored directly on the prescriptions table, no join needed)
        $stmt = $conn->prepare("SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC");
        $stmt->execute([$patient_id]);
        $prescriptions = $stmt->fetchAll();

        // Lab Tests (lab_test_results uses result_unit / result_status column names)
        $stmt = $conn->prepare("SELECT lt.*, ltr.result_value, ltr.result_unit, ltr.reference_range, ltr.result_status 
                                FROM lab_tests lt 
                                LEFT JOIN lab_test_results ltr ON lt.id = ltr.lab_test_id 
                                WHERE lt.patient_id = ? ORDER BY lt.created_at DESC");
        $stmt->execute([$patient_id]);
        $lab_tests = $stmt->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Patient Clinical History</h1>
        <a href="customers.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Patients
        </a>
    </div>

    <?php if (!$patient): ?>
        <div class="alert alert-info">Please select a patient from the <a href="customers.php">Patients list</a> to view their history.</div>
    <?php else: ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-primary text-white">
                <h6 class="m-0 font-weight-bold">Patient Details: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Patient ID:</strong> #C-<?php echo str_pad($patient['id'], 3, '0', STR_PAD_LEFT); ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Joined:</strong> <?php echo date('Y-m-d', strtotime($patient['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultations & Diagnosis -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex align-items-center">
                <i class="fas fa-stethoscope me-2 text-primary"></i>
                <h6 class="m-0 font-weight-bold text-primary">Diagnosis & Consultations</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Reason for Visit</th>
                                <th>Diagnosis</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($consultations)): ?>
                                <tr><td colspan="5" class="text-center">No consultation records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($consultations as $c): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($c['created_at'])); ?></td>
                                    <td>Dr. <?php echo htmlspecialchars($c['doctor_name'] ?? ''); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($c['reason'] ?? '')); ?></td>
                                    <td class="fw-bold"><?php echo nl2br(htmlspecialchars($c['diagnosis'] ?? '')); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($c['status'] == 'Completed') ? 'success' : (($c['status'] == 'Cancelled') ? 'secondary' : 'warning'); ?>">
                                            <?php echo htmlspecialchars($c['status'] ?? 'Pending'); ?>
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

        <!-- Lab History -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex align-items-center">
                <i class="fas fa-flask me-2 text-success"></i>
                <h6 class="m-0 font-weight-bold text-success">Laboratory Reports</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Test Name</th>
                                <th>Result</th>
                                <th>Range</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lab_tests)): ?>
                                <tr><td colspan="5" class="text-center">No laboratory records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lab_tests as $lt): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($lt['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($lt['test_name'] ?? ''); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars(trim(($lt['result_value'] ?? '') . ' ' . ($lt['result_unit'] ?? ''))); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($lt['reference_range'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($lt['status'] == 'Completed') ? 'success' : 'warning'; ?>">
                                            <?php echo htmlspecialchars($lt['result_status'] ?? $lt['status'] ?? 'Pending'); ?>
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

        <!-- Prescription History -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex align-items-center">
                <i class="fas fa-file-prescription me-2 text-info"></i>
                <h6 class="m-0 font-weight-bold text-info">Prescription History</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Notes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($prescriptions)): ?>
                                <tr><td colspan="4" class="text-center">No prescription records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($prescriptions as $p): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($p['created_at'])); ?></td>
                                    <td>Dr. <?php echo htmlspecialchars($p['doctor_name'] ?? ''); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($p['notes'] ?? '')); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($p['status'] == 'Active') ? 'primary' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($p['status'] ?? ''); ?>
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
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>