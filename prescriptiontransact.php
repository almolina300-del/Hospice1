<?php
require('Config/Config.php');
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

$action = $_REQUEST['action'] ?? '';
$Prescription_id = intval($_REQUEST['p'] ?? 0);

// Connect to DB
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());

switch ($action) {

 /* ---------------------- CREATE ---------------------- */
case "Add Prescription":
    $Patient_id = intval($_POST['Patient_id']);
    $Date = $_POST['Date'] ?? '';
    $License_number = $_POST['License_number'] ?? '';
    $Age = intval($_POST['Age'] ?? 0);
    
    // NEW: refill day (1–31)
    $refill_day = isset($_POST['refill_day']) ? (int)$_POST['refill_day'] : 0;
    if ($refill_day < 1 || $refill_day > 31) {
        echo "<script>
                alert('Invalid refill day (must be 1–31).');
                window.history.back();
              </script>";
        exit;
    }

    // Duplicate check for same patient/date
    $dup_sql = "SELECT Prescription_id FROM prescription 
                WHERE Patient_id = $Patient_id AND Date = '$Date'";
    $dup_result = mysqli_query($conn, $dup_sql);
    if (mysqli_num_rows($dup_result) > 0) {
        echo "<script>
                alert('A prescription for this patient on the same date already exists.');
                window.history.back();
              </script>";
        exit;
    }

    // FIRST: Check for duplicate medicines in the submitted data BEFORE creating prescription
    if (isset($_POST['Medicine']) && is_array($_POST['Medicine'])) {
        $medicine_entries = []; // Array to track unique medicine combinations
        
        foreach ($_POST['Medicine'] as $med) {
            $dose          = mysqli_real_escape_string($conn, $med['Dose'] ?? '');
            $form          = mysqli_real_escape_string($conn, $med['Form'] ?? '');
            $medicine_name = mysqli_real_escape_string($conn, $med['Medicine_name'] ?? '');
            
            if ($medicine_name === '') continue;
            
            // Create a unique key for this medicine combination
            $medicine_key = strtolower($medicine_name . '|' . $dose . '|' . $form);
            
            // Check for duplicate medicine within the same prescription
            if (isset($medicine_entries[$medicine_key])) {
                echo "<script>
                        alert('Duplicate medicine entry detected: \"$medicine_name ($dose $form)\" is already in this prescription.\\n\\nPrescription was NOT created.');
                        window.history.back();
                      </script>";
                exit;
            }
            
            // Mark this medicine combination as used
            $medicine_entries[$medicine_key] = true;
        }
    }

    // Only proceed with prescription creation if no duplicates found
    // Get PTR number from doctors table
    $Ptr_number = '';
    if (!empty($License_number)) {
        $ptrQuery = "SELECT Ptr_number FROM doctors WHERE License_number = '$License_number' LIMIT 1";
        $ptrResult = mysqli_query($conn, $ptrQuery);
        if ($ptrResult && mysqli_num_rows($ptrResult) > 0) {
            $ptrRow = mysqli_fetch_assoc($ptrResult);
            $Ptr_number = $ptrRow['Ptr_number'] ?? '';
        }
    }

    // Check if Ptr_number column exists in prescription table (same check as UPDATE)
    $checkColumnQuery = "SHOW COLUMNS FROM prescription LIKE 'Ptr_number'";
    $columnResult = mysqli_query($conn, $checkColumnQuery);
    $hasPtrColumn = mysqli_num_rows($columnResult) > 0;
    
    if ($hasPtrColumn) {
        // Insert into prescription table WITH Ptr_number
        $sql = "INSERT INTO prescription (Prescription_id, Patient_id, Date, Age, License_number, Ptr_number, refill_day, creation_type)
                VALUES (NULL, $Patient_id, '$Date', $Age, '$License_number', '$Ptr_number', $refill_day, 'manual')";
    } else {
        // Insert into prescription table WITHOUT Ptr_number
        $sql = "INSERT INTO prescription (Prescription_id, Patient_id, Date, Age, License_number, refill_day, creation_type)
                VALUES (NULL, $Patient_id, '$Date', $Age, '$License_number', $refill_day, 'manual')";
    }
    
    mysqli_query($conn, $sql) or die(mysqli_error($conn));

    $Prescription_id = mysqli_insert_id($conn);

    // Process medicines (now we know there are no duplicates)
    if (isset($_POST['Medicine']) && is_array($_POST['Medicine'])) {
        foreach ($_POST['Medicine'] as $med) {
            $Quantity      = $med['Quantity'] ?? '';
            $Frequency     = $med['Frequency'] ?? '';
            $dose          = mysqli_real_escape_string($conn, $med['Dose'] ?? '');
            $form          = mysqli_real_escape_string($conn, $med['Form'] ?? '');
            $medicine_name = mysqli_real_escape_string($conn, $med['Medicine_name'] ?? '');
            if ($medicine_name === '') continue;

            // Check if medicine exists
            $checkMed = "SELECT Medicine_id FROM medicine 
                         WHERE Medicine_name = '$medicine_name' 
                           AND Dose = '$dose' 
                           AND Form = '$form'";
            $medResult = mysqli_query($conn, $checkMed);
            
            if (mysqli_num_rows($medResult) > 0) {
                $medRow = mysqli_fetch_assoc($medResult);
                $medicine_id = $medRow['Medicine_id'];
            } else {
                $insertMed = "INSERT INTO medicine (Medicine_name, Dose, Form)
                              VALUES ('$medicine_name', '$dose', '$form')";
                mysqli_query($conn, $insertMed) or die(mysqli_error($conn));
                $medicine_id = mysqli_insert_id($conn);
            }

            // Insert into rx table
            $insertRx = "INSERT INTO rx (Prescription_id, Medicine_id, Quantity, Frequency)
                         VALUES ($Prescription_id, $medicine_id, '$Quantity', '$Frequency')";
            mysqli_query($conn, $insertRx) or die(mysqli_error($conn));
        }
    }

    echo "<script>
            alert('Prescription successfully created!');
            window.location.href = 'ptedit.php?c=$Patient_id';
          </script>";
    exit;

/* ---------------------- UPDATE ---------------------- */
case 'Update Prescription':
    $prescription_id = $_POST['Prescription_id'] ?? 0;
    $patient_id = $_POST['Patient_id'] ?? 0;
    $date = $_POST['Date'] ?? date('Y-m-d');
    $refill_day = $_POST['refill_day'] ?? null;
    $license_number = $_POST['License_number'] ?? '';
    $age = $_POST['Age'] ?? 0;
    $medicines = $_POST['Medicine'] ?? [];
    
    // FIRST: Validate for duplicate medicines BEFORE making any updates
    $medicine_entries = [];
    foreach ($medicines as $med) {
        $medicine_name = $med['Medicine_name'] ?? '';
        $dose = $med['Dose'] ?? '';
        $form = $med['Form'] ?? '';
        
        if (empty($medicine_name)) continue;
        
        // Create a unique key for this medicine combination
        $medicine_key = strtolower($medicine_name . '|' . $dose . '|' . $form);
        
        // Check for duplicate medicine within the same prescription
        if (isset($medicine_entries[$medicine_key])) {
            echo "<script>
                    alert('Duplicate medicine entry detected: \"$medicine_name ($dose $form)\" is already in this prescription.\\n\\nPrescription was NOT updated.');
                    window.history.back();
                  </script>";
            exit;
        }
        
        // Mark this medicine combination as used
        $medicine_entries[$medicine_key] = true;
    }
    
    // Only proceed with update if no duplicates found
    // Get PTR number from doctors table
    $Ptr_number = '';
    if (!empty($license_number)) {
        $ptrQuery = "SELECT Ptr_number FROM doctors WHERE License_number = '$license_number' LIMIT 1";
        $ptrResult = mysqli_query($conn, $ptrQuery);
        if ($ptrResult && mysqli_num_rows($ptrResult) > 0) {
            $ptrRow = mysqli_fetch_assoc($ptrResult);
            $Ptr_number = $ptrRow['Ptr_number'] ?? '';
        }
    }
    
    // Check if Ptr_number column exists in prescription table
    $checkColumnQuery = "SHOW COLUMNS FROM prescription LIKE 'Ptr_number'";
    $columnResult = mysqli_query($conn, $checkColumnQuery);
    $hasPtrColumn = mysqli_num_rows($columnResult) > 0;
    
    if ($hasPtrColumn) {
        // Update prescription WITH Ptr_number
        $sql = "UPDATE prescription SET 
                Refill_day = ?,
                License_number = ?,
                Ptr_number = ?,
                Age = ?,
                creation_type = 'MANUAL'
                WHERE Prescription_id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssii', $refill_day, $license_number, $Ptr_number, $age, $prescription_id);
    } else {
        // Update prescription WITHOUT Ptr_number (column doesn't exist)
        $sql = "UPDATE prescription SET 
                Refill_day = ?,
                License_number = ?,
                Age = ?,
                creation_type = 'MANUAL'
                WHERE Prescription_id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssii', $refill_day, $license_number, $age, $prescription_id);
    }
    
    mysqli_stmt_execute($stmt);
    
    // Delete existing medicines
    $sql = "DELETE FROM rx WHERE Prescription_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $prescription_id);
    mysqli_stmt_execute($stmt);
    
    // Insert updated medicines
    foreach ($medicines as $med) {
        $medicine_name = $med['Medicine_name'] ?? '';
        $dose = $med['Dose'] ?? '';
        $form = $med['Form'] ?? '';
        $frequency = $med['Frequency'] ?? '';
        $quantity = $med['Quantity'] ?? '';
        
        if (empty($medicine_name)) continue;
        
        $sql = "SELECT Medicine_id FROM medicine 
                WHERE Medicine_name = ? AND Dose = ? AND Form = ?
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sss', $medicine_name, $dose, $form);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $medicine_id = $row['Medicine_id'];
            
            $sql = "INSERT INTO rx (Prescription_id, Medicine_id, Frequency, Quantity) 
                    VALUES (?, ?, ?, ?)";
            $stmt2 = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt2, 'iiss', 
                $prescription_id, 
                $medicine_id, 
                $frequency, 
                $quantity
            );
            mysqli_stmt_execute($stmt2);
        }
    }
    
    // Show confirmation message before redirecting
    echo "<script>
            alert('Prescription successfully updated!');
            window.location.href = 'Ptedit.php?c=$patient_id';
          </script>";
    exit();
}
?>