<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/paths.php';
require_once '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';


$message = "";

// Handle Sale Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_sale'])) {
    $cart_data = json_decode($_POST['cart_data'], true);
    $total_amount = (float)$_POST['total_amount'];
    $payment_method = sanitize($_POST['payment_method']);
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $user_id = $_SESSION['user_id'];
    
    if (!empty($cart_data)) {
        try {
            $conn->beginTransaction();
            
            // Insert into sales table (use patient_id column)
            $stmt = $conn->prepare("INSERT INTO sales (patient_id, total_amount, payment_method, user_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$customer_id, $total_amount, $payment_method, $user_id]);
            $sale_id = $conn->lastInsertId();
            
            // Insert items and update stock
            $item_stmt = $conn->prepare("INSERT INTO sale_items (sale_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stock_stmt = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");

            // Helpers: find medicine by id and insert placeholder if missing
            $find_med = $conn->prepare("SELECT id, quantity FROM medicines WHERE id = ?");
            $insert_med = $conn->prepare("INSERT INTO medicines (name, category, batch_no, quantity, buying_price, selling_price, expiry_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

            foreach ($cart_data as $item) {
                $mid = $item['id'];

                // If the medicine id is missing or not found in DB, create a placeholder record
                $found = false;
                if (is_numeric($mid)) {
                    $find_med->execute([$mid]);
                    $found = $find_med->fetch(PDO::FETCH_ASSOC);
                }

                if (!$found) {
                    $med_name = !empty($item['name']) ? $item['name'] : 'Unknown Medicine';
                    $qty_stock = max(50, (int)$item['qty'] + 10);
                    $buy_price = round(((float)$item['price']) * 0.6, 2);
                    $sell_price = (float)$item['price'];
                    $batch_no = 'AUTO' . time() . rand(100,999);
                    $expiry = date('Y-m-d', strtotime('+1 year'));

                    $insert_med->execute([$med_name, 'General', $batch_no, $qty_stock, $buy_price, $sell_price, $expiry]);
                    $mid = $conn->lastInsertId();
                } else {
                    $mid = $found['id'];
                }

                $item_stmt->execute([$sale_id, $mid, $item['qty'], $item['price']]);
                $stock_stmt->execute([$item['qty'], $mid]);
            }
            
            $conn->commit();
            $message = "<div class='alert alert-success d-flex justify-content-between align-items-center'>
                            <span>Sale completed successfully! Sale ID: #$sale_id</span>
                            <a href='generate_receipt.php?id=$sale_id' target='_blank' class='btn btn-sm btn-light'><i class='fas fa-print'></i> Print Receipt</a>
                        </div>";
            // Notify other tabs (dashboard) to refresh charts
            $message .= "<script>try{localStorage.setItem('sale_completed', JSON.stringify({id: $sale_id, time: Date.now()}));}catch(e){};</script>";
        } catch (Exception $e) {
            $conn->rollBack();
            $message = "<div class='alert alert-danger'>Error processing sale: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch Medicines for selection
$medicines = [];
try {
    $stmt = $conn->query("SELECT * FROM medicines WHERE quantity > 0 AND (is_active = 1 OR is_active IS NULL) ORDER BY name ASC");
    $medicines = $stmt->fetchAll();
} catch (PDOException $e) {}

// Dummy medicines for POS if DB empty
if (empty($medicines)) {
    $medicines = [
        ['id'=>1,'name'=>'Paracetamol','batch_no'=>'B001','quantity'=>50,'selling_price'=>30.00],
        ['id'=>2,'name'=>'Amoxicillin','batch_no'=>'A123','quantity'=>25,'selling_price'=>75.00]
    ];
}

// Fetch Patients for selection
$customers = [];
try {
    $stmt = $conn->query("SELECT id, first_name, last_name FROM patients ORDER BY first_name ASC");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {}

if (empty($customers)) {
    $customers = [
        ['id'=>1,'first_name'=>'John','last_name'=>'Kamau'],
        ['id'=>2,'first_name'=>'Jane','last_name'=>'Wangari']
    ];
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">New Sale</h1>
    </div>

    <?php echo $message; ?>

    <div class="row">
        <!-- Medicine Selection -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Select Medicines</h6>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" placeholder="Search medicine..." id="medicineSearch">
                    </div>
                    
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover align-middle" id="posMedicineTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Medicine</th>
                                    <th>Stock</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($medicines)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No medicines in stock.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($medicines as $med): ?>
                                    <tr class="<?php echo ($med['quantity'] < 10) ? 'table-warning' : ''; ?>">
                                        <td>
                                            <div class="fw-bold"><?php echo $med['name']; ?></div>
                                            <small class="text-muted">Batch: <?php echo $med['batch_no']; ?></small>
                                        </td>
                                        <td><?php echo $med['quantity']; ?></td>
                                        <td><?php echo formatKSh($med['selling_price']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary add-to-cart" 
                                                    data-id="<?php echo $med['id']; ?>" 
                                                    data-name="<?php echo $med['name']; ?>" 
                                                    data-price="<?php echo $med['selling_price']; ?>" 
                                                    data-stock="<?php echo $med['quantity']; ?>">
                                                Add
                                            </button>
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

        <!-- Cart and Checkout -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow sticky-top" style="top: 80px;">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Current Cart</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST" id="checkoutForm">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Customer</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" placeholder="Search or select customer..." id="customerSearch">
                            </div>
                            <div class="list-group" id="customerList" style="max-height: 200px; overflow-y: auto; display: none;">
                                <button type="button" class="list-group-item list-group-item-action" data-customer-id="" data-customer-name="Walk-in Customer">
                                    Walk-in Customer
                                </button>
                                <?php foreach ($customers as $c): ?>
                                    <button type="button" class="list-group-item list-group-item-action" data-customer-id="<?php echo $c['id']; ?>" data-customer-name="<?php echo $c['first_name'] . " " . $c['last_name']; ?>">
                                        <?php echo $c['first_name'] . " " . $c['last_name']; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="customer_id" id="customerIdInput" value="">
                            <div id="selectedCustomerDisplay" class="small text-muted mt-2">Selected: Walk-in Customer</div>
                        </div>

                        <div id="cartItems" class="mb-3" style="min-height: 150px; max-height: 300px; overflow-y: auto;">
                            <p class="text-center text-muted py-4">Cart is empty</p>
                        </div>
                        
                        <div class="border-top pt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <span id="subtotal" class="fw-bold">KSh 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="h5 mb-0">Total:</span>
                                <span id="total" class="h5 mb-0 text-danger fw-bold">KSh 0.00</span>
                            </div>
                            
                            <input type="hidden" name="cart_data" id="cartDataInput">
                            <input type="hidden" name="total_amount" id="totalAmountInput">
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Payment Method</label>
                                <select class="form-select" name="payment_method" id="paymentMethod">
                                    <option value="Cash">Cash</option>
                                    <option value="M-Pesa">M-Pesa / Mobile Money</option>
                                    <option value="Card">Credit/Debit Card</option>
                                </select>
                            </div>
                            
                            <button type="submit" name="complete_sale" class="btn btn-danger w-100 py-2 fw-bold" id="checkoutBtn" disabled>
                                <i class="fas fa-check-circle me-2"></i> COMPLETE SALE
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let cart = [];
    const cartItemsContainer = document.getElementById('cartItems');
    const subtotalEl = document.getElementById('subtotal');
    const totalEl = document.getElementById('total');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const cartDataInput = document.getElementById('cartDataInput');
    const totalAmountInput = document.getElementById('totalAmountInput');
    const medicineSearch = document.getElementById('medicineSearch');
    const customerSearch = document.getElementById('customerSearch');
    const customerList = document.getElementById('customerList');
    const customerIdInput = document.getElementById('customerIdInput');
    const selectedCustomerDisplay = document.getElementById('selectedCustomerDisplay');

    // Search functionality for medicines
    medicineSearch.addEventListener('keyup', function() {
        const value = this.value.toLowerCase();
        const rows = document.querySelectorAll('#posMedicineTable tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(value) ? '' : 'none';
        });
    });

    // Customer search functionality
    customerSearch.addEventListener('focus', function() {
        customerList.style.display = 'block';
    });

    customerSearch.addEventListener('keyup', function() {
        const value = this.value.toLowerCase();
        const buttons = customerList.querySelectorAll('button');
        let anyVisible = false;
        
        buttons.forEach(btn => {
            const text = btn.textContent.toLowerCase();
            if (text.includes(value)) {
                btn.style.display = '';
                anyVisible = true;
            } else {
                btn.style.display = 'none';
            }
        });
    });

    // Customer selection
    customerList.addEventListener('click', function(e) {
        if (e.target.tagName === 'BUTTON') {
            e.preventDefault();
            const customerId = e.target.dataset.customerId;
            const customerName = e.target.dataset.customerName;
            
            customerIdInput.value = customerId;
            customerSearch.value = customerName;
            selectedCustomerDisplay.textContent = 'Selected: ' + customerName;
            customerList.style.display = 'none';
        }
    });

    // Hide customer list when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== customerSearch && e.target !== customerList && !customerList.contains(e.target)) {
            customerList.style.display = 'none';
        }
    });

    // Add to cart
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const price = parseFloat(this.dataset.price);
            const stock = parseInt(this.dataset.stock);

            const existing = cart.find(item => item.id === id);
            if (existing) {
                if (existing.qty < stock) {
                    existing.qty++;
                } else {
                    alert('Not enough stock!');
                }
            } else {
                cart.push({ id, name, price, qty: 1, stock });
            }
            renderCart();
        });
    });

    function renderCart() {
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = '<p class="text-center text-muted py-4">Cart is empty</p>';
            checkoutBtn.disabled = true;
            subtotalEl.innerText = 'KSh 0.00';
            totalEl.innerText = 'KSh 0.00';
            return;
        }

        let html = '<ul class="list-group list-group-flush">';
        let total = 0;

        cart.forEach((item, index) => {
            const sub = item.price * item.qty;
            total += sub;
            html += `
                <li class="list-group-item px-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div style="max-width: 60%;">
                            <div class="fw-bold small">${item.name}</div>
                            <small class="text-muted">KSh ${item.price.toLocaleString()} x ${item.qty}</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="fw-bold me-2 small">KSh ${sub.toLocaleString()}</span>
                            <button type="button" class="btn btn-sm text-danger remove-item" data-index="${index}"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </li>
            `;
        });

        html += '</ul>';
        cartItemsContainer.innerHTML = html;
        
        subtotalEl.innerText = 'KSh ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
        totalEl.innerText = 'KSh ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
        
        cartDataInput.value = JSON.stringify(cart);
        totalAmountInput.value = total;
        checkoutBtn.disabled = false;

        // Remove item event
        document.querySelectorAll('.remove-item').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.dataset.index;
                cart.splice(index, 1);
                renderCart();
            });
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>