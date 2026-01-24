<?php
session_start();
require('Config/Config.php');

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

// Get form data
$dosearch = isset($_POST['dosearch']) ? $_POST['dosearch'] : '';
$prescription_date = isset($_POST['prescription_date']) ? $_POST['prescription_date'] : '';
$doctor_license = isset($_POST['License_number']) ? $_POST['License_number'] : '';
$doctor_name = isset($_POST['DoctorName']) ? $_POST['DoctorName'] : '';
$doctor_ptr = isset($_POST['Ptr_number']) ? $_POST['Ptr_number'] : '';
$selected_patients = isset($_POST['selected_patients']) ? explode(',', $_POST['selected_patients']) : [];
$excluded_patients = isset($_POST['exclude_patients']) ? $_POST['exclude_patients'] : [];
$retrieval_method_filter = isset($_POST['retrieval_method_filter']) ? $_POST['retrieval_method_filter'] : 'ALL'; 

// Validate required fields
if (empty($dosearch) || empty($prescription_date) || empty($doctor_license) || empty($doctor_name)) {
    $_SESSION['error'] = "All fields are required!";
    header("Location: bulk_print.php?dosearch=" . urlencode($dosearch));
    exit();
}

// Connect to database
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect to MySQL database. ' . mysqli_connect_error());

// Get PTR number from doctors table if not provided
if (empty($doctor_ptr)) {
    $ptr_sql = "SELECT Ptr_number FROM doctors WHERE License_number = ?";
    $ptr_stmt = mysqli_prepare($conn, $ptr_sql);
    if ($ptr_stmt) {
        mysqli_stmt_bind_param($ptr_stmt, "s", $doctor_license);
        mysqli_stmt_execute($ptr_stmt);
        $ptr_result = mysqli_stmt_get_result($ptr_stmt);
        if ($ptr_row = mysqli_fetch_assoc($ptr_result)) {
            $doctor_ptr = $ptr_row['Ptr_number'];
        }
        mysqli_stmt_close($ptr_stmt);
    }
}

// Get patients who have prescriptions with the selected refill day in their LATEST prescription only
// AND are in the selected patients list (if provided)
$patient_sql = "SELECT pd.Patient_id, 
                       pd.First_name,
                       pd.Middle_name,
                       pd.Last_name,
                       pd.Birthday,
                       pd.Prescription_retrieval_method,
                       TIMESTAMPDIFF(YEAR, pd.Birthday, CURDATE()) AS Age,
                       p.Prescription_id as latest_prescription_id,
                       p.Refill_day as patient_refill_day,
                       p.Date as last_prescription_date,
                       CONCAT(pd.Last_name, ', ', pd.First_name, ' ', COALESCE(pd.Middle_name, '')) AS Patient_name
                FROM patient_details pd
                INNER JOIN prescription p ON pd.Patient_id = p.Patient_id
                WHERE pd.is_active = 1 
                AND p.Prescription_id IN (
                    SELECT MAX(p2.Prescription_id) 
                    FROM prescription p2 
                    WHERE p2.Patient_id = pd.Patient_id 
                    GROUP BY p2.Patient_id
                )
                AND p.Refill_day = ?";

// Add retrieval method filter if not "ALL"
if ($retrieval_method_filter !== 'ALL') {
    $patient_sql .= " AND pd.Prescription_retrieval_method = ?";
}

// IMPORTANT: Always filter by selected patients from the hidden input
// This ensures only visible/selected patients are processed
if (!empty($selected_patients) && $selected_patients[0] !== '') {
    $placeholders = implode(',', array_fill(0, count($selected_patients), '?'));
    $patient_sql .= " AND pd.Patient_id IN ($placeholders)";
}

$patient_sql .= " ORDER BY pd.Last_name, pd.First_name";

// Prepare statement with correct connection and SQL variable
$stmt = mysqli_prepare($conn, $patient_sql);
if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

