<?php
// Lab Tests Management Page
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

// Check if user is Admin or Lab Tech
requirePermission('lab_tests');

$page_title = 'Lab Tests';
$current_page = 'lab_tests.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $patient_id = $_POST['patient_id'] ?? null;
            $test_name = $_POST['test_name'] ?? '';
            $test_category = $_POST['test_category'] ?? '';
            $test_date = $_POST['test_date'] ?? '';
            $test_charge = $_POST['test_charge'] ?? 0;

            if ($patient_id && $test_name && $test_date && $test_charge) {
                try {
                    $stmt = $conn->prepare("INSERT INTO lab_tests (patient_id, test_name, test_category, test_date, test_charge, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                    $stmt->execute([$patient_id, $test_name, $test_category, $test_date, $test_charge]);
                    $success_msg = "Lab test created successfully!";
                } catch (PDOException $e) {
                    $error_msg = "Error creating lab test: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        } elseif ($_POST['action'] === 'add_result') {
            $lab_test_id = $_POST['lab_test_id'] ?? null;
            $result_value = $_POST['result_value'] ?? '';
            $result_unit = $_POST['result_unit'] ?? '';
            $reference_range = $_POST['reference_range'] ?? '';
            $result_status = $_POST['result_status'] ?? 'Normal';

            if ($lab_test_id && $result_value) {
                try {
                    $stmt = $conn->prepare("INSERT INTO lab_test_results (lab_test_id, result_value, result_unit, reference_range, result_status, result_date) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$lab_test_id, $result_value, $result_unit, $reference_range, $result_status]);
                    
                    // Update lab test status to Completed
                    $stmt = $conn->prepare("UPDATE lab_tests SET status = 'Completed' WHERE id = ?");
                    $stmt->execute([$lab_test_id]);
                    
                    $success_msg = "Lab test result added successfully!";
                } catch (PDOException $e) {
                    $error_msg = "Error adding result: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        }
    }
}

// Fetch lab tests with patient info
$lab_tests = [];
try {
    $stmt = $conn->query("SELECT lt.*, p.first_name, p.last_name FROM lab_tests lt JOIN patients p ON lt.patient_id = p.id ORDER BY lt.created_at DESC");
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching lab tests: " . $e->getMessage();
}

// Dummy lab tests for demo/testing when DB empty
if (empty($lab_tests)) {
    $lab_tests = [
        ['id'=>1,'patient_id'=>1,'first_name'=>'John','last_name'=>'Kamau','test_name'=>'Complete Blood Count','test_category'=>'Hematology','test_date'=>date('Y-m-d H:i:s'),'test_charge'=>800.00,'status'=>'Pending'],
        ['id'=>2,'patient_id'=>2,'first_name'=>'Jane','last_name'=>'Wangari','test_name'=>'Liver Function','test_category'=>'Biochemistry','test_date'=>date('Y-m-d H:i:s', strtotime('-2 days')),'test_charge'=>1200.00,'status'=>'Completed']
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
        ['id'=>1,'name'=>'John Kamau'],
        ['id'=>2,'name'=>'Jane Wangari']
    ];
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">Lab Tests Management</h1>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#labTestModal">
                <i class="fas fa-plus"></i> New Lab Test
            </button>
        </div>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Test Name</th>
                            <th>Category</th>
                            <th>Test Date</th>
                            <th>Charge (KSh)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($test['first_name'] . ' ' . $test['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                <td><?php echo htmlspecialchars($test['test_category']); ?></td>
                                <td><?php echo $test['test_date'] ? date('M d, Y H:i', strtotime($test['test_date'])) : 'N/A'; ?></td>
                                <td><?php echo number_format($test['test_charge'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $test['status'] === 'Completed' ? 'success' : ($test['status'] === 'Cancelled' ? 'danger' : 'warning'); ?>">
                                        <?php echo $test['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($test['status'] === 'Pending'): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#resultModal" onclick="setTestId(<?php echo $test['id']; ?>)">
                                            <i class="fas fa-check"></i> Add Result
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewResultModal" onclick="viewResult(<?php echo $test['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Lab Test Modal -->
<div class="modal fade" id="labTestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Lab Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label for="patient_id" class="form-label">Patient *</label>
                        <select class="form-control" id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="test_name" class="form-label">Test Name *</label>
                        <input type="text" class="form-control" id="test_name" name="test_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="test_category" class="form-label">Test Category</label>
                        <select class="form-control" id="test_category" name="test_category">
                            <option value="">Select Category</option>
                            <option value="Hematology">Hematology</option>
                            <option value="Biochemistry">Biochemistry</option>
                            <option value="Microbiology">Microbiology</option>
                            <option value="Immunology">Immunology</option>
                            <option value="Cardiology">Cardiology</option>
                            <option value="Vital Signs">Vital Signs</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="test_date" class="form-label">Test Date & Time *</label>
                        <input type="datetime-local" class="form-control" id="test_date" name="test_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="test_charge" class="form-label">Test Charge (KSh) *</label>
                        <input type="number" class="form-control" id="test_charge" name="test_charge" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Lab Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Result Modal -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Lab Test Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_result">
                    <input type="hidden" id="lab_test_id" name="lab_test_id">
                    <div class="mb-3">
                        <label for="result_value" class="form-label">Result Value *</label>
                        <input type="text" class="form-control" id="result_value" name="result_value" required>
                    </div>
                    <div class="mb-3">
                        <label for="result_unit" class="form-label">Unit</label>
                        <input type="text" class="form-control" id="result_unit" name="result_unit">
                    </div>
                    <div class="mb-3">
                        <label for="reference_range" class="form-label">Reference Range</label>
                        <input type="text" class="form-control" id="reference_range" name="reference_range">
                    </div>
                    <div class="mb-3">
                        <label for="result_status" class="form-label">Result Status</label>
                        <select class="form-control" id="result_status" name="result_status">
                            <option value="Normal">Normal</option>
                            <option value="Abnormal">Abnormal</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Result</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Result Modal -->
<div class="modal fade" id="viewResultModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lab Test Results</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="resultContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function setTestId(testId) {
    document.getElementById('lab_test_id').value = testId;
}

function viewResult(testId) {
    const resultContent = document.getElementById('resultContent');
    resultContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p>Loading test results...</p></div>';
    
    fetch('api/get_lab_test_results.php?lab_test_id=' + testId)
        .then(response => response.text())
        .then(data => {
            resultContent.innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            resultContent.innerHTML = '<div class="alert alert-danger">Error loading results. Please try again.</div>';
        });
}
</script>

<?php include '../includes/footer.php'; ?>