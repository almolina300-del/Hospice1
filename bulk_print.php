<!DOCTYPE html>
<?php
session_start();

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}
require('Config/Config.php');

// Connect to MySQL
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect to MySQL database. ' . mysqli_connect_error());

// Handle search by Refill Date
$Refill_day = '';
if (isset($_POST['dosearch']) && !empty($_POST['dosearch'])) {
    $Refill_day = $_POST['dosearch'];
}

// Check if bulk prescriptions were just created
$bulk_result = [];
$message = '';
$message_class = '';
$has_prescriptions_for_pdf = false;
$prescription_ids_for_pdf = '';
$refill_day_for_pdf = '';

if (isset($_GET['bulk_created']) && isset($_SESSION['bulk_print_result'])) {
    $bulk_result = $_SESSION['bulk_print_result'];

    if ($bulk_result['success']) {
        // Check which IDs to use for PDF
        if (!empty($bulk_result['all_prescription_ids'])) {
            $prescription_ids_for_pdf = implode(',', $bulk_result['all_prescription_ids']);
            $has_prescriptions_for_pdf = true;
            $refill_day_for_pdf = $bulk_result['refill_day'] ?? $Refill_day;
        } else {
            // Fallback to created_ids for backward compatibility
            $created_ids = $bulk_result['created_ids'] ?? [];
            if (!empty($created_ids)) {
                $prescription_ids_for_pdf = implode(',', $created_ids);
                $has_prescriptions_for_pdf = true;
                $refill_day_for_pdf = $bulk_result['refill_day'] ?? $Refill_day;
            }
        }

        $message = "✅ Successfully processed " . ($bulk_result['count'] ?? 0) . " prescriptions";
        $message .= " for refill day " . ($bulk_result['refill_day'] ?? '');
        $message .= " (Date: " . ($bulk_result['prescription_date'] ?? '') . ")";
        $message .= " with doctor: " . ($bulk_result['doctor_name'] ?? '');
        $message_class = "success";

        // Add details about new vs existing if available
        if (isset($bulk_result['created_count']) && isset($bulk_result['existing_count'])) {
            $created_count = $bulk_result['created_count'];
            $existing_count = $bulk_result['existing_count'];

            if ($created_count > 0 && $existing_count > 0) {
                $message .= "<br><small>✓ $created_count new prescriptions created";
                $message .= "<br>✓ $existing_count existing prescriptions included</small>";
            } elseif ($existing_count > 0) {
                $message .= "<br><small>✓ Using $existing_count existing prescriptions (no duplicates created)</small>";
            } elseif ($created_count > 0) {
                $message .= "<br><small>✓ Created $created_count new prescriptions</small>";
            }
        }

        if (!empty($bulk_result['errors'])) {
            $message .= "<br><small>⚠️ Note: " . count($bulk_result['errors']) . " errors occurred</small>";
            $message_class = "warning";
        }
    } else {
        $message = "❌ No prescriptions were processed. Please check your selection.";
        $message_class = "error";
    }

    // Clear the session after displaying
    unset($_SESSION['bulk_print_result']);
}

// Get all patients for the selected refill day with their last prescription date
$patients = [];
$total_patients = 0;

if ($Refill_day != '') {
    $patient_sql = "SELECT 
        p.Prescription_id,
        p.Patient_id,
        p.Date as last_prescription_date,
        CONCAT(pat.Last_name, ', ', pat.First_name, ' ', COALESCE(pat.Middle_name, '')) AS Patient_name,
        pat.Barangay,
        pat.House_nos_street_name,  -- Add this line
        p.Refill_day
    FROM prescription p
    LEFT JOIN patient_details pat ON p.Patient_id = pat.Patient_id
    WHERE p.Prescription_id IN (
        SELECT MAX(p2.Prescription_id) 
        FROM prescription p2 
        WHERE p2.Patient_id = p.Patient_id 
        GROUP BY p2.Patient_id
    )
    AND p.Refill_day = ?
    AND pat.is_active = 1
    ORDER BY pat.Last_name, pat.First_name";
    
    $stmt = mysqli_prepare($conn, $patient_sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $Refill_day);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $patients[] = $row;
            $total_patients++;
        }
        mysqli_stmt_close($stmt);
    }
}

// Get distinct refill days for patients
$refill_day_counts_sql = "SELECT p.Refill_day, COUNT(DISTINCT p.Patient_id) as patient_count
    FROM prescription p
    WHERE p.Prescription_id IN (
        SELECT MAX(p2.Prescription_id) 
        FROM prescription p2 
        GROUP BY p2.Patient_id
    )
    AND p.Patient_id IN (
        SELECT Patient_id FROM patient_details WHERE is_active = 1
    )
    GROUP BY p.Refill_day
    ORDER BY p.Refill_day";