// Bind parameters
if ($retrieval_method_filter !== 'ALL') {
    if (!empty($selected_patients) && $selected_patients[0] !== '') {
        // With retrieval method filter AND selected patients
        $types = "s" . str_repeat("i", count($selected_patients) + 1);
        $params = array_merge([$dosearch, $retrieval_method_filter], $selected_patients);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    } else {
        // With only retrieval method filter
        mysqli_stmt_bind_param($stmt, "ss", $dosearch, $retrieval_method_filter);
    }
} else {
    if (!empty($selected_patients) && $selected_patients[0] !== '') {
        // With only selected patients
        $types = "i" . str_repeat("i", count($selected_patients));
        $params = array_merge([$dosearch], $selected_patients);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    } else {
        // No extra filters
        mysqli_stmt_bind_param($stmt, "i", $dosearch);
    }
}

mysqli_stmt_execute($stmt);
$patient_result = mysqli_stmt_get_result($stmt);

$patients = [];
$total_patients = 0;
$created_count = 0;
$existing_count = 0;
$errors = [];
$all_prescription_ids = []; // Store ALL prescription IDs for PDF generation
$new_prescription_ids = []; // Store only newly created IDs

// Store patient data for processing
while ($patient = mysqli_fetch_assoc($patient_result)) {
    $patients[] = $patient;
    $total_patients++;
}

mysqli_stmt_close($stmt);

// Log how many patients are being processed
error_log("Processing $total_patients patients for bulk print");

// Check if any patients were found
if ($total_patients === 0) {
    $_SESSION['bulk_print_result'] = [
        'success' => false,
        'count' => 0,
        'created_count' => 0,
        'existing_count' => 0,
        'total_patients' => 0,
        'excluded_count' => count($excluded_patients),
        'errors' => ['No patients found to process. Please select patients and try again.'],
        'refill_day' => $dosearch,
        'prescription_date' => $prescription_date,
        'doctor_name' => $doctor_name,
        'doctor_license' => $doctor_license,
        'doctor_ptr' => $doctor_ptr,
        'all_prescription_ids' => [],
        'created_ids' => [],
        'selected_patients' => $selected_patients,
        'retrieval_method_filter' => $retrieval_method_filter,
        'action' => 'create'
    ];
    
    header("Location: bulk_print.php?dosearch=" . urlencode($dosearch));
    exit();
}

