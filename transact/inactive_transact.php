<?php
session_start();
require('../Config/Config.php');

// Check if user is authenticated
if (!isset($_SESSION['Username'])) {
    header("Location: ../index.php");
    exit();
}

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB) 
    or die('Could not connect to database: ' . mysqli_connect_error());

// Get patient ID
$patient_id = isset($_GET['c']) ? intval($_GET['c']) : 0;

// Only process if patient ID is valid
if ($patient_id > 0) {
    
    // Verify patient exists and is inactive
    $check_sql = "SELECT Patient_id, Last_name, First_name FROM patient_details WHERE Patient_id = ? AND is_active = 0";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        
        // Restore patient (set is_active = 1)
        $update_sql = "UPDATE patient_details SET is_active = 1 WHERE Patient_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Remove from remarks_inactive table
            $delete_remark = "DELETE FROM remarks_inactive WHERE Patient_id = ?";
            $stmt2 = mysqli_prepare($conn, $delete_remark);
            mysqli_stmt_bind_param($stmt2, "i", $patient_id);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);
            
            $patient_name = $row['Last_name'] . ', ' . $row['First_name'];
            
            echo "<script>
                    alert('Patient {$patient_name} has been restored successfully!');
                    window.location.href = '../inactive_patient.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Error restoring patient: " . addslashes(mysqli_error($conn)) . "');
                    window.history.back();
                  </script>";
        }
        mysqli_stmt_close($stmt);
    } else {
        mysqli_stmt_close($stmt);
        echo "<script>
                alert('Patient not found or already active.');
                window.history.back();
              </script>";
    }
} else {
    echo "<script>
            alert('Invalid patient ID.');
            window.history.back();
          </script>";
}

mysqli_close($conn);
exit();
?>