$refill_day_counts_result = mysqli_query($conn, $refill_day_counts_sql);
$refill_day_counts = [];
while ($row = mysqli_fetch_assoc($refill_day_counts_result)) {
    $refill_day_counts[$row['Refill_day']] = $row['patient_count'];
}

// Fetch doctors for modal dropdown (including PTR number)
$docQuery = "SELECT License_number, Last_name, First_name, Middle_name, Ptr_number 
             FROM doctors 
             WHERE is_active = 1 
             ORDER BY Last_name ASC";
$docResult = mysqli_query($conn, $docQuery);

// Get today's date for the date input (in PHP)
$today_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html>

<head>
    <title>Bulk Print - Hospice</title>
    <link rel="stylesheet" type="text/css" href="CSS/style.css">
    <style>
        /* Loading overlay styles */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #263F73;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            font-size: 18px;
            color: #263F73;
            font-weight: bold;
        }

        /* Modal styling */
        #printModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            margin-top: 0;
            color: #263F73;
            border-bottom: 2px solid #263F73;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .required {
            color: red;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .doctor-selection-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }

        .doctor-field {
            flex: 1;
            min-width: 200px;
        }

        .doctor-field label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
            font-size: 13px;
        }

        .doctor-field input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .doctor-field input[readonly] {
            background-color: #f5f5f5;
        }

        .date-selection-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }

        .date-field {
            flex: 1;
        }

        .date-note {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        .date-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        /* Patient list table styles */
        .patient-list-container {
            max-height: 400px;
            overflow-y: auto;
            border: 0px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .patient-table {
            width: 95%;
            border-collapse: collapse;
            margin-left: 20px;
        }

        .patient-table th {
            background-color: #263F73;
            color: white;
            padding: 10px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .patient-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .patient-table tr:hover {
            background-color: #f5f5f5;
        }

        .patient-table tr.excluded {
            opacity: 0.6;
            background-color: #fff5f5 !important;
        }

        .patient-table tr.excluded td {
            color: #999;
        }

        .checkbox-cell {
            width: 40px;
            text-align: center;
        }

        .patient-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Summary and action buttons */
        .selection-summary {
            background: #e3f2fd;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #bbdefb;
            font-weight: bold;
            color: #1565c0;
        }

        .select-all-container {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }

        .select-all-label {
            font-weight: bold;
            color: #263F73;
            margin-right: 10px;
        }

        .modal-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .btn-submit {
            background: #28a745;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #218838;
        }

        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-submit.loading {
            pointer-events: none;
        }

        .btn-submit .spinner-small {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }

        .btn-submit.loading .spinner-small {
            display: block;
        }

        /* Status indicators */
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-included {
            background-color: #28a745;
        }

        .status-excluded {
            background-color: #dc3545;
        }

        /* Processing overlay */
        #modalProcessingOverlay {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            z-index: 100;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        #processingTimer {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }

        #processingProgress {
            width: 80%;
            background: #f0f0f0;
            height: 8px;
            border-radius: 4px;
            margin-top: 15px;
            overflow: hidden;
        }

        #progressBar {
            width: 0%;
            height: 100%;
            background: #28a745;
            transition: width 5s linear;
        }

        /* Refill day info */
        .refill-day-info {
            background: #263F73;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }

        /* PDF Loader */
        #pdfLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10001;
            flex-direction: column;
        }

        .pdf-spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid #263F73;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        .pdf-loading-text {
            font-size: 20px;
            color: #263F73;
            font-weight: bold;
        }

        /* Success message */
        .success-message {
            text-align: center;
            margin: 10px 0 10px 240px;
            padding: 12px 20px;
            border-radius: 5px;
            width: 80%;
            font-size: 14px;
            font-weight: bold;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Print button in main page */
        .btn-print {
            background: #263F73;
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: bold;
        }

        .btn-print:hover {
            background: #1e3260;
        }

        .btn-disabled {
            background: #ccc;
            cursor: not-allowed;
            pointer-events: none;
            position: relative;
        }

        .disabled-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #DC143C;
            font-weight: bold;
            font-size: 35px;
            pointer-events: none;
        }

        /* Close button */
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover {
            background-color: #f0f0f0;
            color: #333;
        }
    </style>
</head>

