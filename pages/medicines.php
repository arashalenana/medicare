<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/paths.php';
require_once '../config/db.php';
require_once '../config/rbac.php';

requirePermission('medicines');
include '../includes/header.php';
include '../includes/sidebar.php';


$message = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_medicine'])) {
        $name = sanitize($_POST['name']);
        $category = sanitize($_POST['category']);
        $batch_no = sanitize($_POST['batch_no']);
        $quantity = (int)$_POST['quantity'];
        $buying_price = (float)$_POST['buying_price'];
        $selling_price = (float)$_POST['selling_price'];
        $expiry_date = $_POST['expiry_date'];

        try {
            $stmt = $conn->prepare("INSERT INTO medicines (name, category, batch_no, quantity, buying_price, selling_price, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $batch_no, $quantity, $buying_price, $selling_price, $expiry_date]);
            $message = "<div class='alert alert-success'>Medicine added successfully!</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } elseif (isset($_POST['update_medicine'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $category = sanitize($_POST['category']);
        $batch_no = sanitize($_POST['batch_no']);
        $quantity = (int)$_POST['quantity'];
        $buying_price = (float)$_POST['buying_price'];
        $selling_price = (float)$_POST['selling_price'];
        $expiry_date = $_POST['expiry_date'];

        try {
            $stmt = $conn->prepare("UPDATE medicines SET name=?, category=?, batch_no=?, quantity=?, buying_price=?, selling_price=?, expiry_date=? WHERE id=?");
            $stmt->execute([$name, $category, $batch_no, $quantity, $buying_price, $selling_price, $expiry_date, $id]);
            $message = "<div class='alert alert-success'>Medicine updated successfully!</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } elseif (isset($_POST['delete_medicine'])) {
        if ($_SESSION['role'] === 'Guest') {
            $message = "<div class='alert alert-danger'>Access Denied: Guests cannot delete medicines.</div>";
        } else {
            $id = (int)$_POST['id'];
            try {
                // Soft delete — marks inactive so sale history stays intact
                $stmt = $conn->prepare("UPDATE medicines SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $message = "<div class='alert alert-success'>Medicine removed successfully!</div>";
            } catch (PDOException $e) {
                // Fallback: if is_active column doesn't exist yet, show helpful message
                $message = "<div class='alert alert-warning'>Please run the database update first: <code>add_medicine_soft_delete.sql</code></div>";
            }
        }
    }
}

// Fetch Active Medicines only
$medicines = [];
try {
    $stmt = $conn->query("SELECT * FROM medicines WHERE is_active = 1 ORDER BY name ASC");
    $medicines = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback if is_active column not yet added
    try {
        $stmt = $conn->query("SELECT * FROM medicines ORDER BY name ASC");
        $medicines = $stmt->fetchAll();
    } catch (PDOException $e2) {}
}

// Dummy medicines for demo when DB empty
if (empty($medicines)) {
    $medicines = [
        ['id'=>1,'name'=>'Paracetamol','category'=>'Analgesic','batch_no'=>'B001','quantity'=>50,'buying_price'=>20.00,'selling_price'=>30.00,'expiry_date'=>date('Y-m-d', strtotime('+12 months'))],
        ['id'=>2,'name'=>'Amoxicillin','category'=>'Antibiotic','batch_no'=>'A123','quantity'=>25,'buying_price'=>50.00,'selling_price'=>75.00,'expiry_date'=>date('Y-m-d', strtotime('+6 months'))]
    ];
}

function getRowClass($med) {
    $today = new DateTime();
    $expiry = new DateTime($med['expiry_date']);
    $diff = $today->diff($expiry);
    $days = $diff->days * ($diff->invert ? -1 : 1);

    if ($days < 0) return 'row-expired';
    if ($days <= 30) return 'row-expiring';
    if ($med['quantity'] < 10) return 'row-low-stock';
    return '';
}

function getStatusBadge($med) {
    $today = new DateTime();
    $expiry = new DateTime($med['expiry_date']);
    $diff = $today->diff($expiry);
    $days = $diff->days * ($diff->invert ? -1 : 1);

    if ($days < 0) return '<span class="badge bg-dark">Expired</span>';
    if ($days <= 30) return '<span class="badge bg-danger">Expiring Soon</span>';
    if ($med['quantity'] < 10) return '<span class="badge bg-warning text-dark">Low Stock</span>';
    return '<span class="badge bg-success">In Stock</span>';
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Medicine Inventory</h1>
        <button class="btn btn-primary mt-2 mt-sm-0" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
            <i class="fas fa-plus"></i> Add New Medicine
        </button>
    </div>

    <?php echo $message; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">All Medicines (<?php echo count($medicines); ?> Items)</h6>
            <input type="text" id="medicineSearch" class="form-control" placeholder="Search by medicine name or batch..." style="max-width: 300px;">
        </div>
        <div class="card-body p-0 p-sm-3">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" id="medicineTable">
                    <thead class="table-light">
                        <tr>
                            <th>Name & Batch</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Price</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicines)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No medicines found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($medicines as $med): ?>
                            <tr class="<?php echo getRowClass($med); ?>">
                                <td>
                                    <div class="fw-bold text-wrap" style="min-width: 150px;"><?php echo $med['name']; ?></div>
                                    <small class="text-muted">Batch: <?php echo $med['batch_no']; ?></small>
                                </td>
                                <td><?php echo $med['category']; ?></td>
                                <td><?php echo $med['quantity']; ?></td>
                                <td><?php echo formatKSh($med['selling_price']); ?></td>
                                <td><?php echo $med['expiry_date']; ?></td>
                                <td><?php echo getStatusBadge($med); ?></td>
                                <td>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button class="btn btn-outline-info btn-sm edit-btn" 
                                                data-id="<?php echo $med['id']; ?>"
                                                data-name="<?php echo $med['name']; ?>"
                                                data-category="<?php echo $med['category']; ?>"
                                                data-batch="<?php echo $med['batch_no']; ?>"
                                                data-quantity="<?php echo $med['quantity']; ?>"
                                                data-buying="<?php echo $med['buying_price']; ?>"
                                                data-selling="<?php echo $med['selling_price']; ?>"
                                                data-expiry="<?php echo $med['expiry_date']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editMedicineModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                            <input type="hidden" name="id" value="<?php echo $med['id']; ?>">
                                            <?php if ($_SESSION['role'] !== 'Guest'): ?>
                                            <button type="submit" name="delete_medicine" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </form>
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

<!-- Add Medicine Modal -->
<div class="modal fade" id="addMedicineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Medicine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Medicine Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_no" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" required min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Buying Price (KSh)</label>
                            <input type="number" step="0.01" name="buying_price" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Selling Price (KSh)</label>
                            <input type="number" step="0.01" name="selling_price" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_medicine" class="btn btn-primary">Save Medicine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Medicine Modal -->
<div class="modal fade" id="editMedicineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Medicine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Medicine Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="edit_category" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_no" id="edit_batch" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" required min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Buying Price (KSh)</label>
                            <input type="number" step="0.01" name="buying_price" id="edit_buying" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Selling Price (KSh)</label>
                            <input type="number" step="0.01" name="selling_price" id="edit_selling" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" id="edit_expiry" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_medicine" class="btn btn-primary">Update Medicine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_category').value = this.dataset.category;
            document.getElementById('edit_batch').value = this.dataset.batch;
            document.getElementById('edit_quantity').value = this.dataset.quantity;
            document.getElementById('edit_buying').value = this.dataset.buying;
            document.getElementById('edit_selling').value = this.dataset.selling;
            document.getElementById('edit_expiry').value = this.dataset.expiry;
        });
    });
});

document.getElementById('medicineSearch').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#medicineTable tbody tr');
    
    tableRows.forEach(row => {
        const medicineName = row.cells[0].textContent.toLowerCase();
        const batch = row.cells[0].textContent.toLowerCase();
        const category = row.cells[1].textContent.toLowerCase();
        
        if (medicineName.includes(searchValue) || batch.includes(searchValue) || category.includes(searchValue)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>