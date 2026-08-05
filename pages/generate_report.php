<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/paths.php';
require_once '../config/db.php';
require_once '../config/rbac.php';

// Reports are Admin only
requireAdmin('Only Admins can generate reports.');

date_default_timezone_set('Africa/Nairobi');

$type = $_GET['type'] ?? 'sales';
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$report_title = 'Report';
$rows = [];

try {
    if ($type === 'sales') {
        $report_title = 'Sales Report';
        $stmt = $conn->prepare("SELECT s.*, p.first_name, p.last_name, u.username as staff_name
                                FROM sales s
                                LEFT JOIN patients p ON s.patient_id = p.id
                                JOIN users u ON s.user_id = u.id
                                WHERE DATE(s.created_at) BETWEEN ? AND ? AND u.role IN ('Admin', 'Cashier')
                                ORDER BY s.created_at DESC");
        $stmt->execute([$start_date, $end_date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($type === 'low_stock') {
        $report_title = 'Low Stock Report';
        $stmt = $conn->query("SELECT name, category, quantity, selling_price, expiry_date
                              FROM medicines WHERE quantity < 10 ORDER BY quantity ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($type === 'expiry') {
        $report_title = 'Expiry Report';
        $stmt = $conn->query("SELECT name, category, quantity, selling_price, expiry_date
                              FROM medicines
                              WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                              ORDER BY expiry_date ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $rows = [];
}

$grand_total = 0;
if ($type === 'sales') {
    foreach ($rows as $r) { $grand_total += $r['total_amount']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report_title); ?> - Family Medicare</title>
    <link rel="icon" type="image/png" href="<?php echo $asset_path; ?>images/favicon.png">
    <style>
        :root { --accent-red: #ED1C24; --accent-blue: #00AEDB; --logo-black: #000000; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #222; background: #f4f4f4; padding: 20px; }
        .sheet { background: #fff; max-width: 850px; margin: 0 auto 20px; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border: 1px solid #ddd; }
        .header { text-align: center; border-bottom: 2px solid var(--accent-red); padding-bottom: 14px; margin-bottom: 18px; }
        .header img { width: 64px; margin-bottom: 6px; }
        .header h1 { font-size: 18px; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 0.5px; }
        .header p { font-size: 11px; color: #666; margin-top: 2px; }
        .meta { display: flex; justify-content: space-between; font-size: 12px; color: #444; margin-bottom: 16px; flex-wrap: wrap; gap: 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
        th { background: #f8f9fa; text-align: left; padding: 8px 6px; border-bottom: 2px solid #ddd; font-weight: 700; }
        td { padding: 7px 6px; border-bottom: 1px solid #eee; }
        td.num, th.num { text-align: right; }
        .totals { text-align: right; font-size: 14px; font-weight: 700; color: var(--accent-red); margin-bottom: 10px; }
        .footer { text-align: center; font-size: 10px; color: #888; border-top: 1px dashed #ccc; padding-top: 10px; }
        .no-print-actions { text-align: center; display: flex; justify-content: center; gap: 10px; margin: 0 auto; max-width: 850px; }
        .btn { padding: 12px 24px; border: none; cursor: pointer; border-radius: 6px; font-weight: 700; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-print { background: var(--accent-blue); color: #fff; }
        .btn-back { background: #333; color: #fff; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; }
        .badge-danger { background: #fdecea; color: var(--accent-red); }
        .badge-warning { background: #fff8e1; color: #b8860b; }
        @media print {
            body { background: none; padding: 0; }
            .sheet { box-shadow: none; border: none; max-width: none; margin: 0; }
            .no-print-actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="header">
            <img src="<?php echo $asset_path; ?>images/favicon.png" alt="Logo">
            <h1>Family Medicare Clinic &amp; Laboratory</h1>
            <p><?php echo htmlspecialchars($report_title); ?></p>
        </div>

        <div class="meta">
            <span><strong>Generated:</strong> <?php echo date('d/m/Y H:i'); ?></span>
            <?php if ($type === 'sales'): ?>
                <span><strong>Period:</strong> <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></span>
            <?php endif; ?>
            <span><strong>By:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
        </div>

        <?php if ($type === 'sales'): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Staff</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th class="num">Amount (KSh)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:20px; color:#888;">No sales found for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>#S-<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $r['patient_id'] ? htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) : 'Walk-in'; ?></td>
                            <td><?php echo htmlspecialchars($r['staff_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['payment_method']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></td>
                            <td class="num"><?php echo number_format($r['total_amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!empty($rows)): ?>
                <div class="totals">Total: KSh <?php echo number_format($grand_total, 2); ?> &nbsp;|&nbsp; <?php echo count($rows); ?> Transaction(s)</div>
            <?php endif; ?>

        <?php elseif ($type === 'low_stock' || $type === 'expiry'): ?>
            <table>
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th class="num">Quantity</th>
                        <th class="num">Unit Price (KSh)</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:20px; color:#888;">No items found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $is_expired = strtotime($r['expiry_date']) < strtotime('today');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo htmlspecialchars($r['category'] ?? ''); ?></td>
                            <td class="num"><?php echo (int)$r['quantity']; ?></td>
                            <td class="num"><?php echo number_format($r['selling_price'], 2); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($r['expiry_date'])); ?></td>
                            <td>
                                <?php if ($type === 'low_stock'): ?>
                                    <span class="badge badge-danger">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge <?php echo $is_expired ? 'badge-danger' : 'badge-warning'; ?>"><?php echo $is_expired ? 'Expired' : 'Expiring Soon'; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!empty($rows)): ?>
                <div class="totals" style="color:#444;"><?php echo count($rows); ?> Item(s)</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="footer">
            <p>Family Medicare Clinic &amp; Laboratory &mdash; Internal Report. Confidential.</p>
        </div>
    </div>

    <div class="no-print-actions">
        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> Print / Save as PDF</button>
        <a href="reports_enhanced.php" class="btn btn-back">Back to Reports</a>
    </div>
</body>
</html>