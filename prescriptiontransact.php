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
        
        // refill day (1–31)
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

        // Check for duplicate medicines
        if (isset($_POST['Medicine']) && is_array($_POST['Medicine'])) {
            $medicine_entries = [];
            
            foreach ($_POST['Medicine'] as $med) {
                $dose          = mysqli_real_escape_string($conn, $med['Dose'] ?? '');
                $form          = mysqli_real_escape_string($conn, $med['Form'] ?? '');
                $medicine_name = mysqli_real_escape_string($conn, $med['Medicine_name'] ?? '');
                
                if ($medicine_name === '') continue;
                
                $medicine_key = strtolower($medicine_name . '|' . $dose . '|' . $form);
                
                if (isset($medicine_entries[$medicine_key])) {
                    echo "<script>
                            alert('Duplicate medicine entry detected: \"$medicine_name ($dose $form)\" is already in this prescription.\\n\\nPrescription was NOT created.');
                            window.history.back();
                          </script>";
                    exit;
                }
                
                $medicine_entries[$medicine_key] = true;
            }
        }

        // Get PTR number
        $Ptr_number = '';
        if (!empty($License_number)) {
            $ptrQuery = "SELECT Ptr_number FROM doctors WHERE License_number = '$License_number' LIMIT 1";
            $ptrResult = mysqli_query($conn, $ptrQuery);
            if ($ptrResult && mysqli_num_rows($ptrResult) > 0) {
                $ptrRow = mysqli_fetch_assoc($ptrResult);
                $Ptr_number = $ptrRow['Ptr_number'] ?? '';
            }
        }

        // Check if Ptr_number column exists
        $checkColumnQuery = "SHOW COLUMNS FROM prescription LIKE 'Ptr_number'";
        $columnResult = mysqli_query($conn, $checkColumnQuery);
        $hasPtrColumn = mysqli_num_rows($columnResult) > 0;
        
        if ($hasPtrColumn) {
            $sql = "INSERT INTO prescription (Prescription_id, Patient_id, Date, Age, License_number, Ptr_number, refill_day, creation_type)
                    VALUES (NULL, $Patient_id, '$Date', $Age, '$License_number', '$Ptr_number', $refill_day, 'manual')";
        } else {
            $sql = "INSERT INTO prescription (Prescription_id, Patient_id, Date, Age, License_number, refill_day, creation_type)
                    VALUES (NULL, $Patient_id, '$Date', $Age, '$License_number', $refill_day, 'manual')";
        }
        
        mysqli_query($conn, $sql) or die(mysqli_error($conn));

        $Prescription_id = mysqli_insert_id($conn);

        // Process medicines
        if (isset($_POST['Medicine']) && is_array($_POST['Medicine'])) {
            foreach ($_POST['Medicine'] as $med) {
                $Quantity      = $med['Quantity'] ?? '';
                $Frequency     = $med['Frequency'] ?? '';
                $Days          = $med['Days'] ?? '';
                $dose          = mysqli_real_escape_string($conn, $med['Dose'] ?? '');
                $form          = mysqli_real_escape_string($conn, $med['Form'] ?? '');
                $medicine_name = mysqli_real_escape_string($conn, $med['Medicine_name'] ?? '');
                
                if ($medicine_name === '') continue;

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

                $insertRx = "INSERT INTO rx (Prescription_id, Medicine_id, Quantity, Frequency, Days)
                             VALUES ($Prescription_id, $medicine_id, '$Quantity', '$Frequency', '$Days')";
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
        
        // Validate for duplicate medicines and collect valid medicines
        $medicine_entries = [];
        $valid_medicines = [];
        $has_valid_medicines = false;
        
        if (isset($medicines) && is_array($medicines)) {
            foreach ($medicines as $index => $med) {
                $medicine_name = trim($med['Medicine_name'] ?? '');
                $dose = trim($med['Dose'] ?? '');
                $form = trim($med['Form'] ?? '');
                $frequency = trim($med['Frequency'] ?? '');
                $quantity = trim($med['Quantity'] ?? '');
                
                // Skip completely empty medicine entries
                if (empty($medicine_name) && empty($dose) && empty($form) && empty($frequency) && empty($quantity)) {
                    continue;
                }
                
                // If medicine name is empty but other fields have values, show error
                if (empty($medicine_name) && (!empty($dose) || !empty($form) || !empty($frequency) || !empty($quantity))) {
                    echo "<script>
                            alert('Medicine #" . ($index + 1) . ": Medicine name is required when other fields are filled.');
                            window.history.back();
                          </script>";
                    exit;
                }
                
                $medicine_key = strtolower($medicine_name . '|' . $dose . '|' . $form);
                
                if (isset($medicine_entries[$medicine_key])) {
                    echo "<script>
                            alert('Duplicate medicine entry detected: \"$medicine_name ($dose $form)\" is already in this prescription.\\n\\nPrescription was NOT updated.');
                            window.history.back();
                          </script>";
                    exit;
                }
                
                $medicine_entries[$medicine_key] = true;
                $valid_medicines[] = $med;
                $has_valid_medicines = true;
            }
        }
        
        // Get PTR number
        $Ptr_number = '';
        if (!empty($license_number)) {
            $ptrQuery = "SELECT Ptr_number FROM doctors WHERE License_number = '$license_number' LIMIT 1";
            $ptrResult = mysqli_query($conn, $ptrQuery);
            if ($ptrResult && mysqli_num_rows($ptrResult) > 0) {
                $ptrRow = mysqli_fetch_assoc($ptrResult);
                $Ptr_number = $ptrRow['Ptr_number'] ?? '';
            }
        }
        
        // Check if Ptr_number column exists
        $checkColumnQuery = "SHOW COLUMNS FROM prescription LIKE 'Ptr_number'";
        $columnResult = mysqli_query($conn, $checkColumnQuery);
        $hasPtrColumn = mysqli_num_rows($columnResult) > 0;
        
        // Update prescription
        if ($hasPtrColumn) {
            $sql = "UPDATE prescription SET 
                    Refill_day = '$refill_day',
                    License_number = '$license_number',
                    Ptr_number = '$Ptr_number',
                    Age = '$age',
                    creation_type = 'MANUAL'
                    WHERE Prescription_id = '$prescription_id'";
        } else {
            $sql = "UPDATE prescription SET 
                    Refill_day = '$refill_day',
                    License_number = '$license_number',
                    Age = '$age',
                    creation_type = 'MANUAL'
                    WHERE Prescription_id = '$prescription_id'";
        }
        
        mysqli_query($conn, $sql) or die(mysqli_error($conn));
        
        // Handle medicines - ONLY delete if there are valid medicines
        if ($has_valid_medicines) {
            // Delete existing medicines
            $deleteSql = "DELETE FROM rx WHERE Prescription_id = '$prescription_id'";
            mysqli_query($conn, $deleteSql) or die(mysqli_error($conn));
            
            // Insert updated medicines
            foreach ($valid_medicines as $med) {
                $medicine_name = mysqli_real_escape_string($conn, $med['Medicine_name'] ?? '');
                $dose = mysqli_real_escape_string($conn, $med['Dose'] ?? '');
                $form = mysqli_real_escape_string($conn, $med['Form'] ?? '');
                $frequency = mysqli_real_escape_string($conn, $med['Frequency'] ?? '');
                $quantity = mysqli_real_escape_string($conn, $med['Quantity'] ?? '');
                $days = isset($med['Days']) ? intval($med['Days']) : 0;
                
                if (empty($medicine_name)) continue;
                
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
                
                $insertRx = "INSERT INTO rx (Prescription_id, Medicine_id, Quantity, Frequency, Days)
                             VALUES ('$prescription_id', '$medicine_id', '$quantity', '$frequency', '$days')";
                mysqli_query($conn, $insertRx) or die(mysqli_error($conn));
            }
        }
        // If no valid medicines, we DON'T delete existing ones - they remain unchanged
        
        echo "<script>
                alert('Prescription successfully updated!');
                window.location.href = 'ptedit.php?c=$patient_id';
              </script>";
        exit;
}
?>