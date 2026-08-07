<?php
// Consultations Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../config/paths.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_path . 'login.php');
    exit();
}

require_once '../config/rbac.php';
requirePermission('consultations');

$page_title   = 'Consultations';
$current_page = 'consultations.php';
$role         = $_SESSION['role'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        if ($_POST['action'] === 'create') {
            $patient_id        = $_POST['patient_id']        ?? null;
            $doctor_name       = $_POST['doctor_name']       ?? '';
            $consultation_date = $_POST['consultation_date'] ?? '';
            $reason            = $_POST['reason']            ?? '';
            $previous_diagnosis= trim($_POST['previous_diagnosis'] ?? '');
            $diagnosis         = $_POST['diagnosis']         ?? '';

            if ($patient_id && $doctor_name && $consultation_date) {
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO consultations
                            (patient_id, doctor_name, consultation_date, reason, previous_diagnosis, diagnosis, status)
                         VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
                    );
                    $stmt->execute([$patient_id, $doctor_name, $consultation_date, $reason, $previous_diagnosis, $diagnosis]);
                    $success_msg = "Consultation created successfully!";
                } catch (PDOException $e) {
                    // Column may not exist yet — try without it
                    if (strpos($e->getMessage(), 'previous_diagnosis') !== false) {
                        try {
                            $conn->exec("ALTER TABLE `consultations` ADD COLUMN `previous_diagnosis` text DEFAULT NULL AFTER `reason`");
                            $stmt = $conn->prepare(
                                "INSERT INTO consultations
                                    (patient_id, doctor_name, consultation_date, reason, previous_diagnosis, diagnosis, status)
                                 VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
                            );
                            $stmt->execute([$patient_id, $doctor_name, $consultation_date, $reason, $previous_diagnosis, $diagnosis]);
                            $success_msg = "Consultation created successfully!";
                        } catch (PDOException $e2) {
                            $error_msg = "Error creating consultation: " . $e2->getMessage();
                        }
                    } else {
                        $error_msg = "Error creating consultation: " . $e->getMessage();
                    }
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }

        } elseif ($_POST['action'] === 'update') {
            $consultation_id    = $_POST['consultation_id']    ?? null;
            $diagnosis          = $_POST['diagnosis']          ?? '';
            $previous_diagnosis = trim($_POST['previous_diagnosis'] ?? '');
            $status             = $_POST['status']             ?? 'Pending';

            if ($consultation_id) {
                try {
                    $stmt = $conn->prepare(
                        "UPDATE consultations
                         SET diagnosis = ?, previous_diagnosis = ?, status = ?
                         WHERE id = ?"
                    );
                    $stmt->execute([$diagnosis, $previous_diagnosis, $status, $consultation_id]);
                    $success_msg = "Consultation updated successfully!";
                } catch (PDOException $e) {
                    $error_msg = "Error updating consultation: " . $e->getMessage();
                }
            }
        }
    }
}

// Auto-add previous_diagnosis column if missing
try {
    $conn->exec("ALTER TABLE `consultations` ADD COLUMN `previous_diagnosis` text DEFAULT NULL AFTER `reason`");
} catch (PDOException $e) {
    // Already exists — ignore
}

// Fetch consultations
$consultations = [];
try {
    $stmt = $conn->query(
        "SELECT c.*, p.first_name, p.last_name
         FROM consultations c
         JOIN patients p ON c.patient_id = p.id
         ORDER BY c.created_at DESC"
    );
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching consultations: " . $e->getMessage();
}

if (empty($consultations)) {
    $consultations = [
        ['id'=>1,'patient_id'=>1,'first_name'=>'John','last_name'=>'Doe','doctor_name'=>'Dr. Alice',
         'consultation_date'=>date('Y-m-d H:i:s'),'reason'=>'Fever and cough',
         'previous_diagnosis'=>'Common Cold (Jan 2026)','diagnosis'=>'Viral infection','status'=>'Pending'],
        ['id'=>2,'patient_id'=>2,'first_name'=>'Jane','last_name'=>'Smith','doctor_name'=>'Dr. Bob',
         'consultation_date'=>date('Y-m-d H:i:s', strtotime('-1 day')),'reason'=>'Headache',
         'previous_diagnosis'=>'','diagnosis'=>'Tension headache','status'=>'Completed'],
    ];
    $is_dummy_consultations = true;
}