// PROCESS PATIENTS
foreach ($patients as $patient) {
    $patient_id = $patient['Patient_id'];
    $age = $patient['Age'];
    $latest_prescription_id = $patient['latest_prescription_id'];
    $patient_refill_day = $patient['patient_refill_day'];
    $last_prescription_date = $patient['last_prescription_date'];
    $patient_name = $patient['Patient_name'];
    
    // Log processing
    error_log("Processing Patient: $patient_name (ID: $patient_id), Last Prescription: $last_prescription_date, Age: $age");
    
    // Check if prescription already exists for this patient on the same date
    $check_sql = "SELECT Prescription_id FROM prescription 
                  WHERE Patient_id = ? AND Date = ?";
    
    $check_stmt = mysqli_prepare($conn, $check_sql);
    if (!$check_stmt) {
        $errors[] = "Check failed for Patient: $patient_name - " . mysqli_error($conn);
        continue;
    }
    
    mysqli_stmt_bind_param($check_stmt, "is", $patient_id, $prescription_date);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    // If prescription exists for this date, use the existing one
    if ($check_row = mysqli_fetch_assoc($check_result)) {
        mysqli_stmt_close($check_stmt);
        
        $existing_prescription_id = $check_row['Prescription_id'];
        $all_prescription_ids[] = $existing_prescription_id;
        $existing_count++;
        
        error_log("Using existing prescription $existing_prescription_id for Patient: $patient_name (Date: $prescription_date)");
        continue;
    }
    
    mysqli_stmt_close($check_stmt);
    
    // If no existing prescription, create a new one
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Create new prescription with creation_type = 'bulk'
        $insert_sql = "INSERT INTO prescription 
                       (Patient_id, Date, Age, Refill_day, License_number, creation_type) 
                       VALUES (?, ?, ?, ?, ?, 'bulk')";
        
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        if (!$insert_stmt) {
            throw new Exception("Prepare failed for Patient: $patient_name - " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($insert_stmt, "isiii", 
            $patient_id, 
            $prescription_date, 
            $age, 
            $patient_refill_day,
            $doctor_license
        );
        
        if (!mysqli_stmt_execute($insert_stmt)) {
            throw new Exception("Failed to create prescription for Patient: $patient_name - " . mysqli_error($conn));
        }
        
        $new_prescription_id = mysqli_insert_id($conn);
        $all_prescription_ids[] = $new_prescription_id;
        $new_prescription_ids[] = $new_prescription_id;
        
        // 2. Copy medicines from latest prescription to new prescription
        if ($latest_prescription_id) {
            $copy_meds_sql = "INSERT INTO rx (Prescription_id, Medicine_id, Quantity, Frequency)
                              SELECT ?, Medicine_id, Quantity, Frequency
                              FROM rx 
                              WHERE Prescription_id = ?";
            
            $copy_stmt = mysqli_prepare($conn, $copy_meds_sql);
            if (!$copy_stmt) {
                throw new Exception("Failed to prepare medicine copy for Patient: $patient_name");
            }
            
            mysqli_stmt_bind_param($copy_stmt, "ii", $new_prescription_id, $latest_prescription_id);
            
            if (!mysqli_stmt_execute($copy_stmt)) {
                throw new Exception("Failed to copy medicines for Patient: $patient_name - " . mysqli_error($conn));
            }
            
            mysqli_stmt_close($copy_stmt);
            
            // Get count of copied medicines for logging
            $med_count_sql = "SELECT COUNT(*) as med_count FROM rx WHERE Prescription_id = ?";
            $med_count_stmt = mysqli_prepare($conn, $med_count_sql);
            mysqli_stmt_bind_param($med_count_stmt, "i", $new_prescription_id);
            mysqli_stmt_execute($med_count_stmt);
            $med_result = mysqli_stmt_get_result($med_count_stmt);
            $med_data = mysqli_fetch_assoc($med_result);
            $med_count = $med_data['med_count'];
            
            mysqli_stmt_close($med_count_stmt);
            
            error_log("Copied $med_count medicines from prescription $latest_prescription_id to $new_prescription_id for Patient: $patient_name");
        } else {
            $med_count = 0;
            error_log("No previous prescription found for Patient: $patient_name to copy medicines from");
        }
        
        mysqli_stmt_close($insert_stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        $created_count++;
        
        // Log success
        error_log("✅ Created BULK prescription $new_prescription_id for Patient: $patient_name (Refill Day: $patient_refill_day, Date: $prescription_date) with $med_count medicines");
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $errors[] = "Patient: $patient_name - " . $e->getMessage();
        error_log("❌ Error for Patient: $patient_name: " . $e->getMessage());
        continue;
    }
}

// Calculate total count
$total_count = count($all_prescription_ids);

// Store results in session - with ALL necessary keys
$_SESSION['bulk_print_result'] = [
    'success' => $total_count > 0,
    'count' => $total_count,
    'created_count' => $created_count,
    'existing_count' => $existing_count,
    'total_patients' => $total_patients,
    'excluded_count' => count($excluded_patients),
    'errors' => $errors,
    'refill_day' => $dosearch,
    'prescription_date' => $prescription_date,
    'doctor_name' => $doctor_name,
    'doctor_license' => $doctor_license,
    'doctor_ptr' => $doctor_ptr,
    'all_prescription_ids' => $all_prescription_ids,
    'created_ids' => $new_prescription_ids,
    'selected_patients' => $selected_patients,
    'action' => 'create'
];

// Store for PDF generation
$_SESSION['bulk_prescription_ids'] = $all_prescription_ids;
$_SESSION['prescription_ids'] = $all_prescription_ids;
$_SESSION['print_prescriptions'] = $all_prescription_ids;

// Also store individual data that might be needed by PDF
$_SESSION['prescription_date'] = $prescription_date;
$_SESSION['doctor_name'] = $doctor_name;
$_SESSION['doctor_license'] = $doctor_license;
$_SESSION['doctor_ptr'] = $doctor_ptr;
$_SESSION['refill_day'] = $dosearch;
$_SESSION['retrieval_method_filter'] = $retrieval_method_filter;

// Log success
error_log("✅ Bulk print processing complete: $created_count new prescriptions, $existing_count existing prescriptions, $total_count total");

// Redirect back to bulk_print.php
header("Location: bulk_print.php?dosearch=" . urlencode($dosearch) . "&bulk_created=true");
exit();

mysqli_close($conn);
?>