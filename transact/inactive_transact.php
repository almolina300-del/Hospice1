<?php
session_start();
require('../Config/Config.php');

// Check if user is SUADMIN
if (!isset($_SESSION['Role']) || strtoupper($_SESSION['Role']) != 'SUADMIN') {
    die('<div style="text-align:center; padding:20px; color:red;">Access Denied</div>');
}

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB) 
    or die('Could not connect to database: ' . mysqli_connect_error());

// Get patient ID and action
$patient_id = isset($_GET['c']) ? intval($_GET['c']) : 0;
$action = isset($_GET['a']) ? trim($_GET['a']) : '';

// Get search and page parameters to maintain them
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Only process if patient ID is valid
if ($patient_id > 0 && $action == 'Restore Record') {
    
    // Verify patient exists and is inactive
    $check_sql = "SELECT Patient_id FROM patient_details WHERE Patient_id = ? AND is_active = 0";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        
        // Restore patient (set is_active = 1)
        $update_sql = "UPDATE patient_details SET is_active = 1 WHERE Patient_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Build the redirect URL with search and page parameters
            $redirect_url = '../inactive_patients_modal.php';
            $params = [];
            
            if (!empty($search)) {
                $params[] = 'search=' . urlencode($search);
            }
            if ($page > 1) {
                $params[] = 'page=' . $page;
            }
            
            if (!empty($params)) {
                $redirect_url .= '?' . implode('&', $params);
            }
            
            echo "<script>
                    alert('Patient has been restored successfully!');
                    // Use parent's function to reload modal
                    if (window.parent && window.parent.reloadInactiveModal) {
                        window.parent.reloadInactiveModal('" . $redirect_url . "');
                    } else {
                        window.history.back();
                    }
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
            alert('Invalid request.');
            window.history.back();
          </script>";
}

mysqli_close($conn);
exit();
?>