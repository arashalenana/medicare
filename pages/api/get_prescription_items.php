<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$prescription_id = $_GET['prescription_id'] ?? null;

if (!$prescription_id) {
    echo json_encode(['error' => 'Prescription ID is required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT pi.*, m.name as medicine_name 
        FROM prescription_items pi 
        JOIN medicines m ON pi.medicine_id = m.id 
        WHERE pi.prescription_id = ?
    ");
    $stmt->execute([$prescription_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo '<div class="alert alert-info">No items found for this prescription.</div>';
    } else {
        echo '<div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Quantity</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($items as $item) {
            echo '<tr>
                    <td>' . htmlspecialchars($item['medicine_name']) . '</td>
                    <td>' . htmlspecialchars($item['quantity']) . '</td>
                    <td>' . htmlspecialchars($item['dosage']) . '</td>
                    <td>' . htmlspecialchars($item['frequency']) . '</td>
                    <td>' . htmlspecialchars($item['duration_days']) . ' days</td>
                  </tr>';
        }
        echo '    </tbody>
                </table>
              </div>';
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error fetching items: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