<body>
    <!-- Loading Overlay for Search -->
    <div id="loadingOverlay">
        <div class="spinner"></div>
        <div class="loading-text">Searching Records...</div>
    </div>

    <!-- PDF Generation Loader -->
    <div id="pdfLoader">
        <div class="pdf-spinner"></div>
        <div class="pdf-loading-text">Generating PDF...</div>
    </div>

    <div class="sidebar">
        <!-- Welcome message with first_name above logout -->
        <?php if (isset($_SESSION['First_name'])): ?>
            <div class="welcome-user" style="color: white; text-align: center; padding: 15px; margin-bottom: 10px; background: rgba(255,255,255,0.1); border-radius: 5px;">
                <div style="font-size: 25px; color: white; font-weight: bold; margin-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 5px;">
                    Prescription
                </div> <br>
                <img src="img/user_icon.png" alt="User Icon" style="width: 30px; height: 30px; filter: brightness(0) invert(1);"><br>
                Welcome,<br>
                <?php if (isset($_SESSION['Role'])): ?>
                    <div style="margin-top: 5px; font-size: 12px; color: rgba(255,255,255,0.8);">
                        <?php echo htmlspecialchars($_SESSION['Role']); ?>
                    </div>
                <?php endif; ?>
                <div style="display: flex; align-items: center; justify-content: center">
                    <strong style="font-size: 15px;"><?php echo htmlspecialchars($_SESSION['First_name']); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <a href="patiententry.php">
            Patient Records
        </a>
        <a href="bulk_print.php" style="background-color: whitesmoke; padding: 8px 12px; border-radius: 0px; display: inline-block; margin: 4px 0; text-decoration: none; color: #263F73; font-weight: bold;">
            Bulk Print
        </a>

        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <a href="Doctors.php">
                Doctors
            </a><?php endif; ?>
        <a href="Medicines.php">Medicines</a>

        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <a href="user_management.php">
                User Management
            </a><?php endif; ?>

        <div class="spacer"></div>
        <div class="logout-container">
            <script>
                function confirmLogout() {
                    return confirm("Are you sure you want to log out?");
                }
            </script>
            <div style="font-size: 20px; color: white; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 8px;">
            </div>
            <a href="logout.php" class="logout-btn" onclick="return confirmLogout();"
                style="display: flex; align-items: center; justify-content: left; gap: 8px; 
                text-decoration: none; color: white; padding: 10px; 
                background: rgba(255,255,255,0.1); border-radius: 5px; 
                transition: background 0.3s;">
                <img src="img/logout_icon.png" alt="Logout" class="logo" style="width: 24px; height: 24px;">
                <span>Logout</span>
            </a>
        </div>
    </div>

    <h1 align="center">
        <img src="img/printer.png" alt="bulk_print_icon" class="logo">
        Bulk Printing
    </h1>

    <?php if (!empty($message)): ?>
        <div id="successMessage" class="success-message <?php echo $message_class; ?>">
            <?php echo $message; ?>

            <?php if ($has_prescriptions_for_pdf && !empty($prescription_ids_for_pdf)): ?>
                <div style="margin-top:10px;">
                    <a href="javascript:void(0);"
                        onclick="generateBulkPDF('<?php echo $prescription_ids_for_pdf; ?>', '<?php echo htmlspecialchars($refill_day_for_pdf); ?>')"
                        style="background:#007bff; color:white; padding:8px 16px; border-radius:4px; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M2 7a2 2 0 0 0-2 2v3h4v3h8v-3h4V9a2 2 0 0 0-2-2H2zm11 5H3v-3h10v3zM2 4V0h12v4H2z" />
                        </svg>
                        View/Print PDF
                    </a>
                </div>
            <?php endif; ?>

            <?php if (isset($bulk_result['errors']) && !empty($bulk_result['errors'])): ?>
                <div style="margin-top:10px; text-align:left; font-weight:normal; font-size:12px;">
                    <strong>Error Details:</strong>
                    <ul style="margin:5px 0; padding-left:20px;">
                        <?php foreach ($bulk_result['errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="" style="text-align:center; margin-bottom:20px;">
        <label for="dosearch" style="font-weight:bold;">Search by Refill Day:</label>
        <select name="dosearch" id="dosearch" style="padding:5px; margin-left:10px;">
            <option value="">Select day</option>
            <?php
            for ($i = 1; $i <= 31; $i++) {
                $selected = ($Refill_day == $i) ? 'selected' : '';
                $count = isset($refill_day_counts[$i]) ? $refill_day_counts[$i] : 0;
                $label = $count > 0 ? "Day $i ($count patients)" : "Day $i";
                echo "<option value='$i' $selected>$label</option>";
            }
            ?>
        </select>

        <button type="submit" class="search-btn" style="display:inline-flex; align-items:center; padding:10px 12px; margin-left:10px; background:#263F73; color:white; border:none; border-radius:5px; cursor:pointer;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="15" fill="white" viewBox="0 0 119.828 122.88" style="margin-right:5px;">
                <g>
                    <path d="M48.319,0C61.662,0,73.74,5.408,82.484,14.152c8.744,8.744,14.152,20.823,14.152,34.166
                    c0,12.809-4.984,24.451-13.117,33.098c0.148,0.109,0.291,0.23,0.426,0.364l34.785,34.737c1.457,1.449,1.465,3.807,0.014,5.265
                    c-1.449,1.458-3.807,1.464-5.264,0.015L78.695,87.06c-0.221-0.22-0.408-0.46-0.563-0.715c-8.213,6.447-18.564,10.292-29.814,10.292
                    c-13.343,0-25.423-5.408-34.167-14.152C5.408,73.741,0,61.661,0,48.318s5.408-25.422,14.152-34.166
                    C22.896,5.409,34.976,0,48.319,0 L48.319,0z M77.082,19.555c-7.361-7.361-17.53-11.914-28.763-11.914c-11.233,0-21.403,4.553-28.764,11.914
                    C12.194,26.916,7.641,37.085,7.641,48.318c0,11.233,4.553,21.403,11.914,28.764c7.36,7.361,17.53,11.914,28.764,11.914
                    c11.233,0,21.402-4.553,28.763-11.914c7.361-7.36,11.914-17.53,11.914-28.764C88.996,37.085,84.443,26.916,77.082,19.555 L77.082,19.555z" />
                </g>
            </svg>
            Search
        </button>

        <a href="bulk_print.php" class="btn-print" style="background:#DC143C;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 118.66" width="16" height="16" fill="white">
                <g>
                    <path d="M106.2,22.2c1.78,2.21,3.43,4.55,5.06,7.46c5.99,10.64,8.52,22.73,7.49,34.54c-1.01,11.54-5.43,22.83-13.37,32.27 
                    c-2.85,3.39-5.91,6.38-9.13,8.97c-11.11,8.93-24.28,13.34-37.41,13.22c-13.13-0.13-26.21-4.78-37.14-13.98 
                    c-3.19-2.68-6.18-5.73-8.91-9.13C6.38,87.59,2.26,78.26,0.71,68.41c-1.53-9.67-0.59-19.83,3.07-29.66 
                    c3.49-9.35,8.82-17.68,15.78-24.21C26.18,8.33,34.29,3.76,43.68,1.48c2.94-0.71,5.94-1.18,8.99-1.37c3.06-0.2,6.19-0.13,9.4,0.22 
                    c2.01,0.22,3.46,2.03,3.24,4.04c-0.22,2.01-2.03,3.46-4.04,3.24c-2.78-0.31-5.49-0.37-8.14-0.2c-2.65,0.17-5.23,0.57-7.73,1.17 
                    c-8.11,1.96-15.1,5.91-20.84,11.29C18.43,25.63,13.72,33,10.62,41.3c-3.21,8.61-4.04,17.51-2.7,25.96 
                    c1.36,8.59,4.96,16.74,10.55,23.7c2.47,3.07,5.12,5.78,7.91,8.13c9.59,8.07,21.03,12.15,32.5,12.26c11.47,0.11,23-3.76,32.76-11.61 
                    c2.9-2.33,5.62-4.98,8.13-7.97c6.92-8.22,10.77-18.09,11.66-28.2c0.91-10.37-1.32-20.99-6.57-30.33c-1.59-2.82-3.21-5.07-5.01-7.24 
                    l-0.53,14.7c-0.07,2.02-1.76,3.6-3.78,3.52c 2.02-0.07-3.6-1.76-3.52-3.78l0.85-23.42c0.07-2.02,1.76-3.6,3.78-3.52 
                    c0.13,0,0.25,0.02,0.37,0.03l0,0l22.7,3.19c2,0.28,3.4,2.12,3.12,4.13c-0.28,2-2.12,3.4-4.13,3.12L106.2,22.2L106.2,22.2z" />
                </g>
            </svg>
            Reset
        </a>

        <?php if ($Refill_day != '' && $total_patients > 0): ?>
            <a href="javascript:void(0);" onclick="openPrintModal()" class="btn-print">
                <svg xmlns="http://www.w3.org/2000/svg" width="25" height="21" fill="white" viewBox="0 0 16 15">
                    <path d="M2 7a2 2 0 0 0-2 2v3h4v3h8v-3h4V9a2 2 0 0 0-2-2H2zm11 5H3v-3h10v3zM2 4V0h12v4H2z" />
                </svg>
                Print (<?php echo $total_patients; ?> patients)
            </a>
        <?php else: ?>
            <a href="javascript:void(0);" class="btn-print btn-disabled">
                <span class="disabled-overlay">&#10006;</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="25" height="21" fill="white" viewBox="0 0 16 15">
                    <path d="M2 7a2 2 0 0 0-2 2v3h4v3h8v-3h4V9a2 2 0 0 0-2-2H2zm11 5H3v-3h10v3zM2 4V0h12v4H2z" />
                </svg>
                Print
            </a>
        <?php endif; ?>
    </form>

    <hr style="margin: 20px 250px; border: 1px solid #ccc; width: 80%;">
    <br>

    <div style="margin: -15px auto; width:100%; border:1px solid #f5f5f5;">
        <?php
        echo "<table align='center' border='1' cellpadding='2' width='100%'>";
        echo "<tr style='background-color:#263F73; color:white;'>
                <th>Patient Name</th>
                <th>Address</th>
            </tr>";

        $bg = 'F2F2FF';

        if ($total_patients > 0) {
            foreach ($patients as $patient) {
                $bg = ($bg == 'F2F2FF') ? 'E2E2F2' : 'F2F2FF';
                
               echo "<tr bgcolor='#$bg'>
        <td align='center'>" . strtoupper($patient['Patient_name'] ?? '') . "</td>
        <td align='center'>" . 
            (!empty($patient['House_nos_street_name']) ? 
                strtoupper($patient['House_nos_street_name'] . ', ' . $patient['Barangay']) : 
                strtoupper($patient['Barangay'] ?? '')
            ) . 
        "</td>
    </tr>";
            }
        } else {
            echo "<tr>
                    <td colspan='3' align='center' style='padding:10px;'>No patients found. Please select a refill day.</td>
                </tr>";
        }

        echo "</table>";
        ?>
    </div>

    <!-- Print Modal with Everything -->
    <div id="printModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closePrintModal()">&times;</button>
            
            <h3 class="modal-header">Bulk Print - Refill Day <?php echo htmlspecialchars($Refill_day); ?></h3>
            
            <div class="refill-day-info">
                Total Patients Found: <?php echo $total_patients; ?>
            </div>

            <form id="printForm" method="post" action="bulk_print_process.php">
                <input type="hidden" name="dosearch" value="<?php echo htmlspecialchars($Refill_day); ?>">
                <input type="hidden" name="selected_patients" id="selectedPatientsInput">

                <!-- Doctor Selection -->
                <div class="doctor-selection-container">
                    <div class="doctor-field">
                        <label class="form-label">
                            Doctor Name: <span class="required">*</span>
                        </label>
                        <input list="doctorsList"
                            id="doctorNameModal"
                            name="DoctorName"
                            required
                            placeholder="Type doctor name"
                            oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div class="doctor-field">
                        <label class="form-label">License Number</label>
                        <input type="text"
                            id="doctorLicenseModal"
                            name="License_number"
                            readonly
                            placeholder="Auto-filled">
                    </div>

                    <div class="doctor-field">
                        <label class="form-label">PTR Number</label>
                        <input type="text"
                            id="doctorPtrModal"
                            name="Ptr_number"
                            readonly
                            placeholder="Auto-filled">
                    </div>
                </div>

                <datalist id="doctorsList">
                    <?php
                    if ($docResult && mysqli_num_rows($docResult) > 0) {
                        mysqli_data_seek($docResult, 0);
                        while ($doc = mysqli_fetch_assoc($docResult)) {
                            $DoctorName = trim($doc['Last_name'] . ', ' . $doc['First_name'] . ' ' . $doc['Middle_name']);
                            $LicenseNo  = htmlspecialchars($doc['License_number']);
                            $PtrNumber  = htmlspecialchars($doc['Ptr_number'] ?? '');
                            echo "<option value=\"{$DoctorName}\" data-license=\"{$LicenseNo}\" data-ptr=\"{$PtrNumber}\"></option>";
                        }
                    }
                    ?>
                </datalist>

                <!-- Date Selection -->
                <div class="date-selection-container">
                    <div class="date-field">
                        <label class="form-label">
                            Prescription Date: <span class="required">*</span>
                        </label>
                        <input type="date" name="prescription_date" id="prescription_date"
                            required class="form-control">
                        <div class="date-error" id="dateError">You cannot select past dates. Please select today or a future date.</div>
                        <div class="date-note">Note: You can only select today's date or future dates</div>
                    </div>
                </div>

                <!-- Patient List with Checkboxes -->
                <div class="select-all-container">
                    <label class="select-all-label">
                        <input type="checkbox" id="selectAll" class="patient-checkbox" checked>
                        Select/Deselect All Patients
                    </label>
                    <span style="margin-left: 20px; color: #666;">
                        <span class="status-indicator status-included"></span> Included: <span id="includedCount"><?php echo $total_patients; ?></span>
                        <span style="margin-left: 15px;">
                            <span class="status-indicator status-excluded"></span> Excluded: <span id="excludedCount">0</span>
                        </span>
                    </span>
                </div>

 <div class="patient-list-container">
    <table class="patient-table">
        <thead>
            <tr>
                <th class="checkbox-cell">Include</th>
                <th>Patient Name</th>
                <th>Address</th>
                <th class="sortable-header" onclick="toggleDateSort()" style="cursor: pointer;">
                    Last Prescription Date
                </th>
            </tr>
        </thead>
        <tbody id="patientListBody">
            <?php foreach ($patients as $patient): 
                $last_date = $patient['last_prescription_date'];
                $formatted_date = $last_date ? date('M d, Y', strtotime($last_date)) : 'Never';
                $date_timestamp = $last_date ? strtotime($last_date) : 0;
            ?>
            <tr data-patient-id="<?php echo $patient['Patient_id']; ?>" 
                data-date-timestamp="<?php echo $date_timestamp; ?>">
                <td class="checkbox-cell">
                    <input type="checkbox" 
                           class="patient-checkbox patient-select" 
                           name="exclude_patients[]" 
                           value="<?php echo $patient['Patient_id']; ?>"
                           checked
                           onchange="updatePatientStatus(this)">
                </td>
                <td><?php echo strtoupper($patient['Patient_name']); ?></td>
                <td><?php echo strtoupper($patient['Barangay']); ?></td>
                <td class="last-prescription-date" data-sort-value="<?php echo $date_timestamp; ?>">
                    <?php echo $formatted_date; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
                <!-- Selection Summary -->
                <div class="selection-summary" id="selectionSummary">
                    Ready to create prescriptions for <?php echo $total_patients; ?> patients
                </div>

                <!-- Action Buttons -->
                <div class="modal-buttons">
                    <button type="button" onclick="closePrintModal()" class="btn btn-cancel">
                        Cancel
                    </button>
                    <button type="button" onclick="startBulkProcessing()" class="btn btn-submit" id="createBtn">
                        <span class="button-text">Create & Generate PDF</span>
                        <span class="spinner-small"></span>
                    </button>
                </div>

            </form> 

            <!-- Processing Overlay -->
            <div id="modalProcessingOverlay" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.95); border-radius:8px; z-index:100; flex-direction:column; justify-content:center; align-items:center;">
                <div class="spinner" style="width:60px; height:60px; border-width:6px; border-top-color:#28a745;"></div>
                <div class="loading-text" style="font-size:18px; color:#28a745; font-weight:bold; margin-top:15px;">Processing...</div>
                <div id="processingTimer" style="font-size:14px; color:#666; margin-top:10px;">Starting in 5 seconds...</div>
                <div id="processingProgress" style="width:80%; background:#f0f0f0; height:10px; border-radius:5px; margin-top:20px; overflow:hidden;">
                    <div id="progressBar" style="width:0%; height:100%; background:#28a745; transition:width 5s linear;"></div>
                </div>
                <div style="font-size:12px; color:#666; margin-top:10px; text-align:center;">
                    Processing <span id="processingCount"><?php echo $total_patients; ?></span> patients<br>
                    Please wait, do not close this window
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.querySelector('form[method="post"]');
            const searchButton = searchForm.querySelector('button[type="submit"]');

            if (searchForm && searchButton) {
                searchForm.addEventListener('submit', function(e) {
                    if (e.submitter === searchButton) {
                        document.getElementById('loadingOverlay').style.display = 'flex';
                        setTimeout(function() {
                            document.getElementById('loadingOverlay').style.display = 'none';
                        }, 3000);
                    }
                });
            }

            // Set today's date when page loads
            setDateDefaults();
            
            // Initialize checkbox states
            initializeCheckboxes();
        });

        function setDateDefaults() {
            const today = new Date();
            const todayFormatted = today.toLocaleDateString('en-CA');

            const prescriptionDateInput = document.getElementById('prescription_date');

            if (prescriptionDateInput) {
                prescriptionDateInput.value = todayFormatted;
                prescriptionDateInput.min = todayFormatted;

                prescriptionDateInput.addEventListener('change', function() {
                    validateDateInput(this);
                });

                prescriptionDateInput.addEventListener('input', function() {
                    validateDateInput(this);
                });
            }
        }

        function validateDateInput(dateInput) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const selectedDate = new Date(dateInput.value);
            selectedDate.setHours(0, 0, 0, 0);

            const dateError = document.getElementById('dateError');

            if (selectedDate < today) {
                dateError.style.display = 'block';
                dateInput.style.borderColor = '#dc3545';
                dateInput.style.backgroundColor = '#fff5f5';
                return false;
            } else {
                dateError.style.display = 'none';
                dateInput.style.borderColor = '#28a745';
                dateInput.style.backgroundColor = '#fff';
                return true;
            }
        }

        function initializeCheckboxes() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const patientCheckboxes = document.querySelectorAll('.patient-select');
            
            if (selectAllCheckbox && patientCheckboxes.length > 0) {
                // Set initial state
                updateSelectionSummary();
                
                // Add event listener to Select All
                selectAllCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    patientCheckboxes.forEach(cb => {
                        cb.checked = isChecked;
                        updatePatientStatus(cb);
                    });
                    updateSelectionSummary();
                });
                
                // Add event listeners to individual checkboxes
                patientCheckboxes.forEach(cb => {
                    cb.addEventListener('change', function() {
                        updatePatientStatus(this);
                        updateSelectAllState();
                        updateSelectionSummary();
                    });
                });
                
                // Update select all state on load
                updateSelectAllState();
            }
        }

        function updatePatientStatus(checkbox) {
            const row = checkbox.closest('tr');
            if (checkbox.checked) {
                row.classList.remove('excluded');
            } else {
                row.classList.add('excluded');
            }
        }

        function updateSelectAllState() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const patientCheckboxes = document.querySelectorAll('.patient-select');
            
            if (patientCheckboxes.length > 0) {
                const checkedCount = Array.from(patientCheckboxes).filter(cb => cb.checked).length;
                selectAllCheckbox.checked = checkedCount === patientCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < patientCheckboxes.length;
            }
        }

        function updateSelectionSummary() {
            const patientCheckboxes = document.querySelectorAll('.patient-select');
            const totalCount = patientCheckboxes.length;
            const includedCount = Array.from(patientCheckboxes).filter(cb => cb.checked).length;
            const excludedCount = totalCount - includedCount;
            
            // Update counters
            document.getElementById('includedCount').textContent = includedCount;
            document.getElementById('excludedCount').textContent = excludedCount;
            
            // Update summary text
            const summaryDiv = document.getElementById('selectionSummary');
            if (summaryDiv) {
                if (excludedCount === 0) {
                    summaryDiv.textContent = `Create prescriptions for ${includedCount} patients`;
                    summaryDiv.style.backgroundColor = '#d4edda';
                    summaryDiv.style.color = '#155724';
                    summaryDiv.style.borderColor = '#c3e6cb';
                } else if (includedCount === 0) {
                    summaryDiv.textContent = 'No patients selected. Please select at least one patient.';
                    summaryDiv.style.backgroundColor = '#f8d7da';
                    summaryDiv.style.color = '#721c24';
                    summaryDiv.style.borderColor = '#f5c6cb';
                } else {
                    summaryDiv.textContent = `Ready to create prescriptions for ${includedCount} patients (${excludedCount} excluded)`;
                    summaryDiv.style.backgroundColor = '#fff3cd';
                    summaryDiv.style.color = '#856404';
                    summaryDiv.style.borderColor = '#ffeaa7';
                }
            }
            
            // Update processing count
            document.getElementById('processingCount').textContent = includedCount;
            
            // Update submit button state
            const submitBtn = document.getElementById('createBtn');
            if (submitBtn) {
                submitBtn.disabled = includedCount === 0;
            }
            
            return includedCount;
        }

        function openPrintModal() {
            <?php if ($total_patients == 0): ?>
                alert('No patients found for the selected refill day.');
                return;
            <?php endif; ?>

            document.getElementById('printModal').style.display = 'flex';
            resetModalState();
            
            // Set date defaults when modal opens
            setDateDefaults();
            
            // Initialize checkboxes
            initializeCheckboxes();
            
            // Prevent body scrolling
            document.body.style.overflow = 'hidden';
        }

        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
            resetModalState();
            
            // Re-enable body scrolling
            document.body.style.overflow = 'auto';
        }

        function resetModalState() {
            const createBtn = document.getElementById('createBtn');
            const overlay = document.getElementById('modalProcessingOverlay');
            const progressBar = document.getElementById('progressBar');

            if (createBtn) {
                createBtn.disabled = false;
                createBtn.classList.remove('loading');
                createBtn.querySelector('.button-text').textContent = 'Create & Generate PDF';
            }

            if (overlay) overlay.style.display = 'none';
            if (progressBar) progressBar.style.width = '0%';
        }

        // Doctor autocomplete functionality
        const doctorInputModal = document.getElementById('doctorNameModal');
        const licenseInputModal = document.getElementById('doctorLicenseModal');
        const ptrInputModal = document.getElementById('doctorPtrModal');
        const doctorOptionsModal = document.querySelectorAll('#doctorsList option');

        if (doctorInputModal) {
            doctorInputModal.addEventListener('input', function() {
                let found = false;
                licenseInputModal.value = '';
                ptrInputModal.value = '';

                doctorOptionsModal.forEach(opt => {
                    if (opt.value === doctorInputModal.value) {
                        licenseInputModal.value = opt.dataset.license;
                        ptrInputModal.value = opt.dataset.ptr || '';
                        found = true;
                    }
                });

                if (!found && doctorInputModal.value !== '') {
                    licenseInputModal.value = '';
                    ptrInputModal.value = '';
                }
            });
        }

        function startBulkProcessing() {
            const doctorName = doctorInputModal ? doctorInputModal.value.trim() : '';
            const licenseNo = licenseInputModal ? licenseInputModal.value.trim() : '';
            const prescriptionDateInput = document.getElementById('prescription_date');

            // Validate date first
            if (prescriptionDateInput && !validateDateInput(prescriptionDateInput)) {
                prescriptionDateInput.focus();
                return false;
            }

            const prescriptionDate = prescriptionDateInput ? prescriptionDateInput.value : '';

            if (doctorName === '' || licenseNo === '') {
                alert('Please select a valid doctor from the list.');
                if (doctorInputModal) doctorInputModal.focus();
                return false;
            }

            // Get selected patients
            const patientCheckboxes = document.querySelectorAll('.patient-select');
            const selectedPatients = [];
            const excludedPatients = [];
            
            patientCheckboxes.forEach(cb => {
                if (cb.checked) {
                    selectedPatients.push(cb.value);
                } else {
                    excludedPatients.push(cb.value);
                }
            });
            
            if (selectedPatients.length === 0) {
                alert('Please select at least one patient to process.');
                return false;
            }
            
            // Store selected patients in hidden field
            document.getElementById('selectedPatientsInput').value = selectedPatients.join(',');

            const overlay = document.getElementById('modalProcessingOverlay');
            const createBtn = document.getElementById('createBtn');
            const progressBar = document.getElementById('progressBar');
            const timerDisplay = document.getElementById('processingTimer');

            if (overlay) overlay.style.display = 'flex';
            if (createBtn) {
                createBtn.disabled = true;
                createBtn.classList.add('loading');
                createBtn.querySelector('.button-text').textContent = 'Processing...';
            }

            let secondsLeft = 5;
            const countdownInterval = setInterval(() => {
                if (timerDisplay) {
                    timerDisplay.textContent = `Starting in ${secondsLeft} second${secondsLeft !== 1 ? 's' : ''}...`;
                }

                if (progressBar) {
                    const progressPercent = ((5 - secondsLeft) / 5) * 100;
                    progressBar.style.width = `${progressPercent}%`;
                }

                secondsLeft--;

                if (secondsLeft < 0) {
                    clearInterval(countdownInterval);

                    if (timerDisplay) timerDisplay.textContent = 'Submitting form...';
                    if (progressBar) progressBar.style.width = '100%';

                    setTimeout(() => {
                        document.getElementById('printForm').submit();
                    }, 500);
                }
            }, 1000);

            return true;
        }

        const printForm = document.getElementById('printForm');
        if (printForm) {
            printForm.addEventListener('submit', function(e) {
                if (!this.hasAttribute('data-processing')) {
                    const doctorName = doctorInputModal ? doctorInputModal.value.trim() : '';
                    const licenseNo = licenseInputModal ? licenseInputModal.value.trim() : '';
                    const prescriptionDateInput = document.getElementById('prescription_date');

                    // Validate date before submission
                    if (prescriptionDateInput && !validateDateInput(prescriptionDateInput)) {
                        e.preventDefault();
                        prescriptionDateInput.focus();
                        return false;
                    }

                    const prescriptionDate = prescriptionDateInput ? prescriptionDateInput.value : '';

                    if (doctorName === '' || licenseNo === '') {
                        e.preventDefault();
                        alert('Please select a valid doctor from the list.');
                        if (doctorInputModal) doctorInputModal.focus();
                        return false;
                    }

                    // Get selected patients count
                    const patientCheckboxes = document.querySelectorAll('.patient-select');
                    const selectedCount = Array.from(patientCheckboxes).filter(cb => cb.checked).length;
                    
                    if (selectedCount === 0) {
                        e.preventDefault();
                        alert('Please select at least one patient to process.');
                        return false;
                    }

                    const loadingOverlay = document.getElementById('loadingOverlay');
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'flex';
                        loadingOverlay.querySelector('.loading-text').textContent = 'Creating Records...';
                    }
                }

                return true;
            });
        }

        const printModal = document.getElementById('printModal');
        if (printModal) {
            printModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closePrintModal();
                }
            });
        }

        function generateBulkPDF(ids, refillDay) {
            const pdfLoader = document.getElementById('pdfLoader');
            pdfLoader.style.display = 'flex';

            const viewBtn = event.target.closest('a') || event.target;
            if (viewBtn.hasAttribute('data-processing')) {
                return;
            }
            viewBtn.setAttribute('data-processing', 'true');

            const pdfUrl = 'Pdfs/bulk_generate_pdf.php?bulk_ids=' + encodeURIComponent(ids) + '&dosearch=' + encodeURIComponent(refillDay);

            setTimeout(() => {
                pdfLoader.style.display = 'none';
                window.open(pdfUrl, '_blank');
                viewBtn.removeAttribute('data-processing');
            }, 1500);
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('printModal').style.display === 'flex') {
                closePrintModal();
            }
        });
    </script>
    
</body>
</html>