<?php
require('Config/Config.php');
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

// Database connection
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// Get action
$action = $_POST['action'] ?? '';

// Get patient ID
$patient_id = isset($_GET['c']) ? intval($_GET['c']) : 0;
if ($patient_id === 0 && isset($_POST['patient_id'])) {
    $patient_id = intval($_POST['patient_id']);
}

// Get referring page to redirect back correctly
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$base_file = 'Patiententry.php'; // Your actual file name

// Extract base filename from referrer
if (!empty($referrer)) {
    $parsed = parse_url($referrer);
    if (isset($parsed['path'])) {
        $base_file = basename($parsed['path']);
    }
}

// Initialize redirect - use the correct file
$redirect = $base_file . '?c=' . $patient_id;

switch ($action) {

    /* ---------------------- ADD REMARK ---------------------- */
    case "Add Remark":
        // Get form data
        $remark_date = $_POST['Date'] ?? date('Y-m-d');
        $remark_details = mysqli_real_escape_string($conn, $_POST['Details'] ?? '');
        $created_by = mysqli_real_escape_string($conn, $_POST['Created_by'] ?? ($_SESSION['First_name'] ?? 'Unknown'));

        // Convert to uppercase
        $remark_details = strtoupper($remark_details);
        $created_by = strtoupper($created_by);
        $remark_date = trim($remark_date);

        // Validate
        if (empty($remark_details)) {
            echo "<script>
                    alert('Remark details are required.');
                    window.history.back();
                  </script>";
            exit();
        }

        if ($patient_id <= 0) {
            echo "<script>
                    alert('Invalid patient ID.');
                    window.history.back();
                  </script>";
            exit();
        }

        // Insert remark
        $sql = "INSERT INTO patient_remarks 
                (Date, Details, Created_by, Patient_id)
                VALUES ('$remark_date', '$remark_details', '$created_by', $patient_id)";
        
        if (mysqli_query($conn, $sql)) {
            echo "<script>
                    alert('Remark successfully added!');
                    window.location.href = '$base_file?c=$patient_id&remark_added=1';
                  </script>";
        } else {
            echo "<script>
                    alert('Error adding remark: " . mysqli_error($conn) . "');
                    window.history.back();
                  </script>";
        }
        exit();
        break;

    /* ---------------------- UPDATE REMARK ---------------------- */
    case "Update Remark":
        // Get form data
        $ptr_id = intval($_POST['remark_id'] ?? 0);
        $remark_date = $_POST['Date'] ?? date('Y-m-d');
        $remark_details = mysqli_real_escape_string($conn, $_POST['Details'] ?? '');
        $current_user = $_SESSION['First_name'] ?? 'Unknown';

        // Convert to uppercase
        $remark_details = strtoupper($remark_details);
        $current_user_upper = strtoupper($current_user);
        $remark_date = trim($remark_date);

        // Validate
        if (empty($remark_details) || $ptr_id <= 0) {
            echo "<script>
                    alert('Remark details are required.');
                    window.history.back();
                  </script>";
            exit();
        }

        // Check if current user is the creator of this remark
        $check_sql = "SELECT Created_by FROM patient_remarks WHERE Ptremarks_id = $ptr_id";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $row = mysqli_fetch_assoc($check_result);
            $creator = strtoupper(trim($row['Created_by']));
            
            // Only allow the creator to edit
            if ($creator !== $current_user_upper) {
                echo "<script>
                        alert('Only the creator ($creator) can edit this remark.');
                        window.history.back();
                      </script>";
                exit();
            }
        } else {
            echo "<script>
                    alert('Remark not found.');
                    window.history.back();
                  </script>";
            exit();
        }

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Update remark - REMOVED Updated_by column
            $sql = "UPDATE patient_remarks 
                    SET Date = '$remark_date', 
                        Details = '$remark_details'
                    WHERE Ptremarks_id = $ptr_id";
            
            if (!mysqli_query($conn, $sql)) {
                throw new Exception(mysqli_error($conn));
            }

            mysqli_commit($conn);
            echo "<script>
                    alert('Remark successfully updated.');
                    window.location.href = '$base_file?c=$patient_id&remark_updated=1';
                  </script>";
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<script>
                    alert('Error updating remark: " . addslashes($e->getMessage()) . "');
                    window.history.back();
                  </script>";
            exit();
        }

        break;

    /* ---------------------- DEFAULT ---------------------- */
    default:
        echo "<script>
                alert('Invalid action.');
                window.history.back();
              </script>";
        exit();
        break;
}

// Close connection
mysqli_close($conn);
?>