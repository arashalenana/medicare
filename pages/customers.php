<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/paths.php';
require_once '../config/db.php';
require_once '../config/rbac.php';

requirePermission('patients');

include '../includes/header.php';
include '../includes/sidebar.php';

$role = $_SESSION['role'] ?? '';
$can_set_service = in_array($role, ['Admin', 'Receptionist', 'Cashier', 'Guest']);

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_customer'])) {
        if (!hasPermission('patient_create')) {
            $message = "<div class='alert alert-danger'>Access Denied: You do not have permission to add patients.</div>";
        } else {
            $first_name = sanitize($_POST['first_name']);
            $last_name  = sanitize($_POST['last_name']);
            $phone      = sanitize($_POST['phone']);
            $age        = !empty($_POST['age']) ? (int)$_POST['age'] : null;
            $place      = sanitize($_POST['place'] ?? '');
            $service    = $can_set_service ? sanitize($_POST['service'] ?? '') : null;
            try {
                $stmt = $conn->prepare("INSERT INTO patients (first_name, last_name, phone, age, place, service, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$first_name, $last_name, $phone, $age, $place ?: null, $service ?: null]);
                $message = "<div class='alert alert-success'>Patient added successfully!</div>";
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
    } elseif (isset($_POST['update_customer'])) {
        if (!hasPermission('patient_edit')) {
            $message = "<div class='alert alert-danger'>Access Denied: You do not have permission to edit patients.</div>";
        } else {
            $id         = (int)$_POST['id'];
            $first_name = sanitize($_POST['first_name']);
            $last_name  = sanitize($_POST['last_name']);
            $phone      = sanitize($_POST['phone']);
            $age        = !empty($_POST['age']) ? (int)$_POST['age'] : null;
            $place      = sanitize($_POST['place'] ?? '');
            $service    = $can_set_service ? sanitize($_POST['service'] ?? '') : null;
            try {
                $stmt = $conn->prepare("UPDATE patients SET first_name=?, last_name=?, phone=?, age=?, place=?, service=? WHERE id=?");
                $stmt->execute([$first_name, $last_name, $phone, $age, $place ?: null, $service ?: null, $id]);
                $message = "<div class='alert alert-success'>Patient updated successfully!</div>";
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
    } elseif (isset($_POST['delete_customer'])) {
        if (!hasPermission('patient_delete')) {
            $message = "<div class='alert alert-danger'>Access Denied: You do not have permission to delete patients.</div>";
        } else {
            $id = (int)$_POST['id'];
            try {
                $stmt = $conn->prepare("DELETE FROM patients WHERE id=?");
                $stmt->execute([$id]);
                // Reset counter to 1 if table is now empty
                $remaining = $conn->query("SELECT COUNT(*) FROM patients")->fetchColumn();
                if ($remaining == 0) {
                    $conn->exec("ALTER TABLE patients AUTO_INCREMENT = 1");
                }
                $message = "<div class='alert alert-success'>Patient deleted successfully!</div>";
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }
}

$customers = [];
try {
    $stmt = $conn->query("SELECT * FROM patients ORDER BY first_name ASC");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {}

if (empty($customers)) {
    $customers = [
        ['id'=>1,'first_name'=>'John','last_name'=>'Kamau','phone'=>'0712345678','age'=>35,'place'=>'Westlands','service'=>'Consultation','created_at'=>date('Y-m-d H:i:s')],
        ['id'=>2,'first_name'=>'Jane','last_name'=>'Wangari','phone'=>'0723456789','age'=>28,'place'=>'Karen','service'=>'Lab','created_at'=>date('Y-m-d H:i:s')]
    ];
    $is_dummy_customers = true;
}

$service_options = ['Treatment', 'Consultation', 'Over The Counter', 'Lab'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Patient Management</h1>
        <?php if (hasPermission('patient_create')): ?>
        <button class="btn btn-primary mt-2 mt-sm-0" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-user-plus"></i> Add Patient
        </button>
        <?php endif; ?>
    </div>

    <?php echo $message; ?>

    <div class="card shadow mb-4">
        <div class="card-body p-0 p-sm-3">
            <div class="mb-3">
                <input type="text" id="customerSearch" class="form-control" placeholder="Search by name, phone or place..." style="max-width:340px;">
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>OP No.</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Age</th>
                            <th>Phone</th>
                            <th>Place</th>
                            <th>Service</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No patients found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td class="fw-bold text-danger">OP-<?php echo str_pad($customer['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($customer['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['last_name']); ?></td>
                                <td><?php echo !empty($customer['age']) ? htmlspecialchars($customer['age']) . ' yrs' : '<span class="text-muted">—</span>'; ?></td>
                                <td><?php echo htmlspecialchars($customer['phone'] ?? ''); ?></td>
                                <td><?php echo !empty($customer['place']) ? htmlspecialchars($customer['place']) : '<span class="text-muted">—</span>'; ?></td>
                                <td>
                                    <?php if (!empty($customer['service'])): ?>
                                        <?php
                                        $svc_colors = ['Treatment'=>'success','Consultation'=>'primary','Over The Counter'=>'warning','Lab'=>'info'];
                                        $svc = $customer['service'];
                                        $badge = $svc_colors[$svc] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($svc); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($customer['created_at'])); ?></td>
                                <td>
                                    <div class="d-flex gap-2 align-items-center flex-wrap">
                                        <a href="patient_view.php?id=<?php echo $customer['id']; ?>" class="btn btn-outline-primary btn-sm">View</a>
                                        <?php if (hasPermission('patient_history')): ?>
                                        <a href="patient_history.php?id=<?php echo $customer['id']; ?>" class="btn btn-outline-secondary btn-sm">History</a>
                                        <?php endif; ?>
                                        <?php if (hasPermission('patient_edit')): ?>
                                        <button class="btn btn-outline-info btn-sm edit-btn"
                                                data-id="<?php echo $customer['id']; ?>"
                                                data-first="<?php echo htmlspecialchars($customer['first_name']); ?>"
                                                data-last="<?php echo htmlspecialchars($customer['last_name']); ?>"
                                                data-phone="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                                                data-age="<?php echo htmlspecialchars($customer['age'] ?? ''); ?>"
                                                data-place="<?php echo htmlspecialchars($customer['place'] ?? ''); ?>"
                                                data-service="<?php echo htmlspecialchars($customer['service'] ?? ''); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editCustomerModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (hasPermission('patient_delete')): ?>
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirm('Delete this patient?');">
                                            <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                                            <button type="submit" name="delete_customer" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                        </form>
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

<!-- Add Patient Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2 text-primary"></i>Add New Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="Enter first name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="Enter last name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 0712345678" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" placeholder="e.g. 32" min="0" max="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Place (Living Area)</label>
                            <input type="text" name="place" class="form-control" placeholder="e.g. Westlands, Nairobi">
                        </div>
                        <?php if ($can_set_service): ?>
                        <div class="col-md-6">
                            <label class="form-label">Service</label>
                            <select name="service" class="form-select">
                                <option value="">-- Select Service --</option>
                                <?php foreach ($service_options as $opt): ?>
                                    <option value="<?php echo $opt; ?>"><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_customer" class="btn btn-primary">Save Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Patient Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2 text-info"></i>Edit Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="edit_first" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="edit_last" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="edit_phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" id="edit_age" class="form-control" min="0" max="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Place (Living Area)</label>
                            <input type="text" name="place" id="edit_place" class="form-control">
                        </div>
                        <?php if ($can_set_service): ?>
                        <div class="col-md-6">
                            <label class="form-label">Service</label>
                            <select name="service" id="edit_service" class="form-select">
                                <option value="">-- Select Service --</option>
                                <?php foreach ($service_options as $opt): ?>
                                    <option value="<?php echo $opt; ?>"><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_customer" class="btn btn-primary">Update Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value    = this.dataset.id;
            document.getElementById('edit_first').value = this.dataset.first;
            document.getElementById('edit_last').value  = this.dataset.last;
            document.getElementById('edit_phone').value = this.dataset.phone;
            document.getElementById('edit_age').value   = this.dataset.age;
            document.getElementById('edit_place').value = this.dataset.place;
            const svcSel = document.getElementById('edit_service');
            if (svcSel) svcSel.value = this.dataset.service;
        });
    });

    document.getElementById('customerSearch').addEventListener('keyup', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.table tbody tr').forEach(row => {
            const text = [1,2,4,5].map(i => row.cells[i]?.textContent.toLowerCase()).join(' ');
            row.style.display = text.includes(q) ? '' : 'none';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>