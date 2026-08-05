<?php
require_once __DIR__ . '/../config/db.php';

// This script inserts dummy patients and medicines for testing.
// Run from command line: php database/seed_dummy.php

try {
    $conn->beginTransaction();

    // Ensure 10 patients
    $stmt = $conn->query("SELECT COUNT(*) FROM patients");
    $count = (int)$stmt->fetchColumn();
    $needed = max(0, 10 - $count);

    if ($needed > 0) {
        $pstmt = $conn->prepare("INSERT INTO patients (first_name, last_name, date_of_birth, gender, phone, email, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        for ($i = 1; $i <= $needed; $i++) {
            $idx = $count + $i;
            $first = "DemoFirst" . $idx;
            $last = "DemoLast" . $idx;
            $dob = date('Y-m-d', strtotime('-' . (20 + ($idx % 30)) . ' years'));
            $gender = ($idx % 2) ? 'Male' : 'Female';
            $phone = '07' . str_pad((string)(10000000 + $idx), 8, '0', STR_PAD_LEFT);
            $email = strtolower($first) . "." . strtolower($last) . "@example.com";
            $pstmt->execute([$first, $last, $dob, $gender, $phone, $email]);
        }
        echo "Inserted $needed patients.\n";
    } else {
        echo "Patients: OK ($count present).\n";
    }

    // Ensure 60 medicines
    $stmt = $conn->query("SELECT COUNT(*) FROM medicines");
    $mcount = (int)$stmt->fetchColumn();
    $mneeded = max(0, 60 - $mcount);

    if ($mneeded > 0) {
        $mstmt = $conn->prepare("INSERT INTO medicines (name, category, batch_no, quantity, buying_price, selling_price, expiry_date, dosage, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $categories = ['Analgesics','Antibiotics','Antihistamines','Antacids','Antihypertensives','Antidiabetics','Respiratory','Vitamins','Dermatology','Cardiology'];

        for ($i = 1; $i <= $mneeded; $i++) {
            $idx = $mcount + $i;
            $name = "DemoMed " . $idx;
            $cat = $categories[$idx % count($categories)];
            $batch = 'DM' . str_pad($idx, 4, '0', STR_PAD_LEFT);
            $qty = rand(10, 300);
            $buy = rand(50, 500) / 10.0; // 5.0 - 50.0
            $sell = round($buy * (1 + (0.3 + rand(0,40)/100)), 2); // 30-70% margin
            $expiry = date('Y-m-d', strtotime('+' . rand(180, 1460) . ' days')); // 6 months - 4 years
            $dosage = (rand(1,3)*100) . 'mg';
            $mstmt->execute([$name, $cat, $batch, $qty, $buy, $sell, $expiry, $dosage]);
        }
        echo "Inserted $mneeded medicines.\n";
    } else {
        echo "Medicines: OK ($mcount present).\n";
    }

    $conn->commit();
    echo "Seeding complete.\n";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Error seeding data: " . $e->getMessage() . "\n";
    exit(1);
}
