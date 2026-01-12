<?php
require('Config/Config.php');
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}
$action = $_REQUEST['action'] ?? '';
$Medicine_id = intval($_REQUEST['m'] ?? 0);

// Connect to DB
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());

switch ($action) {

    /* ---------------------- CREATE ---------------------- */
    case "Add Medicine":
        $Medicine_name = $_POST['Medicine_name'] ?? '';
        $Dose = $_POST['Dose'] ?? '';
        $Form = $_POST['Form'] ?? '';
        
        // Check for empty fields
        if (empty($Medicine_name) || empty($Dose) || empty($Form)) {
            echo "<script>
                    alert('All fields are required!');
                    window.history.back();
                  </script>";
            exit;
        }
        
        // Convert to uppercase and trim
        $Medicine_name = strtoupper(trim($Medicine_name));
        $Dose = strtoupper(trim($Dose));
        $Form = strtoupper(trim($Form));
        
        // Escape inputs
        $Medicine_name_escaped = mysqli_real_escape_string($conn, $Medicine_name);
        $Dose_escaped = mysqli_real_escape_string($conn, $Dose);
        $Form_escaped = mysqli_real_escape_string($conn, $Form);
      
        // duplicate check - check for exact match of concatenated fields
        $dup_sql = "SELECT Medicine_id FROM medicine 
                    WHERE UPPER(TRIM(Medicine_name)) = '$Medicine_name_escaped' 
                    AND UPPER(TRIM(Dose)) = '$Dose_escaped' 
                    AND UPPER(TRIM(Form)) = '$Form_escaped'";
        $dup_result = mysqli_query($conn, $dup_sql);

        if (mysqli_num_rows($dup_result) > 0) {
            echo "<script>
                    alert('This medicine with the same dose and form already exists.');
                    window.history.back();
                  </script>";
            exit;
        }

        // Insert into medicine table
        $sql = "INSERT INTO medicine (Medicine_name, Dose, Form)
                VALUES ('$Medicine_name_escaped', '$Dose_escaped', '$Form_escaped')";
        mysqli_query($conn, $sql) or die(mysqli_error($conn));

        // Check if there's a redirect parameter
        $redirect = $_POST['redirect'] ?? 'medicine.php';
        
        echo "<script>
                alert('Medicine successfully added!');
                window.location.href = '$redirect';
              </script>";
        exit;

    /* ---------------------- UPDATE ---------------------- */
    case "Update Medicine":
        $Medicine_id = intval($_POST['Medicine_id'] ?? 0);
        $Medicine_name = $_POST['Medicine_name'] ?? '';
        $Dose = $_POST['Dose'] ?? '';
        $Form = $_POST['Form'] ?? '';

        // Check for empty fields
        if (empty($Medicine_name) || empty($Dose) || empty($Form)) {
            echo "<script>
                    alert('All fields are required!');
                    window.history.back();
                  </script>";
            exit;
        }

        // Convert to uppercase and trim
        $Medicine_name = strtoupper(trim($Medicine_name));
        $Dose = strtoupper(trim($Dose));
        $Form = strtoupper(trim($Form));
        
        // Escape inputs
        $Medicine_name_escaped = mysqli_real_escape_string($conn, $Medicine_name);
        $Dose_escaped = mysqli_real_escape_string($conn, $Dose);
        $Form_escaped = mysqli_real_escape_string($conn, $Form);

        // duplicate check - excluding current medicine
        $dup_sql = "SELECT Medicine_id FROM medicine
                    WHERE UPPER(TRIM(Medicine_name)) = '$Medicine_name_escaped' 
                    AND UPPER(TRIM(Dose)) = '$Dose_escaped' 
                    AND UPPER(TRIM(Form)) = '$Form_escaped'
                    AND Medicine_id != $Medicine_id";
        $dup_result = mysqli_query($conn, $dup_sql);

        if (mysqli_num_rows($dup_result) > 0) {
            echo "<script>
                    alert('Another medicine with the same name, dose and form already exists.');
                    window.history.back();
                  </script>";
            exit;
        }

        $sql = "UPDATE medicine SET
                    Medicine_name = '$Medicine_name_escaped',
                    Dose = '$Dose_escaped',
                    Form = '$Form_escaped'
                WHERE Medicine_id = $Medicine_id";
        mysqli_query($conn, $sql) or die(mysqli_error($conn));

        echo "<script>
                alert('Medicine updated!');
                window.location.href = 'medicines.php';
              </script>";
        exit;

    /* ---------------------- DELETE ---------------------- */
    // Note: You said "no delete" but I'll include the structure in case you change your mind
    case "Delete Medicine":
        $Medicine_id = intval($_GET['m'] ?? 0);
        
        // First check if medicine is used in any prescriptions
        $check_sql = "SELECT COUNT(*) as count FROM rx WHERE Medicine_id = $Medicine_id";
        $check_result = mysqli_query($conn, $check_sql);
        $row = mysqli_fetch_assoc($check_result);
        
        if ($row['count'] > 0) {
            echo "<script>
                    alert('Cannot delete medicine. It is being used in existing prescriptions.');
                    window.history.back();
                  </script>";
            exit;
        }
        
        $sql = "DELETE FROM medicine WHERE Medicine_id = $Medicine_id";
        mysqli_query($conn, $sql) or die(mysqli_error($conn));

        echo "<script>
                alert('Medicine deleted!');
                window.location.href = 'medicines.php';
              </script>";
        exit;
}
?>