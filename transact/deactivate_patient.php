<?php
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

require('Config/Config.php');

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS) or die('Could not connect to MySQL database. ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// Get form data
$patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$deactivation_date = isset($_POST['deactivation_date']) ? $_POST['deactivation_date'] : date('Y-m-d');
$remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($conn, $_POST['remarks']) : '';
$set_by = isset($_POST['set_by']) ? mysqli_real_escape_string($conn, $_POST['set_by']) : 'Unknown';

// Validate data
if ($patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // 1. Insert into remarks_inactive table
    $insert_sql = "INSERT INTO remarks_inactive (Patient_id, Date, Details, is_set_by) 
                   VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, 'isss', $patient_id, $deactivation_date, $remarks, $set_by);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to insert remarks: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    
    // 2. Update patient_details to set is_active = 0
    $update_sql = "UPDATE patient_details SET is_active = 0 WHERE Patient_id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, 'i', $patient_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to deactivate patient: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode(['success' => true, 'message' => 'Patient deactivated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);
?>