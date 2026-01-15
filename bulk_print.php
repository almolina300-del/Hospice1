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

// Pagination settings
$records_per_page = 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Build SQL query - Get LATEST prescription per patient
$sql = "SELECT p.Prescription_id, 
    CONCAT(pat.Last_name, ', ', pat.First_name, ' ', COALESCE(pat.Middle_name, '')) AS Patient_name,
    pat.Barangay,
    p.Refill_day,
    CONCAT(d.Last_name, ', ', d.First_name, ' ', COALESCE(d.Middle_name, '')) AS Doctor_name,
    d.Ptr_number
FROM prescription p
LEFT JOIN patient_details pat ON p.Patient_id = pat.Patient_id
LEFT JOIN doctors d ON p.License_number = d.License_number
WHERE p.Prescription_id IN (
    SELECT MAX(p2.Prescription_id) 
    FROM prescription p2 
    WHERE p2.Patient_id = p.Patient_id 
    GROUP BY p2.Patient_id
)";

$where = [];
$where[] = "pat.is_active = 1";

if ($Refill_day != '') {
    $where[] = "p.Refill_day = '$Refill_day'";
}

if (count($where) > 0) {
    $sql .= " AND " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.Refill_day ASC";

// Get total rows for pagination
$total_result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$total_rows = mysqli_num_rows($total_result);

// Apply limit for current page
$sql .= " LIMIT $offset, $records_per_page";
$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));

// Get total rows without LIMIT for modal display
$sql_no_limit = "SELECT p.Prescription_id
    FROM prescription p
    LEFT JOIN patient_details pat ON p.Patient_id = pat.Patient_id
    LEFT JOIN doctors d ON p.License_number = d.License_number
    WHERE p.Prescription_id IN (
        SELECT MAX(p2.Prescription_id) 
        FROM prescription p2 
        WHERE p2.Patient_id = p.Patient_id 
        GROUP BY p2.Patient_id
    )";

if ($Refill_day != '') {
    $sql_no_limit .= " AND p.Refill_day = '$Refill_day'";
}

$sql_no_limit .= " AND pat.is_active = 1";

