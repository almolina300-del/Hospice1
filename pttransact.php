<?php
require('Config/Config.php');
session_start();

if (!isset($_SESSION['Username'])) {
  // Redirect to login page
  header("Location: index.php");
  exit();
}

// Capture GET and POST values
$action = $_REQUEST['a'] ?? '';
$cid    = intval($_REQUEST['c'] ?? 0);

foreach ($_POST as $key => $value) {
  $$key = $value;
}

// Database connection
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
  or die('Could not connect: ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// Define patient variables safely
$Last_name        = $_POST['Last_name'] ?? '';
$First_name       = $_POST['First_name'] ?? '';
$Middle_name      = $_POST['Middle_name'] ?? '';
$Suffix           = $_POST['Suffix'] ?? '';
$Sex              = $_POST['Sex'] ?? '';
$Birthday         = $_POST['Birthday'] ?? '';
$House_nos_street_name    = $_POST['House_nos_street_name'] ?? '';
$Barangay         = $_POST['Barangay'] ?? '';
$Contact_nos      = $_POST['Contact_nos'] ?? '';
$Prescription_retrieval_method = $_POST['Prescription_retrieval_method'] ?? '';

// Sanitize numeric values
$Contact_nos      = preg_replace('/\D/', '', $Contact_nos);

// Always define $redirect before the switch to avoid warnings
$redirect = 'Patiententry.php';

switch ($action) {

  /* ---------------------- CREATE PATIENT RECORD ---------------------- */
  case "Create Record":
    // Convert to uppercase
    $Last_name   = strtoupper($Last_name);
    $First_name  = strtoupper($First_name);
    $Middle_name = strtoupper($Middle_name);
    $Suffix      = strtoupper($Suffix);
    $Sex         = strtoupper($Sex);
    $Birthday    = strtoupper($Birthday);
    $House_nos_street_name = strtoupper($House_nos_street_name);
    $Barangay    = strtoupper($Barangay);
    $Contact_nos = trim($Contact_nos);
    $Prescription_retrieval_method = strtoupper($Prescription_retrieval_method);

    // Duplicate check before insert
    $dup_sql = "SELECT Patient_id FROM patient_details 
                WHERE Last_name = '$Last_name'
                  AND First_name = '$First_name'
                  AND Middle_name = '$Middle_name'
                  AND Suffix = '$Suffix'
                  AND Birthday = '$Birthday'";
    $dup_result = mysqli_query($conn, $dup_sql) or die(mysqli_error($conn));
    if (mysqli_num_rows($dup_result) > 0) {
      echo "<script>
            alert('A patient with the same name and birthday already exists.');
            window.history.back();
          </script>";
      exit;
    }

    // Insert patient
    $sql = "INSERT INTO patient_details 
            (Patient_id, First_name, Middle_name, Last_name, Suffix, Sex, Birthday, Contact_nos, House_nos_street_name, Barangay, Prescription_retrieval_method)
            VALUES (NULL, '$First_name', '$Middle_name', '$Last_name', '$Suffix', '$Sex', '$Birthday', '$Contact_nos', '$House_nos_street_name', '$Barangay', '$Prescription_retrieval_method')";
    mysqli_query($conn, $sql) or die(mysqli_error($conn));

    // Get newly created patient ID
    $new_id = mysqli_insert_id($conn);

    // Show popup then redirect to edit page
    echo "<script>
          alert('Record successfully created!');
          window.location.href = 'ptedit.php?c=$new_id';
        </script>";
    exit();
    break;

  /* ---------------------- DEACTIVATE PATIENT RECORD ---------------------- */
  case "Deactivate Record":

    mysqli_query($conn, "
        UPDATE patient_details
        SET is_active = 0
        WHERE Patient_id = $cid
    ") or die(mysqli_error($conn));

    echo "<script>
            alert('Patient record has been deactivated.');
            window.location.href='Patiententry.php';
          </script>";
    exit();

  /* ---------------------- UPDATE PATIENT RECORD ---------------------- */
  case "Update Record":

    // Convert to uppercase
    $Last_name   = strtoupper($Last_name);
    $First_name  = strtoupper($First_name);
    $Middle_name = strtoupper($Middle_name);
    $Suffix      = strtoupper($Suffix);
    $Sex         = strtoupper($Sex);
    $Birthday    = strtoupper($Birthday);
    $House_nos_street_name = strtoupper($House_nos_street_name);
    $Barangay    = strtoupper($Barangay);
    $Contact_nos = trim($Contact_nos);
    $Prescription_retrieval_method = strtoupper($Prescription_retrieval_method);

    // 🔒 START TRANSACTION
    mysqli_begin_transaction($conn);

    try {

        /* ---------------- DUPLICATE PATIENT CHECK ---------------- */
        $dup_sql = "SELECT Patient_id FROM patient_details 
                    WHERE Last_name = '$Last_name'
                      AND First_name = '$First_name'
                      AND Middle_name = '$Middle_name'
                      AND Suffix = '$Suffix'
                      AND Birthday = '$Birthday'
                      AND Patient_id != $cid";
        $dup_result = mysqli_query($conn, $dup_sql);
        if (mysqli_num_rows($dup_result) > 0) {
            throw new Exception('Duplicate patient record found.');
        }

        /* ---------------- UPDATE PATIENT ---------------- */
        // FIXED: Added Prescription_retrieval_method to the UPDATE query
        mysqli_query($conn, "
            UPDATE patient_details 
            SET Last_name = '$Last_name',
                First_name = '$First_name',
                Middle_name = '$Middle_name',
                Suffix = '$Suffix',
                Sex = '$Sex',
                Birthday = '$Birthday',
                Contact_nos = '$Contact_nos',
                House_nos_street_name = '$House_nos_street_name',
                Barangay = '$Barangay',
                Prescription_retrieval_method = '$Prescription_retrieval_method'
            WHERE Patient_id = $cid
        ") or die(mysqli_error($conn));

        // ✅ COMMIT IF ALL OK
        mysqli_commit($conn);

        echo "<script>
                alert('Patient record successfully updated.');
                window.location.href = 'ptedit.php?c=$cid';
              </script>";
        exit();

    } catch (Exception $e) {

        // ❌ ROLLBACK EVERYTHING
        mysqli_rollback($conn);

        echo "<script>
                alert('{$e->getMessage()}');
                window.location.href = 'ptedit.php?c=$cid';
              </script>";
        exit();
    }

    break;

}

// Redirect after action
header("Location: $redirect");
exit;