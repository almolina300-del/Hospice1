<?php
session_start();
require('../Config/Config.php');
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['Username'])) {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS) or die(json_encode(['success' => false, 'message' => 'Could not connect to MySQL database']));
mysqli_select_db($conn, SQL_DB);

// Get form data
$patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$deactivation_date = isset($_POST['deactivation_date']) ? $_POST['deactivation_date'] : date('Y-m-d');
$remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($conn, $_POST['remarks']) : '';
$reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, $_POST['reason']) : '';

// Validate data
if ($patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit();
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Reason is required']);
    exit();
}

// Get user's first name from session
$is_set_by = $_SESSION['First_name'] ?? $_SESSION['Username'] ?? 'Unknown';

// Start transaction
mysqli_begin_transaction($conn);

try {
    // 1. Insert into remarks_inactive table with correct column names
    $insert_sql = "INSERT INTO remarks_inactive (Patient_id, Date, Reason, Details, is_set_by) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare insert statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'issss', $patient_id, $deactivation_date, $reason, $remarks, $is_set_by);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to insert remarks: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    
    // 2. Update patient_details to set is_active = 0
    $update_sql = "UPDATE patient_details SET is_active = 0 WHERE Patient_id = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $update_sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare update statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $patient_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to deactivate patient: " . mysqli_stmt_error($stmt));
    }
    
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    if ($affected_rows === 0) {
        throw new Exception("Patient not found or already deactivated");
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Patient deactivated successfully',
        'patient_id' => $patient_id,
        'is_set_by' => $is_set_by,
        'date' => $deactivation_date,
        'reason' => $reason
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    // Log error for debugging
    error_log("Deactivate patient error: " . $e->getMessage());
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    mysqli_close($conn);
}