$all_rows_result = mysqli_query($conn, $sql_no_limit);
$real_total_rows = mysqli_num_rows($all_rows_result);

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
            background: rgba(0, 0, 0, 0.4);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 8px;
            width: 500px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            margin-top: 0;
            color: #263F73;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
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

        .summary-box {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            position: relative;
        }

        .btn-cancel {
            background: #ccc;
            color: #333;
        }

        .btn-submit {
            background: #3CB371;
            color: white;
        }

        .btn-submit.loading {
            pointer-events: none;
        }

        .btn-submit .button-text {
            visibility: visible;
        }

        .btn-submit.loading .button-text {
            visibility: hidden;
        }

        .btn-submit .spinner-small {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none;
        }

        .btn-submit.loading .spinner-small {
            display: block;
        }

        .btn-print {
            background: #263F73;
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

        /* Modal Processing Overlay */
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

        #processingProgress {
            width: 80%;
            background: #f0f0f0;
            height: 10px;
            border-radius: 5px;
            margin-top: 20px;
            overflow: hidden;
        }

        #progressBar {
            width: 0%;
            height: 100%;
            background: #3CB371;
            transition: width 5s linear;
        }

        #processingTimer {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }

        .modal-content.processing {
            pointer-events: none;
        }

        .modal-content.processing *:not(#modalProcessingOverlay *) {
            opacity: 0.5;
        }

        .logo {
            width: 30px;
            height: 30px;
            vertical-align: middle;
            margin-right: 6px;
        }

        /* Date input styling */
        input[type="date"]:invalid {
            border-color: #ff6b6b;
            background-color: #fff5f5;
        }

        input[type="date"]:valid {
            border-color: #28a745;
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

        /* Timezone indicator */
        .timezone-indicator {
            font-size: 11px;
            color: #666;
            text-align: right;
            margin-top: -10px;
            margin-bottom: 10px;
            font-style: italic;
        }

        /* Doctor selection container */
        .doctor-selection-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
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
                    l-0.53,14.7c-0.07,2.02-1.76,3.6-3.78,3.52c-2.02-0.07-3.6-1.76-3.52-3.78l0.85-23.42c0.07-2.02,1.76-3.6,3.78-3.52 
                    c0.13,0,0.25,0.02,0.37,0.03l0,0l22.7,3.19c2,0.28,3.4,2.12,3.12,4.13c-0.28,2-2.12,3.4-4.13,3.12L106.2,22.2L106.2,22.2z" />
                </g>
            </svg>
            Reset
        </a>

        <?php if ($Refill_day != '' && $real_total_rows > 0): ?>
            <a href="javascript:void(0);" onclick="openPrintModal()" class="btn-print">
                <svg xmlns="http://www.w3.org/2000/svg" width="25" height="21" fill="white" viewBox="0 0 16 15">
                    <path d="M2 7a2 2 0 0 0-2 2v3h4v3h8v-3h4V9a2 2 0 0 0-2-2H2zm11 5H3v-3h10v3zM2 4V0h12v4H2z" />
                </svg>
                Print
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

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $bg = ($bg == 'F2F2FF') ? 'E2E2F2' : 'F2F2FF';

                    echo "<tr bgcolor='#$bg'>
                            <td align='center'>" . strtoupper($row['Patient_name'] ?? '') . "</td>
                            <td align='center'>" . strtoupper($row['Barangay'] ?? '') . "</td>
                        </tr>";
                }
            } else {
                echo "<tr>
                        <td colspan='2' align='center' style='padding:10px;'>No prescriptions found.</td>
                    </tr>";
            }
        } else {
            echo "<tr>
                    <td colspan='2' align='center' style='padding:10px; color:red;'>Database query error.</td>
                </tr>";
        }

        echo "</table>";
        ?>
    </div>

    <div class="pagination" style="text-align:center; margin-top:15px;">
        <span style="font-style:italic; color:#263F73; margin-right:10px;">Page:</span>
        <?php
        $total_pages = ceil($total_rows / $records_per_page);
        for ($i = 1; $i <= $total_pages; $i++) {
            $active = ($i == $page) ? "font-weight:bold;" : "";
            $link = "bulk_print.php?page=$i";
            if ($Refill_day != '') $link .= "&dosearch=$Refill_day";
            echo "<a href='$link' style='padding:5px; color:#263F73; font-style:italic; $active'>$i</a>";
        }
        ?>
    </div>

    <!-- Print Confirmation Modal -->
    <div id="printModal">
        <div class="modal-content">
            <h3 class="modal-header">Create Prescriptions for Bulk Print</h3>

            <form id="printForm" method="post" action="bulk_print_process.php">
                <input type="hidden" name="dosearch" value="<?php echo htmlspecialchars($Refill_day); ?>">

                <div class="form-group">
                    <label class="form-label">
                        Prescription Date: <span class="required">*</span>
                    </label>
                    <input type="date" name="prescription_date" id="prescription_date"
                        required class="form-control">
                    <div class="date-error" id="dateError">You cannot select past dates. Please select today or a future date.</div>
                    <div class="date-note">Note: You can only select today's date or future dates</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Doctor Information: <span class="required">*</span>
                    </label>

                    <div class="doctor-selection-container">
                        <div class="doctor-field">
                            <label>Doctor Name</label>
                            <input list="doctorsList"
                                id="doctorNameModal"
                                name="DoctorName"
                                required
                                placeholder="Type doctor name"
                                oninput="this.value = this.value.toUpperCase()">
                        </div>

                        <div class="doctor-field">
                            <label>License Number</label>
                            <input type="text"
                                id="doctorLicenseModal"
                                name="License_number"
                                readonly
                                placeholder="Auto-filled">
                        </div>

                        <div class="doctor-field">
                            <label>PTR Number</label>
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
                            mysqli_data_seek($docResult, 0); // Reset pointer
                            while ($doc = mysqli_fetch_assoc($docResult)) {
                                $DoctorName = trim($doc['Last_name'] . ', ' . $doc['First_name'] . ' ' . $doc['Middle_name']);
                                $LicenseNo  = htmlspecialchars($doc['License_number']);
                                $PtrNumber  = htmlspecialchars($doc['Ptr_number'] ?? '');
                                echo "<option value=\"{$DoctorName}\" data-license=\"{$LicenseNo}\" data-ptr=\"{$PtrNumber}\"></option>";
                            }
                        }
                        ?>
                    </datalist>
                </div>

                <div class="summary-box">
                    <p style="margin:5px 0;"><strong>Summary:</strong></p>
                    <p style="margin:5px 0;">Refill Day: <strong><?php echo htmlspecialchars($Refill_day); ?></strong></p>
                    <p style="margin:5px 0;">Total Patients: <strong><?php echo $real_total_rows; ?></strong> (Latest prescription only)</p>
                    <p style="margin:5px 0; font-style:italic; color:#666;">This will create new prescriptions and generate a PDF for all patients.</p>
                </div>

                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="closePrintModal()" class="btn btn-cancel">Cancel</button>
                    <button type="button" onclick="startBulkProcessing()" class="btn btn-submit" id="createBtn">
                        <span class="button-text">Create Records & Generate PDF</span>
                        <span class="spinner-small"></span>
                    </button>
                </div>

                <button type="submit" id="hiddenSubmitBtn" style="display:none;"></button>
            </form>

            <div id="modalProcessingOverlay" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.95); border-radius:8px; z-index:100; flex-direction:column; justify-content:center; align-items:center;">
                <div class="spinner" style="width:60px; height:60px; border-width:6px; border-top-color:#3CB371;"></div>
                <div class="loading-text" style="font-size:18px; color:#3CB371; font-weight:bold; margin-top:15px;">Processing...</div>
                <div id="processingTimer" style="font-size:14px; color:#666; margin-top:10px;">Starting in 5 seconds...</div>
                <div id="processingProgress" style="width:80%; background:#f0f0f0; height:10px; border-radius:5px; margin-top:20px; overflow:hidden;">
                    <div id="progressBar" style="width:0%; height:100%; background:#3CB371; transition:width 5s linear;"></div>
                </div>
                <div style="font-size:12px; color:#666; margin-top:10px; text-align:center;">
                    Processing <?php echo $real_total_rows; ?> patients<br>
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
        });

        function setDateDefaults() {
            // Simple approach - just use the local date
            // Since we're in Philippines and server is set to Asia/Manila
            const today = new Date();
            const todayFormatted = today.toLocaleDateString('en-CA'); // YYYY-MM-DD format

            const prescriptionDateInput = document.getElementById('prescription_date');

            if (prescriptionDateInput) {
                // Set default value and min attribute
                prescriptionDateInput.value = todayFormatted;
                prescriptionDateInput.min = todayFormatted;

                // Add event listener for date validation
                prescriptionDateInput.addEventListener('change', function() {
                    validateDateInput(this);
                });

                // Also validate on input
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

        function openPrintModal() {
            <?php if ($real_total_rows == 0): ?>
                alert('No patients found for the selected refill day.');
                return;
            <?php endif; ?>

            document.getElementById('printModal').style.display = 'flex';
            resetModalState();

            // Set date defaults when modal opens
            setDateDefaults();
        }

        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
            resetModalState();
        }

        function resetModalState() {
            const createBtn = document.getElementById('createBtn');
            const overlay = document.getElementById('modalProcessingOverlay');
            const progressBar = document.getElementById('progressBar');

            if (createBtn) {
                createBtn.disabled = false;
                createBtn.classList.remove('loading');
                createBtn.querySelector('.button-text').textContent = 'Create Records & Generate PDF';
            }

            if (overlay) overlay.style.display = 'none';
            if (progressBar) progressBar.style.width = '0%';
        }

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
            const ptrNo = ptrInputModal ? ptrInputModal.value.trim() : '';
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
                    const ptrNo = ptrInputModal ? ptrInputModal.value.trim() : '';
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
    </script>
</body>

</html>