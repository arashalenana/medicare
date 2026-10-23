<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../config/paths.php';
require_once '../config/db.php';

if (!isset($_GET['id'])) die("Sale ID is required.");

$sale_id = (int)$_GET['id'];

// A4 or thermal? default = thermal (80mm)
$mode = $_GET['mode'] ?? 'thermal';

try {
    $stmt = $conn->prepare("SELECT s.*, p.first_name, p.last_name, u.username as staff_name
                            FROM sales s
                            LEFT JOIN patients p ON s.patient_id = p.id
                            JOIN users u ON s.user_id = u.id
                            WHERE s.id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();
    if (!$sale) die("Sale not found.");

    $stmt = $conn->prepare("SELECT si.*, m.name as medicine_name
                            FROM sale_items si
                            JOIN medicines m ON si.medicine_id = m.id
                            WHERE si.sale_id = ?");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$is_thermal = ($mode === 'thermal');
$receipt_width = $is_thermal ? '80mm' : '210mm';
$font_size     = $is_thermal ? '11px' : '13px';
$padding       = $is_thermal ? '4mm' : '15mm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo str_pad($sale_id, 5, '0', STR_PAD_LEFT); ?> - Family Medicare</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: <?php echo $font_size; ?>;
            background: #f0f0f0;
            color: #000;
            padding: 20px;
        }

        .receipt-wrap {
            width: <?php echo $receipt_width; ?>;
            margin: 0 auto 20px;
            background: #fff;
            padding: <?php echo $padding; ?>;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
            border: 1px solid #ddd;
        }

        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .header img {
            width: <?php echo $is_thermal ? '48px' : '64px'; ?>;
            margin-bottom: 4px;
        }

        .header .clinic-name {
            font-size: <?php echo $is_thermal ? '12px' : '15px'; ?>;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header .clinic-sub {
            font-size: <?php echo $is_thermal ? '9px' : '11px'; ?>;
            line-height: 1.4;
            color: #333;
            margin-top: 2px;
        }

        .info-block {
            margin-bottom: 8px;
            font-size: <?php echo $is_thermal ? '10px' : '12px'; ?>;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
        }

        .info-row .label { font-weight: bold; }

        .divider {
            border: none;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: <?php echo $is_thermal ? '10px' : '12px'; ?>;
            margin-bottom: 6px;
        }

        thead th {
            border-bottom: 1px solid #000;
            padding: 3px 2px;
            text-align: left;
            font-weight: bold;
        }

        thead th:nth-child(2) { text-align: center; }
        thead th:nth-child(3) { text-align: right; }

        tbody td {
            padding: 3px 2px;
            vertical-align: top;
        }

        tbody td:nth-child(2) { text-align: center; }
        tbody td:nth-child(3) { text-align: right; }

        .totals {
            border-top: 1px dashed #000;
            padding-top: 6px;
            font-size: <?php echo $is_thermal ? '10px' : '12px'; ?>;
        }

        .total-grand {
            display: flex;
            justify-content: space-between;
            font-size: <?php echo $is_thermal ? '13px' : '15px'; ?>;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .total-sub {
            display: flex;
            justify-content: space-between;
            font-size: <?php echo $is_thermal ? '9px' : '11px'; ?>;
            color: #444;
        }

        .footer {
            text-align: center;
            border-top: 1px dashed #000;
            padding-top: 6px;
            margin-top: 8px;
            font-size: <?php echo $is_thermal ? '9px' : '10px'; ?>;
            line-height: 1.5;
        }

        /* ── Screen actions (hidden on print) ── */
        .screen-actions {
            width: <?php echo $receipt_width; ?>;
            margin: 0 auto;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            padding: 11px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-thermal { background: #ED1C24; color: #fff; }
        .btn-a4      { background: #00AEDB; color: #fff; }
        .btn-back    { background: #333;    color: #fff; }

        /* ── Print styles ── */
        @media print {
            body { background: none; padding: 0; }
            .receipt-wrap {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: <?php echo $is_thermal ? '2mm' : '8mm'; ?>;
                width: 100%;
            }
            .screen-actions { display: none; }
            <?php if ($is_thermal): ?>
            @page { size: 80mm auto; margin: 0; }
            <?php else: ?>
            @page { size: A4; margin: 10mm; }
            <?php endif; ?>
        }
    </style>
</head>
<body>

<div class="receipt-wrap">
    <div class="header">
        <img src="../assets/images/favicon.png" alt="Logo">
        <div class="clinic-name">Family Medicare</div>
        <div class="clinic-sub">
            Clinic &amp; Laboratory<br>
            Quality Healthcare · Nairobi, Kenya<br>
            Email: fammedicare810@gmail.com
        </div>
    </div>

    <div class="info-block">
        <div class="info-row"><span class="label">Receipt No:</span><span>#<?php echo str_pad($sale_id, 5, '0', STR_PAD_LEFT); ?></span></div>
        <div class="info-row"><span class="label">Date:</span><span><?php echo date('d/m/Y H:i', strtotime($sale['created_at'])); ?></span></div>
        <div class="info-row"><span class="label">Cashier:</span><span><?php echo htmlspecialchars($sale['staff_name']); ?></span></div>
        <div class="info-row">
            <span class="label">Patient:</span>
            <span><?php echo !empty($sale['patient_id']) ? htmlspecialchars(trim(($sale['first_name'] ?? '') . ' ' . ($sale['last_name'] ?? ''))) : 'Walk-in'; ?></span>
        </div>
    </div>

    <hr class="divider">

    <table>
        <thead>
            <tr>
                <th>Item / Service</th>
                <th>Qty</th>
                <th>KSh</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $has_items    = !empty($items);
            $drug_sales   = floatval($sale['drug_sales']     ?? 0);
            $lab_fee      = floatval($sale['lab_fee']        ?? 0);
            $med_fee      = floatval($sale['medication_fee'] ?? 0);
            ?>

            <?php if ($has_items): ?>
                <!-- Drug Sales section header -->
                <tr style="background:#f8f8f8;">
                    <td colspan="3" style="font-weight:bold; font-size:<?php echo $is_thermal ? '10px' : '12px'; ?>; padding:4px 2px; letter-spacing:0.04em;">
                        ── Drug Sales ──
                    </td>
                </tr>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td style="padding-left:6px;"><?php echo htmlspecialchars($item['medicine_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>

            <?php elseif ($drug_sales > 0): ?>
                <!-- No sale_items but drug_sales amount exists (billing-only) -->
                <tr>
                    <td>Drug Sales</td>
                    <td>1</td>
                    <td><?php echo number_format($drug_sales, 2); ?></td>
                </tr>
            <?php endif; ?>

            <?php if ($med_fee > 0): ?>
            <tr>
                <td>Medication Fee</td>
                <td>1</td>
                <td><?php echo number_format($med_fee, 2); ?></td>
            </tr>
            <?php endif; ?>

            <?php if ($lab_fee > 0): ?>
            <tr>
                <td>Lab Fee</td>
                <td>1</td>
                <td><?php echo number_format($lab_fee, 2); ?></td>
            </tr>
            <?php endif; ?>

            <?php if (!$has_items && $drug_sales <= 0 && $med_fee <= 0 && $lab_fee <= 0): ?>
            <tr>
                <td colspan="3" style="text-align:center; color:#888; padding:6px 0;">No items recorded</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="total-grand">
            <span>TOTAL:</span>
            <span>KSh <?php echo number_format($sale['total_amount'], 2); ?></span>
        </div>
        <?php if (isset($sale['amount_paid']) && floatval($sale['amount_paid']) > 0): ?>
        <div class="total-sub">
            <span>Amount Paid:</span>
            <span>KSh <?php echo number_format($sale['amount_paid'], 2); ?></span>
        </div>
        <?php if (floatval($sale['balance'] ?? 0) > 0): ?>
        <div class="total-sub" style="font-weight:bold; color:#c00;">
            <span>Balance Due:</span>
            <span>KSh <?php echo number_format($sale['balance'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <div class="total-sub">
            <span>Payment:</span>
            <span><?php echo htmlspecialchars($sale['payment_method']); ?></span>
        </div>
        <div class="total-sub">
            <span>Status:</span>
            <span><?php echo htmlspecialchars($sale['payment_status']); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for choosing Family Medicare!<br>
        Get well soon. 🌿<br>
        *** Keep receipt for reference ***</p>
    </div>
</div>

<div class="screen-actions">
    <button onclick="window.print()" class="btn btn-thermal">🖨️ Print (Thermal)</button>
    <a href="?id=<?php echo $sale_id; ?>&mode=a4" class="btn btn-a4">📄 A4 Version</a>
    <a href="billing.php" class="btn btn-back">← Back</a>
</div>

<?php if ($is_thermal): ?>
<script>
    // Auto-trigger print dialog for thermal printers
    window.addEventListener('load', function() {
        // Small delay to allow styles to render
        setTimeout(function() {
            if (window.location.search.includes('autoprint=1')) {
                window.print();
            }
        }, 400);
    });
</script>
<?php endif; ?>

</body>
</html>