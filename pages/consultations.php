<?php
// Consultations Management Page
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

// Check if user is Admin (only Admin can manage consultations)
requirePermission('consultations');

$page_title = 'Consultations';
$current_page = 'consultations.php';
$role = $_SESSION['role'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $patient_id = $_POST['patient_id'] ?? null;
            $doctor_name = $_POST['doctor_name'] ?? '';
            $consultation_date = $_POST['consultation_date'] ?? '';
            $reason = $_POST['reason'] ?? '';
            $diagnosis = $_POST['diagnosis'] ?? '';

            if ($patient_id && $doctor_name && $consultation_date) {
                try {
                    $stmt = $conn->prepare("INSERT INTO consultations (patient_id, doctor_name, consultation_date, reason, diagnosis, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                    $stmt->execute([$patient_id, $doctor_name, $consultation_date, $reason, $diagnosis]);
                    $success_msg = "Consultation created successfully!";
                } catch (PDOException $e) {
                    $error_msg = "Error creating consultation: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        } elseif ($_POST['action'] === 'update') {
            $consultation_id = $_POST['consultation_id'] ?? null;
            $diagnosis = $_POST['diagnosis'] ?? '';
            $status = $_POST['status'] ?? 'Pending';

            if ($consultation_id) {
                try {
                    $stmt = $conn->prepare("UPDATE consultations SET diagnosis = ?, status = ? WHERE id = ?");
                    $stmt->execute([$diagnosis, $status, $consultation_id]);
                    $success_msg = "Consultation updated successfully!";
                } catch (PDOException $e) {
                    $error_msg = "Error updating consultation: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch consultations
$consultations = [];
try {
    $stmt = $conn->query("SELECT c.*, p.first_name, p.last_name FROM consultations c JOIN patients p ON c.patient_id = p.id ORDER BY c.created_at DESC");
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching consultations: " . $e->getMessage();
}

// Dummy consultations when none exist (helps verify UI/flows)
if (empty($consultations)) {
    $consultations = [
        ['id'=>1,'patient_id'=>1,'first_name'=>'John','last_name'=>'Doe','doctor_name'=>'Dr. Alice','consultation_date'=>date('Y-m-d H:i:s'),'reason'=>'Fever and cough','diagnosis'=>'Viral infection','status'=>'Pending'],
        ['id'=>2,'patient_id'=>2,'first_name'=>'Jane','last_name'=>'Smith','doctor_name'=>'Dr. Bob','consultation_date'=>date('Y-m-d H:i:s', strtotime('-1 day')),'reason'=>'Headache','diagnosis'=>'Tension headache','status'=>'Completed']
    ];
    $is_dummy_consultations = true;
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
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">Consultations Management</h1>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#consultationModal">
                <i class="fas fa-plus"></i> New Consultation
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
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <?php if ($role === 'Admin'): ?>
                            <th>Diagnosis</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultations as $consultation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($consultation['first_name'] . ' ' . $consultation['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['doctor_name']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($consultation['consultation_date'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($consultation['reason'], 0, 30)); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $consultation['status'] === 'Completed' ? 'success' : ($consultation['status'] === 'Cancelled' ? 'danger' : 'warning'); ?>">
                                        <?php echo $consultation['status']; ?>
                                    </span>
                                </td>
                                <?php if ($role === 'Admin'): ?>
                                <td>
                                    <span class="diagnosis-hidden text-muted fst-italic" id="diag-mask-<?php echo $consultation['id']; ?>">
                                        ••••••
                                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-secondary" title="Reveal diagnosis"
                                            onclick="revealDiagnosis(<?php echo $consultation['id']; ?>, <?php echo htmlspecialchars(json_encode($consultation['diagnosis'] ?? ''), ENT_QUOTES); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </span>
                                    <span class="d-none" id="diag-text-<?php echo $consultation['id']; ?>">
                                        <?php echo htmlspecialchars(substr($consultation['diagnosis'] ?? '', 0, 40)); ?>
                                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-secondary" title="Hide diagnosis"
                                            onclick="hideDiagnosis(<?php echo $consultation['id']; ?>)">
                                            <i class="fas fa-eye-slash"></i>
                                        </button>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editConsultationModal" onclick="editConsultation(<?php echo $consultation['id']; ?>, '<?php echo htmlspecialchars($consultation['diagnosis'] ?? '', ENT_QUOTES); ?>', '<?php echo $consultation['status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Consultation Modal -->
<div class="modal fade" id="consultationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Consultation</h5>
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
                        <label for="doctor_name" class="form-label">Doctor Name *</label>
                        <input type="text" class="form-control" id="doctor_name" name="doctor_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="consultation_date" class="form-label">Consultation Date & Time *</label>
                        <input type="datetime-local" class="form-control" id="consultation_date" name="consultation_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Consultation</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="diagnosis" class="form-label">Diagnosis</label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Consultation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Consultation Modal -->
<div class="modal fade" id="editConsultationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Consultation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_consultation_id" name="consultation_id">
                    <div class="mb-3">
                        <label for="edit_diagnosis" class="form-label">Diagnosis</label>
                        <textarea class="form-control" id="edit_diagnosis" name="diagnosis" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-control" id="edit_status" name="status">
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Consultation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editConsultation(id, diagnosis, status) {
    document.getElementById('edit_consultation_id').value = id;
    document.getElementById('edit_diagnosis').value = diagnosis;
    document.getElementById('edit_status').value = status;
}
function revealDiagnosis(id, text) {
    document.getElementById('diag-mask-' + id).classList.add('d-none');
    document.getElementById('diag-text-' + id).classList.remove('d-none');
}
function hideDiagnosis(id) {
    document.getElementById('diag-text-' + id).classList.add('d-none');
    document.getElementById('diag-mask-' + id).classList.remove('d-none');
}
</script>

<?php include '../includes/footer.php'; ?>