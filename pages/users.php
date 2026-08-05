<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}
require_once '../config/paths.php';
require_once '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$message = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = sanitize($_POST['username']);
        $password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
        $role = sanitize($_POST['role']);

        try {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $password, $role]);
            
            // Redirect based on role if needed.
            
            $message = "<div class='alert alert-success'>User added successfully!</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } elseif (isset($_POST['update_user'])) {
        $id = (int)$_POST['id'];
        $username = sanitize($_POST['username']);
        $role = sanitize($_POST['role']);
        $password = $_POST['password'];

        try {
            if (!empty($password)) {
                $hashed = password_hash(trim($password), PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET username=?, password=?, role=? WHERE id=?");
                $stmt->execute([$username, $hashed, $role, $id]);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, role=? WHERE id=?");
                $stmt->execute([$username, $role, $id]);
            }
            $message = "<div class='alert alert-success'>User updated successfully!</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['id'];
        if ($id == $_SESSION['user_id']) {
            $message = "<div class='alert alert-danger'>You cannot delete your own account!</div>";
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$id]);
                $message = "<div class='alert alert-success'>User deleted successfully!</div>";
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Fetch Users
$users = [];
try {
    $stmt = $conn->query("SELECT * FROM users ORDER BY role ASC, username ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {}

// Provide demo users if none exist (useful for initial setup/testing)
if (empty($users)) {
    $users = [
        ['id'=>1,'username'=>'admin','password'=>'admin123','role'=>'Admin'],
        ['id'=>2,'username'=>'receptionist','password'=>'receptionist123','role'=>'Receptionist']
    ];
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Management</h1>
        <button class="btn btn-primary mt-2 mt-sm-0" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
    </div>

    <?php echo $message; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">System Users</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#U-<?php echo str_pad($user['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td class="fw-bold"><?php echo $user['username']; ?></td>
                            <td>
                                <span class="badge <?php echo $user['role'] == 'Admin' ? 'bg-danger' : 'bg-info text-dark'; ?>">
                                    <?php echo $user['role']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2 align-items-center">
                                    <button class="btn btn-outline-info btn-sm edit-user-btn" 
                                            data-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo $user['username']; ?>"
                                            data-role="<?php echo $user['role']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editUserModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form action="" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="Receptionist">Receptionist</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Lab Tech">Lab Tech</option>
                            <option value="Guest">Guest</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="Receptionist">Receptionist</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Lab Tech">Lab Tech</option>
                            <option value="Guest">Guest</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-user-btn');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = this.dataset.id;
            document.getElementById('edit_username').value = this.dataset.username;
            document.getElementById('edit_role').value = this.dataset.role;
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>