<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

try {
    // Build last 7 days dates
    $dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $dates[] = date('Y-m-d', strtotime("-{$i} days"));
    }

    // Fetch totals grouped by date
    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $stmt = $conn->prepare("SELECT DATE(created_at) as date, SUM(total_amount) as total FROM sales WHERE DATE(created_at) IN ($placeholders) GROUP BY DATE(created_at)");
    $stmt->execute($dates);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalsByDate = [];
    foreach ($rows as $r) {
        $totalsByDate[$r['date']] = (float)$r['total'];
    }

    $labels = [];
    $values = [];
    foreach ($dates as $d) {
        $labels[] = date('D', strtotime($d));
        $values[] = isset($totalsByDate[$d]) ? $totalsByDate[$d] : 0.0;
    }

    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
