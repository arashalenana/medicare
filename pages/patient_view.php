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
requirePermission('patients');

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
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Patient Details</h1>
        <a href="customers.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Patients
        </a>
    </div>

    <?php if (!$patient): ?>
        <div class="alert alert-info">Patient not found.</div>
    <?php else: ?>
        <div class="row">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card shadow h-100 py-2 border-start border-primary border-4">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Full Name</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card shadow h-100 py-2 border-start border-success border-4">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Phone Number</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($patient['phone']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-phone fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card shadow h-100 py-2 border-start border-info border-4">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Joined Date</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo date('Y-m-d', strtotime($patient['created_at'])); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Additional Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 200px;">Patient ID</th>
                        <td>#C-<?php echo str_pad($patient['id'], 3, '0', STR_PAD_LEFT); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>ID Number</th>
                        <td><?php echo htmlspecialchars($patient['id_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Gender</th>
                        <td><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Date of Birth</th>
                        <td><?php echo htmlspecialchars($patient['date_of_birth'] ?? 'N/A'); ?></td>
                    </tr>
                </table>
                
                <div class="mt-4">
                    <?php if (hasPermission('patient_history')): ?>
                    <a href="patient_history.php?id=<?php echo $patient['id']; ?>" class="btn btn-info">
                        <i class="fas fa-history"></i> View Full Clinical History
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('billing_create')): ?>
                    <a href="billing.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                        <i class="fas fa-receipt"></i> Create Bill
                    </a>
                    <?php endif; ?>

                    <?php if (hasPermission('lab_test_create')): ?>
                    <a href="lab_tests.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-flask"></i> Request Lab Test
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
