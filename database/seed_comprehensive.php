<?php
require_once __DIR__ . '/../config/db.php';

// Comprehensive seeding script for all tables
// Run from command line: php database/seed_comprehensive.php

try {
    $conn->beginTransaction();

    // ===== PATIENTS =====
    $stmt = $conn->query("SELECT COUNT(*) FROM patients");
    $count = (int)$stmt->fetchColumn();
    $needed = max(0, 30 - $count);

    if ($needed > 0) {
        $pstmt = $conn->prepare("INSERT INTO patients (first_name, last_name, date_of_birth, gender, phone, email, id_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $first_names = ['John', 'Mary', 'David', 'Sarah', 'Michael', 'Emily', 'James', 'Jessica', 'Robert', 'Linda', 'William', 'Elizabeth', 'Charles', 'Jennifer', 'Joseph', 'Patricia', 'Thomas', 'Barbara', 'Christopher', 'Susan', 'Daniel', 'Jessica', 'Matthew', 'Karen', 'Anthony', 'Nancy', 'Mark', 'Lisa', 'Donald', 'Betty'];
        $last_names = ['Kamau', 'Wanjiku', 'Ochieng', 'Achieng', 'Njoroge', 'Muthoni', 'Kipkorir', 'Nyambura', 'Mutua', 'Khadija', 'Ouma', 'Moraa', 'Kipchoge', 'Kariuki', 'Mwangi', 'Kiplagat', 'Kipkemboi', 'Kipchoge', 'Kipketer', 'Kiplagat', 'Kipchoge', 'Kipkemboi', 'Kiplagat', 'Kipketer', 'Kiplagat', 'Kipchoge', 'Kipkemboi', 'Kiplagat', 'Kipketer', 'Kiplagat'];

        for ($i = 1; $i <= $needed; $i++) {
            $idx = $count + $i;
            $first = $first_names[($idx - 1) % count($first_names)];
            $last = $last_names[($idx - 1) % count($last_names)];
            $dob = date('Y-m-d', strtotime('-' . (20 + ($idx % 50)) . ' years'));
            $gender = ($idx % 2) ? 'Male' : 'Female';
            $phone = '07' . str_pad((string)(10000000 + $idx), 8, '0', STR_PAD_LEFT);
            $email = strtolower($first) . "." . strtolower($last) . "@email.com";
            $id_number = str_pad((string)(10000000 + $idx), 8, '0', STR_PAD_LEFT);
            $pstmt->execute([$first, $last, $dob, $gender, $phone, $email, $id_number]);
        }
        echo "Inserted $needed patients.\n";
    } else {
        echo "Patients: OK ($count present).\n";
    }

    // ===== MEDICINES =====
    $stmt = $conn->query("SELECT COUNT(*) FROM medicines");
    $mcount = (int)$stmt->fetchColumn();
    $mneeded = max(0, 50 - $mcount);

    if ($mneeded > 0) {
        $mstmt = $conn->prepare("INSERT INTO medicines (name, category, batch_no, quantity, buying_price, selling_price, expiry_date, dosage, manufacturer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $categories = ['Analgesics','Antibiotics','Antihistamines','Antacids','Antihypertensives','Antidiabetics','Respiratory','Vitamins','Dermatology','Cardiology'];
        $manufacturers = ['Pfizer', 'Novartis', 'Merck', 'Johnson & Johnson', 'AbbVie', 'Roche', 'Amgen', 'Bristol-Myers Squibb', 'Eli Lilly', 'Sanofi'];

        for ($i = 1; $i <= $mneeded; $i++) {
            $idx = $mcount + $i;
            $name = "Medicine " . $idx;
            $cat = $categories[$idx % count($categories)];
            $batch = 'B' . str_pad($idx, 5, '0', STR_PAD_LEFT);
            $qty = rand(20, 500);
            $buy = rand(50, 500) / 10.0;
            $sell = round($buy * (1 + (0.3 + rand(0,40)/100)), 2);
            $expiry = date('Y-m-d', strtotime('+' . rand(180, 1460) . ' days'));
            $dosage = (rand(1,4)*100) . 'mg';
            $manufacturer = $manufacturers[$idx % count($manufacturers)];
            $mstmt->execute([$name, $cat, $batch, $qty, $buy, $sell, $expiry, $dosage, $manufacturer]);
        }
        echo "Inserted $mneeded medicines.\n";
    } else {
        echo "Medicines: OK ($mcount present).\n";
    }

    // ===== CONSULTATIONS =====
    $stmt = $conn->query("SELECT COUNT(*) FROM consultations");
    $ccount = (int)$stmt->fetchColumn();
    $cneeded = max(0, 50 - $ccount);

    if ($cneeded > 0) {
        $cstmt = $conn->prepare("INSERT INTO consultations (patient_id, doctor_name, consultation_date, reason, diagnosis, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        $doctors = ['Dr. James Kariuki', 'Dr. Sarah Kipchoge', 'Dr. Peter Mwangi', 'Dr. Alice Kiplagat', 'Dr. Robert Kipkemboi'];
        $reasons = ['Routine checkup', 'Flu symptoms', 'Chest pain', 'Diabetes follow-up', 'Allergy check', 'Blood pressure monitoring', 'Headache', 'Stomach pain', 'Fever', 'Cough'];
        $diagnoses = ['Hypertension', 'Common Cold', 'Anxiety', 'Type 2 Diabetes', 'Seasonal Allergies', 'Migraine', 'Gastritis', 'Bronchitis', 'Influenza', 'Asthma'];
        $statuses = ['Completed', 'Completed', 'Completed', 'Pending', 'Completed'];

        $stmt_patients = $conn->query("SELECT id FROM patients ORDER BY RAND() LIMIT 25");
        $patients = $stmt_patients->fetchAll(PDO::FETCH_COLUMN);

        for ($i = 1; $i <= $cneeded; $i++) {
            $patient_id = $patients[$i % count($patients)];
            $doctor = $doctors[$i % count($doctors)];
            $date = date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days ' . rand(8, 17) . ':00:00'));
            $reason = $reasons[$i % count($reasons)];
            $diagnosis = $diagnoses[$i % count($diagnoses)];
            $status = $statuses[$i % count($statuses)];
            $cstmt->execute([$patient_id, $doctor, $date, $reason, $diagnosis, $status]);
        }
        echo "Inserted $cneeded consultations.\n";
    } else {
        echo "Consultations: OK ($ccount present).\n";
    }

    // ===== CONSULTATION CHARGES =====
    $stmt = $conn->query("SELECT COUNT(*) FROM consultation_charges");
    $chcount = (int)$stmt->fetchColumn();
    $chneeded = max(0, 50 - $chcount);

    if ($chneeded > 0) {
        $chstmt = $conn->prepare("INSERT INTO consultation_charges (consultation_id, charge_amount, charge_type, created_at) VALUES (?, ?, ?, NOW())");
        
        $stmt_cons = $conn->query("SELECT id FROM consultations ORDER BY RAND() LIMIT 50");
        $consultations = $stmt_cons->fetchAll(PDO::FETCH_COLUMN);

        for ($i = 1; $i <= $chneeded; $i++) {
            $consultation_id = $consultations[$i % count($consultations)];
            $charge = rand(300, 1000);
            $type = 'Consultation Fee';
            $chstmt->execute([$consultation_id, $charge, $type]);
        }
        echo "Inserted $chneeded consultation charges.\n";
    } else {
        echo "Consultation Charges: OK ($chcount present).\n";
    }

    // ===== LAB TESTS =====
    $stmt = $conn->query("SELECT COUNT(*) FROM lab_tests");
    $lcount = (int)$stmt->fetchColumn();
    $lneeded = max(0, 40 - $lcount);

    if ($lneeded > 0) {
        $lstmt = $conn->prepare("INSERT INTO lab_tests (patient_id, consultation_id, test_name, test_category, test_date, test_charge, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $test_names = ['Blood Pressure Test', 'Full Blood Count', 'ECG', 'Fasting Blood Sugar', 'Allergy Panel', 'Lipid Panel', 'Liver Function Test', 'Kidney Function Test', 'Thyroid Function Test', 'Urinalysis'];
        $test_categories = ['Vital Signs', 'Hematology', 'Cardiology', 'Biochemistry', 'Immunology', 'Hematology', 'Biochemistry', 'Biochemistry', 'Endocrinology', 'Urinalysis'];
        $statuses = ['Completed', 'Completed', 'Completed', 'Pending', 'Completed'];

        $stmt_patients = $conn->query("SELECT id FROM patients ORDER BY RAND() LIMIT 30");
        $patients = $stmt_patients->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt_cons = $conn->query("SELECT id FROM consultations ORDER BY RAND() LIMIT 30");
        $consultations = $stmt_cons->fetchAll(PDO::FETCH_COLUMN);

        for ($i = 1; $i <= $lneeded; $i++) {
            $patient_id = $patients[$i % count($patients)];
            $consultation_id = $consultations[$i % count($consultations)];
            $test_name = $test_names[$i % count($test_names)];
            $test_category = $test_categories[$i % count($test_categories)];
            $date = date('Y-m-d H:i:s', strtotime('-' . rand(1, 20) . ' days ' . rand(8, 17) . ':00:00'));
            $charge = rand(500, 3000);
            $status = $statuses[$i % count($statuses)];
            $lstmt->execute([$patient_id, $consultation_id, $test_name, $test_category, $date, $charge, $status]);
        }
        echo "Inserted $lneeded lab tests.\n";
    } else {
        echo "Lab Tests: OK ($lcount present).\n";
    }

    // ===== LAB TEST RESULTS =====
    $stmt = $conn->query("SELECT COUNT(*) FROM lab_test_results");
    $rcount = (int)$stmt->fetchColumn();
    $rneeded = max(0, 30 - $rcount);

    if ($rneeded > 0) {
        $rstmt = $conn->prepare("INSERT INTO lab_test_results (lab_test_id, result_value, result_unit, reference_range, result_status, result_date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt_tests = $conn->query("SELECT id FROM lab_tests WHERE status = 'Completed' ORDER BY RAND() LIMIT 30");
        $tests = $stmt_tests->fetchAll(PDO::FETCH_COLUMN);

        $result_statuses = ['Normal', 'Normal', 'Normal', 'Abnormal', 'Critical'];

        for ($i = 1; $i <= $rneeded; $i++) {
            $test_id = $tests[$i % count($tests)];
            $value = rand(50, 200);
            $unit = 'mg/dL';
            $range = '70-100';
            $status = $result_statuses[$i % count($result_statuses)];
            $date = date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' days'));
            $rstmt->execute([$test_id, $value, $unit, $range, $status, $date]);
        }
        echo "Inserted $rneeded lab test results.\n";
    } else {
        echo "Lab Test Results: OK ($rcount present).\n";
    }

    // ===== PRESCRIPTIONS =====
    $stmt = $conn->query("SELECT COUNT(*) FROM prescriptions");
    $prcount = (int)$stmt->fetchColumn();
    $prneeded = max(0, 40 - $prcount);

    if ($prneeded > 0) {
        $prstmt = $conn->prepare("INSERT INTO prescriptions (patient_id, consultation_id, doctor_name, prescription_date, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        $doctors = ['Dr. James Kariuki', 'Dr. Sarah Kipchoge', 'Dr. Peter Mwangi', 'Dr. Alice Kiplagat', 'Dr. Robert Kipkemboi'];
        $notes_list = ['Take with meals', 'Take before bed', 'Take in the morning', 'Take as needed', 'Continue for 2 weeks'];
        $statuses = ['Active', 'Active', 'Active', 'Completed', 'Active'];

        $stmt_patients = $conn->query("SELECT id FROM patients ORDER BY RAND() LIMIT 30");
        $patients = $stmt_patients->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt_cons = $conn->query("SELECT id FROM consultations ORDER BY RAND() LIMIT 30");
        $consultations = $stmt_cons->fetchAll(PDO::FETCH_COLUMN);

        for ($i = 1; $i <= $prneeded; $i++) {
            $patient_id = $patients[$i % count($patients)];
            $consultation_id = $consultations[$i % count($consultations)];
            $doctor = $doctors[$i % count($doctors)];
            $date = date('Y-m-d', strtotime('-' . rand(1, 20) . ' days'));
            $notes = $notes_list[$i % count($notes_list)];
            $status = $statuses[$i % count($statuses)];
            $prstmt->execute([$patient_id, $consultation_id, $doctor, $date, $notes, $status]);
        }
        echo "Inserted $prneeded prescriptions.\n";
    } else {
        echo "Prescriptions: OK ($prcount present).\n";
    }

    // ===== PRESCRIPTION ITEMS =====
    $stmt = $conn->query("SELECT COUNT(*) FROM prescription_items");
    $picount = (int)$stmt->fetchColumn();
    $pineeded = max(0, 80 - $picount);

    if ($pineeded > 0) {
        $pistmt = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_id, quantity, dosage, frequency, duration_days, instructions, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $frequencies = ['Once daily', 'Twice daily', 'Three times daily', 'Every 6 hours', 'Every 8 hours', 'As needed'];
        $instructions = ['Take with water', 'Take with food', 'Take before bed', 'Take in the morning', 'Avoid dairy'];

        $stmt_prescriptions = $conn->query("SELECT id FROM prescriptions ORDER BY RAND() LIMIT 40");
        $prescriptions = $stmt_prescriptions->fetchAll(PDO::FETCH_COLUMN);

        $stmt_medicines = $conn->query("SELECT id FROM medicines ORDER BY RAND() LIMIT 40");
        $medicines = $stmt_medicines->fetchAll(PDO::FETCH_COLUMN);

        for ($i = 1; $i <= $pineeded; $i++) {
            $prescription_id = $prescriptions[$i % count($prescriptions)];
            $medicine_id = $medicines[$i % count($medicines)];
            $quantity = rand(1, 30);
            $dosage = (rand(1,4)*100) . 'mg';
            $frequency = $frequencies[$i % count($frequencies)];
            $duration = rand(5, 30);
            $instruction = $instructions[$i % count($instructions)];
            $pistmt->execute([$prescription_id, $medicine_id, $quantity, $dosage, $frequency, $duration, $instruction]);
        }
        echo "Inserted $pineeded prescription items.\n";
    } else {
        echo "Prescription Items: OK ($picount present).\n";
    }

    // ===== SALES =====
    $stmt = $conn->query("SELECT COUNT(*) FROM sales");
    $scount = (int)$stmt->fetchColumn();
    $sneeded = max(0, 60 - $scount);

    if ($sneeded > 0) {
        $sstmt = $conn->prepare("INSERT INTO sales (patient_id, consultation_fee, medication_fee, lab_fee, drug_sales, total_amount, payment_method, payment_status, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $payment_methods = ['Cash', 'M-Pesa', 'Card', 'Insurance', 'Cheque'];
        $payment_statuses = ['Completed', 'Completed', 'Completed', 'Pending', 'Completed'];

        $stmt_patients = $conn->query("SELECT id FROM patients ORDER BY RAND() LIMIT 50");
        $patients = $stmt_patients->fetchAll(PDO::FETCH_COLUMN);

        for ($i = 1; $i <= $sneeded; $i++) {
            $patient_id = $patients[$i % count($patients)];
            $consultation_fee = rand(0, 1) ? rand(300, 1000) : 0;
            $medication_fee = rand(0, 1) ? rand(200, 800) : 0;
            $lab_fee = rand(0, 1) ? rand(500, 3000) : 0;
            $drug_sales = rand(0, 1) ? rand(100, 2000) : 0;
            $total = $consultation_fee + $medication_fee + $lab_fee + $drug_sales;
            
            if ($total == 0) $total = rand(500, 2000);
            
            $method = $payment_methods[$i % count($payment_methods)];
            $status = $payment_statuses[$i % count($payment_statuses)];
            $user_id = rand(1, 3);
            $sstmt->execute([$patient_id, $consultation_fee, $medication_fee, $lab_fee, $drug_sales, $total, $method, $status, $user_id]);
        }
        echo "Inserted $sneeded sales records.\n";
    } else {
        echo "Sales: OK ($scount present).\n";
    }

    // ===== SALE ITEMS =====
    $stmt = $conn->query("SELECT COUNT(*) FROM sale_items");
    $sicount = (int)$stmt->fetchColumn();
    $sineeded = max(0, 100 - $sicount);

    if ($sineeded > 0) {
        $sistmt = $conn->prepare("INSERT INTO sale_items (sale_id, medicine_id, quantity, price, item_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        
        $item_types = ['Medicine', 'Medicine', 'Medicine', 'Consultation', 'Lab Test'];

        $stmt_sales = $conn->query("SELECT id FROM sales ORDER BY RAND() LIMIT 60");
        $sales = $stmt_sales->fetchAll(PDO::FETCH_COLUMN);

        $stmt_medicines = $conn->query("SELECT id, selling_price FROM medicines ORDER BY RAND() LIMIT 80");
        $medicines_data = $stmt_medicines->fetchAll(PDO::FETCH_ASSOC);

        for ($i = 1; $i <= $sineeded; $i++) {
            $sale_id = $sales[$i % count($sales)];
            $med_data = $medicines_data[$i % count($medicines_data)];
            $medicine_id = $med_data['id'];
            $quantity = rand(1, 5);
            $price = $med_data['selling_price'];
            $type = $item_types[$i % count($item_types)];
            $sistmt->execute([$sale_id, $medicine_id, $quantity, $price, $type]);
        }
        echo "Inserted $sineeded sale items.\n";
    } else {
        echo "Sale Items: OK ($sicount present).\n";
    }

    $conn->commit();
    echo "\n=== Comprehensive seeding complete! ===\n";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Error seeding data: " . $e->getMessage() . "\n";
    exit(1);
}