// Fetch patients for dropdown
$patients = [];
try {
    $stmt = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM patients ORDER BY first_name");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (empty($patients)) {
    $patients = [['id'=>1,'name'=>'John Doe'],['id'=>2,'name'=>'Jane Smith']];
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
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Previous Diagnosis</th>
                            <th>Diagnosis</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultations as $c): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['doctor_name']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($c['consultation_date'])); ?></td>
                            <td><?php echo htmlspecialchars(substr($c['reason'] ?? '', 0, 40)); ?></td>
                            <td>
                                <?php if (!empty($c['previous_diagnosis'])): ?>
                                <span id="prev-mask-<?php echo $c['id']; ?>">
                                    ••••••
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-secondary" title="Reveal previous diagnosis"
                                        onclick="revealPrev(<?php echo $c['id']; ?>, <?php echo htmlspecialchars(json_encode($c['previous_diagnosis']), ENT_QUOTES); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </span>
                                <span class="d-none" id="prev-text-<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars(substr($c['previous_diagnosis'], 0, 60)); ?>
                                    <?php if (strlen($c['previous_diagnosis']) > 60): ?>…<?php endif; ?>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-secondary" title="Hide previous diagnosis"
                                        onclick="hidePrev(<?php echo $c['id']; ?>)">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($role === 'Admin' || $role === 'Guest'): ?>
                            <td>
                                <span class="text-muted fst-italic" id="diag-mask-<?php echo $c['id']; ?>">
                                    ••••••
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-secondary" title="Reveal diagnosis"
                                        onclick="revealDiagnosis(<?php echo $c['id']; ?>, <?php echo htmlspecialchars(json_encode($c['diagnosis'] ?? ''), ENT_QUOTES); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </span>
                                <span class="d-none" id="diag-text-<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars(substr($c['diagnosis'] ?? '', 0, 40)); ?>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-secondary" title="Hide diagnosis"
                                        onclick="hideDiagnosis(<?php echo $c['id']; ?>)">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                </span>
                            </td>
                            <?php else: ?>
                            <td><?php echo htmlspecialchars(substr($c['diagnosis'] ?? '', 0, 40)); ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge bg-<?php echo $c['status'] === 'Completed' ? 'success' : ($c['status'] === 'Cancelled' ? 'danger' : 'warning'); ?>">
                                    <?php echo $c['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info"
                                    data-bs-toggle="modal" data-bs-target="#editConsultationModal"
                                    onclick="editConsultation(
                                        <?php echo $c['id']; ?>,
                                        <?php echo htmlspecialchars(json_encode($c['diagnosis'] ?? ''), ENT_QUOTES); ?>,
                                        '<?php echo $c['status']; ?>',
                                        <?php echo htmlspecialchars(json_encode($c['previous_diagnosis'] ?? ''), ENT_QUOTES); ?>
                                    )">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2 text-primary"></i>New Consultation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Patient <span class="text-danger">*</span></label>
                            <select class="form-control" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Doctor Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="doctor_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="consultation_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-control" name="status">
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Chief Complaints</label>
                            <textarea class="form-control" name="reason" rows="2" placeholder="Main symptoms or reason for visit"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Previous Diagnosis</label>
                            <textarea class="form-control" name="previous_diagnosis" rows="2" placeholder="Any prior diagnosis relevant to this visit"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Diagnosis</label>
                            <textarea class="form-control" name="diagnosis" rows="2" placeholder="Current diagnosis"></textarea>
                        </div>
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
                <h5 class="modal-title"><i class="fas fa-edit me-2 text-info"></i>Edit Consultation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_consultation_id" name="consultation_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Previous Diagnosis</label>
                        <textarea class="form-control" id="edit_previous_diagnosis" name="previous_diagnosis" rows="2"
                            placeholder="Prior diagnosis relevant to this visit"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Diagnosis</label>
                        <textarea class="form-control" id="edit_diagnosis" name="diagnosis" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
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
function editConsultation(id, diagnosis, status, previousDiagnosis) {
    document.getElementById('edit_consultation_id').value    = id;
    document.getElementById('edit_diagnosis').value          = diagnosis;
    document.getElementById('edit_status').value             = status;
    document.getElementById('edit_previous_diagnosis').value = previousDiagnosis;
}
function revealDiagnosis(id, text) {
    document.getElementById('diag-mask-' + id).classList.add('d-none');
    document.getElementById('diag-text-' + id).classList.remove('d-none');
}
function hideDiagnosis(id) {
    document.getElementById('diag-text-' + id).classList.add('d-none');
    document.getElementById('diag-mask-' + id).classList.remove('d-none');
}
function revealPrev(id, text) {
    document.getElementById('prev-mask-' + id).classList.add('d-none');
    document.getElementById('prev-text-' + id).classList.remove('d-none');
}
function hidePrev(id) {
    document.getElementById('prev-text-' + id).classList.add('d-none');
    document.getElementById('prev-mask-' + id).classList.remove('d-none');
}
</script>

<?php include '../includes/footer.php'; ?>