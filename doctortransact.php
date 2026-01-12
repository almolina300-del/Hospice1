<?php
require('Config/Config.php');
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

// Capture GET and POST values
$action = $_GET['a'] ?? $_POST['a'] ?? '';
$License_number_key = $_GET['c'] ?? $_POST['c'] ?? '';

// Database connection
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// Define variables safely with input sanitization
$Last_name  = mysqli_real_escape_string($conn, $_POST['Last_name'] ?? '');
$First_name = mysqli_real_escape_string($conn, $_POST['First_name'] ?? '');
$Middle_name = mysqli_real_escape_string($conn, $_POST['Middle_name'] ?? '');
$License_number = mysqli_real_escape_string($conn, $_POST['License_number'] ?? '');
$Ptr_number = mysqli_real_escape_string($conn, $_POST['Ptr_number'] ?? '');

// Redirect target
$redirect = 'Doctors.php';

switch ($action) {

    /* ---------------------- CREATE RECORD ---------------------- */
    case "Create Record":
        $Last_name  = strtoupper($Last_name);
        $First_name = strtoupper($First_name);
        $Middle_name = strtoupper($Middle_name);
        $License_number = strtoupper($License_number);
        $Ptr_number = strtoupper($Ptr_number);

        if (empty($License_number) || empty($Last_name) || empty($First_name)) {
            echo "<script>
                    alert('License Number, Last Name, and First Name are required fields.');
                    window.history.back();
                  </script>";
            exit();
        }

        $dup_sql = "SELECT License_number FROM doctors 
                    WHERE License_number = ? OR Ptr_number = ?";
        $stmt = mysqli_prepare($conn, $dup_sql);
        mysqli_stmt_bind_param($stmt, "ss", $License_number, $Ptr_number);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo "<script>
                    alert('Doctor with this License or PTR already exists.');
                    window.history.back();
                  </script>";
            mysqli_stmt_close($stmt);
            exit();
        }
        mysqli_stmt_close($stmt);

        $sql = "INSERT INTO doctors 
                (License_number, Last_name, First_name, Middle_name, Ptr_number, is_active)
                VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssss",
            $License_number, $Last_name, $First_name, $Middle_name, $Ptr_number);

        if (mysqli_stmt_execute($stmt)) {
            echo "<script>
                    alert('Doctor record successfully created!');
                    window.location.href = 'Doctors.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Error creating doctor record.');
                    window.history.back();
                  </script>";
        }
        mysqli_stmt_close($stmt);
        exit();

    /* ---------------------- DEACTIVATE RECORD ---------------------- */
    case "deactivate":
        if (!empty($License_number_key)) {
            $sql = "UPDATE doctors SET is_active = 0 WHERE License_number = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $License_number_key);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
                        alert('Doctor record has been deactivated.');
                        window.location.href = 'Doctors.php';
                      </script>";
            }
            mysqli_stmt_close($stmt);
        }
        exit();

    /* ---------------------- ACTIVATE RECORD ---------------------- */
    case "activate":
        if (!empty($License_number_key)) {
            $sql = "UPDATE doctors SET is_active = 1 WHERE License_number = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $License_number_key);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
                        alert('Doctor record has been activated.');
                        window.location.href = 'Doctors.php';
                      </script>";
            }
            mysqli_stmt_close($stmt);
        }
        exit();

/* ---------------------- UPDATE RECORD ---------------------- */
case "Update Record":
    // Get the original license number from the key
    $original_license = mysqli_real_escape_string($conn, $License_number_key);
    
    // Get form values
    $Last_name  = strtoupper(mysqli_real_escape_string($conn, $_POST['Last_name'] ?? ''));
    $First_name = strtoupper(mysqli_real_escape_string($conn, $_POST['First_name'] ?? ''));
    $Middle_name = strtoupper(mysqli_real_escape_string($conn, $_POST['Middle_name'] ?? ''));
    $License_number = mysqli_real_escape_string($conn, $_POST['License_number'] ?? '');
    $Ptr_number = mysqli_real_escape_string($conn, $_POST['Ptr_number'] ?? '');

    // Debug: Remove this after testing
    error_log("UPDATE - Original: $original_license, New License: $License_number, Last: $Last_name, First: $First_name");

    // Validate required fields
    if (empty($Last_name) || empty($First_name)) {
        echo "<script>
                alert('Last Name and First Name are required fields.');
                window.history.back();
              </script>";
        exit();
    }

    // Verify the doctor exists
    $check_sql = "SELECT License_number FROM doctors WHERE License_number = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $original_license);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) == 0) {
        mysqli_stmt_close($check_stmt);
        echo "<script>
                alert('Doctor record not found. License: " . htmlspecialchars($original_license) . "');
                window.history.back();
              </script>";
        exit();
    }
    mysqli_stmt_close($check_stmt);

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Update only the name fields (License and PTR remain unchanged)
        $update_sql = "UPDATE doctors 
                       SET Last_name = ?,
                           First_name = ?,
                           Middle_name = ?
                       WHERE License_number = ?";
        
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "ssss", 
            $Last_name, $First_name, $Middle_name, $original_license);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_commit($conn);
            mysqli_stmt_close($stmt);
            
            echo "<script>
                    alert('Doctor record successfully updated.');
                    window.location.href = 'Doctors.php';
                  </script>";
            exit();
        } else {
            $error_msg = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception("Update failed: $error_msg");
        }

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>
                alert('Error updating record: " . addslashes($e->getMessage()) . "');
                window.history.back();
              </script>";
        exit();
    }
}
?>