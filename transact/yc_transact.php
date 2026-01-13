<?php
require('../Config/Config.php');
session_start();

if (!isset($_SESSION['Username'])) {
  // Redirect to login page
  header("Location: ../index.php");
  exit();
}

// Capture GET and POST values
$action = $_POST['action'] ?? $_REQUEST['a'] ?? '';
$cid    = intval($_REQUEST['c'] ?? 0); // Patient ID
$ycid   = intval($_REQUEST['yc'] ?? 0); // Yellow Card ID (if needed)

// Database connection
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
  or die('Could not connect: ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// Define Yellow Card variables safely
$Yellow_card_nos  = $_POST['Yellow_card_nos'] ?? '';
$Membership_type  = $_POST['Membership_type'] ?? '';
$Yc_expiry_date   = $_POST['Yc_expiry_date'] ?? '';
$Patient_id       = $_POST['Patient_id'] ?? $cid;

// Sanitize inputs
$Yellow_card_nos = trim($Yellow_card_nos);
$Membership_type = strtoupper(trim($Membership_type));
$Yc_expiry_date  = trim($Yc_expiry_date);

// Always define $redirect before the switch
$redirect = '../Patiententry.php';

switch ($action) {

  /* ---------------------- CREATE YELLOW CARD ---------------------- */
  case "Create Yellow Card":
    // Validate required fields
    if (empty($Yellow_card_nos) || empty($Patient_id)) {
      echo "<script>
              alert('Yellow Card Number and Patient ID are required.');
              window.history.back();
            </script>";
      exit;
    }

    // Check if patient exists
  $stmt = $conn->prepare("SELECT Patient_id FROM patient_details WHERE Patient_id = ? AND is_active = 1");
$stmt->bind_param("i", $Patient_id);
$stmt->execute();
$patient_check = $stmt->get_result();
    if (mysqli_num_rows($patient_check) == 0) {
        echo "<script>
                alert('Patient not found or is deactivated.');
                window.history.back();
              </script>";
        exit;
    }

    // Check if patient already has a yellow card
    $existing_yc = mysqli_query($conn, 
        "SELECT Yellow_card_nos FROM yellow_card WHERE Patient_id = $Patient_id");
    if (mysqli_num_rows($existing_yc) > 0) {
        echo "<script>
                alert('This patient already has a Yellow Card assigned.');
                window.history.back();
              </script>";
        exit;
    }

    // Check if Yellow Card number already exists
    $dup_sql = "SELECT Yellow_card_nos FROM yellow_card 
                WHERE Yellow_card_nos = '$Yellow_card_nos'";
    $dup_result = mysqli_query($conn, $dup_sql) or die(mysqli_error($conn));
    if (mysqli_num_rows($dup_result) > 0) {
      echo "<script>
            alert('Yellow Card Number already exists.');
            window.history.back();
          </script>";
      exit;
    }

    // Insert yellow card
    $sql = "INSERT INTO yellow_card 
            (Yellow_card_nos, Patient_id, Membership_type, Yc_expiry_date)
            VALUES ('$Yellow_card_nos', $Patient_id, '$Membership_type', '$Yc_expiry_date')";
    
    if (mysqli_query($conn, $sql)) {
      echo "<script>
              alert('Yellow Card successfully created!');
              window.location.href = '../ptedit.php?c=$Patient_id';
            </script>";
    } else {
      echo "<script>
              alert('Error creating Yellow Card: " . mysqli_error($conn) . "');
              window.history.back();
            </script>";
    }
    exit();
    break;

  /* ---------------------- UPDATE YELLOW CARD ---------------------- */
  case "Update Yellow Card":
    // Validate required fields
    if (empty($Yellow_card_nos) || empty($Patient_id)) {
      echo "<script>
              alert('Yellow Card Number and Patient ID are required.');
              window.history.back();
            </script>";
      exit;
    }

    // Check if Yellow Card exists for another patient
    $dup_sql = "SELECT Patient_id FROM yellow_card 
                WHERE Yellow_card_nos = '$Yellow_card_nos' 
                AND Patient_id != $Patient_id";
    $dup_result = mysqli_query($conn, $dup_sql) or die(mysqli_error($conn));
    if (mysqli_num_rows($dup_result) > 0) {
      echo "<script>
            alert('Yellow Card Number already exists for another patient.');
            window.history.back();
          </script>";
      exit;
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Check if yellow card exists for this patient
        $check_sql = "SELECT Yellow_card_nos FROM yellow_card WHERE Patient_id = $Patient_id";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update existing yellow card
            $update_sql = "UPDATE yellow_card 
                           SET Yellow_card_nos = '$Yellow_card_nos',
                               Membership_type = '$Membership_type',
                               Yc_expiry_date = '$Yc_expiry_date'
                           WHERE Patient_id = $Patient_id";
        } else {
            // Insert new yellow card
            $update_sql = "INSERT INTO yellow_card 
                           (Yellow_card_nos, Patient_id, Membership_type, Yc_expiry_date)
                           VALUES ('$Yellow_card_nos', $Patient_id, '$Membership_type', '$Yc_expiry_date')";
        }
        
        mysqli_query($conn, $update_sql);
        
        // Commit transaction
        mysqli_commit($conn);
        
        echo "<script>
                alert('Yellow Card successfully updated!');
                window.location.href = '../ptedit.php?c=$Patient_id';
              </script>";
        
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        
        echo "<script>
                alert('Error updating Yellow Card: " . addslashes($e->getMessage()) . "');
                window.history.back();
              </script>";
    }
    exit();
    break;

  /* ---------------------- DELETE YELLOW CARD ---------------------- */
  case "Delete Yellow Card":
    if (empty($Patient_id)) {
      echo "<script>
              alert('Patient ID is required.');
              window.history.back();
            </script>";
      exit;
    }

    $delete_sql = "DELETE FROM yellow_card WHERE Patient_id = $Patient_id";
    
    if (mysqli_query($conn, $delete_sql)) {
      echo "<script>
              alert('Yellow Card successfully deleted!');
              window.location.href = '../ptedit.php?c=$Patient_id';
            </script>";
    } else {
      echo "<script>
              alert('Error deleting Yellow Card: " . mysqli_error($conn) . "');
              window.history.back();
            </script>";
    }
    exit();
    break;

  /* ---------------------- VIEW YELLOW CARD ---------------------- */
  case "View Yellow Card":
    // This could redirect to a view page or return JSON for AJAX
    header("Location: ../ptedit.php?c=$Patient_id");
    exit();
    break;

}

// Default redirect
header("Location: $redirect");
exit;
?>