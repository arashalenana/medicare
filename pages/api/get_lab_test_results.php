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

$lab_test_id = $_GET['lab_test_id'] ?? null;

if (!$lab_test_id) {
    echo json_encode(['error' => 'Lab Test ID is required']);
    exit();
}

try {
    // Fetch test details
    $stmt = $conn->prepare("
        SELECT lt.*, p.first_name, p.last_name 
        FROM lab_tests lt 
        JOIN patients p ON lt.patient_id = p.id 
        WHERE lt.id = ?
    ");
    $stmt->execute([$lab_test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        echo '<div class="alert alert-danger">Lab test not found.</div>';
        exit();
    }

    // Fetch results
    $stmt = $conn->prepare("SELECT * FROM lab_test_results WHERE lab_test_id = ? ORDER BY result_date DESC");
    $stmt->execute([$lab_test_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<div class="mb-3">
            <strong>Patient:</strong> ' . htmlspecialchars($test['first_name'] . ' ' . $test['last_name']) . '<br>
            <strong>Test:</strong> ' . htmlspecialchars($test['test_name']) . ' (' . htmlspecialchars($test['test_category']) . ')<br>
            <strong>Date:</strong> ' . ($test['test_date'] ? date('M d, Y H:i', strtotime($test['test_date'])) : 'N/A') . '
          </div>';

    if (empty($results)) {
        echo '<div class="alert alert-info">No results recorded for this test yet.</div>';
    } else {
        echo '<div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Result Value</th>
                            <th>Unit</th>
                            <th>Reference Range</th>
                            <th>Status</th>
                            <th>Result Date</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($results as $result) {
            $status_class = $result['result_status'] === 'Normal' ? 'success' : ($result['result_status'] === 'Critical' ? 'danger' : 'warning');
            echo '<tr>
                    <td>' . htmlspecialchars($result['result_value']) . '</td>
                    <td>' . htmlspecialchars($result['result_unit']) . '</td>
                    <td>' . htmlspecialchars($result['reference_range']) . '</td>
                    <td><span class="badge bg-' . $status_class . '">' . htmlspecialchars($result['result_status']) . '</span></td>
                    <td>' . ($result['result_date'] ? date('M d, Y H:i', strtotime($result['result_date'])) : 'N/A') . '</td>
                  </tr>';
        }
        echo '    </tbody>
                </table>
              </div>';
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error fetching results: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
