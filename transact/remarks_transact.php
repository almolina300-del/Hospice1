<?php
require('../Config/Config.php');
session_start();
date_default_timezone_set('Asia/Manila');



// Database connection
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());
mysqli_set_charset($conn, 'utf8mb4');

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
$redirect = '../' . $base_file . '?c=' . $patient_id;

switch ($action) {

    /* ---------------------- ADD REMARK ---------------------- */
    case "Add Remark":
        // Get form data
        $remark_date = $_POST['Date'] ?? date('Y-m-d');
        $remark_details = $_POST['Details'] ?? '';
        $created_by = $_POST['Created_by'] ?? ($_SESSION['First_name'] ?? 'Unknown');

        // Convert to uppercase
        $remark_details = strtoupper(trim($remark_details));
        $created_by = strtoupper(trim($created_by));
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

        // Insert remark using prepared statement
        $stmt = $conn->prepare("INSERT INTO patient_remarks (Date, Details, Created_by, Patient_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $remark_date, $remark_details, $created_by, $patient_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            echo "<script>
                    alert('Remark successfully added!');
                    window.location.href = '../$base_file?c=$patient_id&remark_added=1';
                  </script>";
        } else {
            $error = $stmt->error;
            $stmt->close();
            echo "<script>
                    alert('Error adding remark: " . addslashes($error) . "');
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
        $remark_details = $_POST['Details'] ?? '';
        $current_user = $_SESSION['First_name'] ?? 'Unknown';

        // Convert to uppercase
        $remark_details = strtoupper(trim($remark_details));
        $current_user_upper = strtoupper(trim($current_user));
        $remark_date = trim($remark_date);

        // Validate
        if (empty($remark_details) || $ptr_id <= 0) {
            echo "<script>
                    alert('Remark details are required.');
                    window.history.back();
                  </script>";
            exit();
        }

        // Check if current user is the creator of this remark using prepared statement
        $stmt = $conn->prepare("SELECT Created_by FROM patient_remarks WHERE Ptremarks_id = ?");
        $stmt->bind_param("i", $ptr_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        $stmt->close();
        
        if ($check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
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
            // Update remark using prepared statement
            $stmt = $conn->prepare("UPDATE patient_remarks SET Date = ?, Details = ? WHERE Ptremarks_id = ?");
            $stmt->bind_param("ssi", $remark_date, $remark_details, $ptr_id);
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
            $stmt->close();
            mysqli_commit($conn);
            
            echo "<script>
                    alert('Remark successfully updated.');
                    window.location.href = '../$base_file?c=$patient_id&remark_updated=1';
                  </script>";
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            if (isset($stmt)) $stmt->close();
            
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