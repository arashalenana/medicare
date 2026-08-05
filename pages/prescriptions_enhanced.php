<?php
// Enhanced Prescriptions Management Page
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

// Check if user is Admin (only Admin can manage prescriptions)
requirePermission('prescriptions');

$page_title = 'Prescriptions';
$current_page = 'prescriptions_enhanced.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $patient_id = $_POST['patient_id'] ?? null;
            $doctor_name = $_POST['doctor_name'] ?? '';
            $prescription_date = $_POST['prescription_date'] ?? date('Y-m-d');
            $notes = $_POST['notes'] ?? '';

            if ($patient_id && $doctor_name) {
                try {
                    $stmt = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_name, prescription_date, notes, status) VALUES (?, ?, ?, ?, 'Active')");
                    $stmt->execute([$patient_id, $doctor_name, $prescription_date, $notes]);
                    $prescription_id = $conn->lastInsertId();
                    $success_msg = "Prescription created successfully! ID: " . $prescription_id;
                } catch (PDOException $e) {
                    $error_msg = "Error creating prescription: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        } elseif ($_POST['action'] === 'add_item') {
            $prescription_id = $_POST['prescription_id'] ?? null;
            $medicine_id = $_POST['medicine_id'] ?? null;
            $quantity = $_POST['quantity'] ?? 1;
            $dosage = $_POST['dosage'] ?? '';
            $frequency = $_POST['frequency'] ?? '';
            $duration_days = $_POST['duration_days'] ?? null;
            $instructions = $_POST['instructions'] ?? '';

            if ($prescription_id && $medicine_id && $quantity) {
                try {
                    $stmt = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_id, quantity, dosage, frequency, duration_days, instructions) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$prescription_id, $medicine_id, $quantity, $dosage, $frequency, $duration_days, $instructions]);
                    $success_msg = "Medicine added to prescription!";
                } catch (PDOException $e) {
                    $error_msg = "Error adding medicine: " . $e->getMessage();
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        } elseif ($_POST['action'] === 'update_status') {
            $prescription_id = $_POST['prescription_id'] ?? null;
            $status = $_POST['status'] ?? 'Active';

            if ($prescription_id) {
                try {
                    $stmt = $conn->prepare("UPDATE prescriptions SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $prescription_id]);
                    $success_msg = "Prescription status updated!";
                } catch (PDOException $e) {
                    $error_msg = "Error updating prescription: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch prescriptions with patient info
$prescriptions = [];
try {
    $stmt = $conn->query("SELECT p.*, pa.first_name, pa.last_name FROM prescriptions p JOIN patients pa ON p.patient_id = pa.id ORDER BY p.created_at DESC");
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Error fetching prescriptions: " . $e->getMessage();
}

// Dummy prescriptions to show sample data when DB empty
if (empty($prescriptions)) {
    $prescriptions = [
        ['id'=>201,'patient_id'=>1,'first_name'=>'John','last_name'=>'Doe','doctor_name'=>'Dr. Alice','prescription_date'=>date('Y-m-d'),'notes'=>'Take with food','status'=>'Active'],
        ['id'=>202,'patient_id'=>2,'first_name'=>'Jane','last_name'=>'Smith','doctor_name'=>'Dr. Bob','prescription_date'=>date('Y-m-d', strtotime('-1 day')),'notes'=>'Before sleep','status'=>'Completed']
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

// Fetch medicines for dropdown (try to select dosage, fallback if column missing)
$medicines = [];
try {
    try {
        $stmt = $conn->query("SELECT id, name, dosage FROM medicines WHERE quantity > 0 ORDER BY name");
        $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $inner) {
        // Fallback to select without dosage if the column doesn't exist
        $stmt = $conn->query("SELECT id, name FROM medicines WHERE quantity > 0 ORDER BY name");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $medicines = array_map(function($r){ $r['dosage'] = $r['dosage'] ?? ''; return $r; }, $rows);
    }
} catch (PDOException $e) {
    $error_msg = "Error fetching medicines: " . $e->getMessage();
}

if (empty($medicines)) {
    $medicines = [
        ['id'=>1,'name'=>'Paracetamol','dosage'=>'500mg'],
        ['id'=>2,'name'=>'Amoxicillin','dosage'=>'250mg']
    ];
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">Prescriptions Management</h1>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#prescriptionModal">
                <i class="fas fa-plus"></i> New Prescription
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
                            <th>Status</th>
                            <th>Items</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                <td><?php echo $prescription['prescription_date'] ? date('M d, Y', strtotime($prescription['prescription_date'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $prescription['status'] === 'Active' ? 'success' : ($prescription['status'] === 'Cancelled' ? 'danger' : 'secondary'); ?>">
                                        <?php echo $prescription['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#itemsModal" onclick="viewItems(<?php echo $prescription['id']; ?>)">
                                        <i class="fas fa-list"></i> View
                                    </button>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addItemModal" onclick="setPrescriptionId(<?php echo $prescription['id']; ?>)">
                                        <i class="fas fa-plus"></i> Add Item
                                    </button>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#statusModal" onclick="setStatusData(<?php echo $prescription['id']; ?>, '<?php echo $prescription['status']; ?>')">
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

<!-- New Prescription Modal -->
<div class="modal fade" id="prescriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Prescription</h5>
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
                        <label for="prescription_date" class="form-label">Prescription Date</label>
                        <input type="date" class="form-control" id="prescription_date" name="prescription_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Prescription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Medicine to Prescription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" id="prescription_id" name="prescription_id">
                    <div class="mb-3">
                        <label for="medicine_id" class="form-label">Medicine *</label>
                        <select class="form-control" id="medicine_id" name="medicine_id" required>
                            <option value="">Select Medicine</option>
                            <?php foreach ($medicines as $medicine): ?>
                                <option value="<?php echo $medicine['id']; ?>"><?php echo htmlspecialchars($medicine['name'] . ' - ' . $medicine['dosage']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity *</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="dosage" class="form-label">Dosage</label>
                        <input type="text" class="form-control" id="dosage" name="dosage" placeholder="e.g., 500mg">
                    </div>
                    <div class="mb-3">
                        <label for="frequency" class="form-label">Frequency</label>
                        <input type="text" class="form-control" id="frequency" name="frequency" placeholder="e.g., Twice daily">
                    </div>
                    <div class="mb-3">
                        <label for="duration_days" class="form-label">Duration (days)</label>
                        <input type="number" class="form-control" id="duration_days" name="duration_days" min="1">
                    </div>
                    <div class="mb-3">
                        <label for="instructions" class="form-label">Instructions</label>
                        <textarea class="form-control" id="instructions" name="instructions" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Prescription Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" id="status_prescription_id" name="prescription_id">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Items Modal -->
<div class="modal fade" id="itemsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Prescription Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="itemsContent">Loading...</div>
            </div>
        </div>
    </div>
</div>

<script>
function setPrescriptionId(prescriptionId) {
    document.getElementById('prescription_id').value = prescriptionId;
}

function setStatusData(prescriptionId, status) {
    document.getElementById('status_prescription_id').value = prescriptionId;
    document.getElementById('status').value = status;
}

function viewItems(prescriptionId) {
    const itemsContent = document.getElementById('itemsContent');
    itemsContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p>Loading items for prescription ID: ' + prescriptionId + '...</p></div>';
    
    fetch('api/get_prescription_items.php?prescription_id=' + prescriptionId)
        .then(response => response.text())
        .then(data => {
            itemsContent.innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            itemsContent.innerHTML = '<div class="alert alert-danger">Error loading items. Please try again.</div>';
        });
}
</script>

<?php include '../includes/footer.php'; ?>