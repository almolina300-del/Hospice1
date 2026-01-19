<?php
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

// Check if user is SUADMIN
$is_suadmin = isset($_SESSION['Role']) && $_SESSION['Role'] === 'SUADMIN';

require('Config/Config.php');

$bg_patients = 'F2F2FF';

// Order handling with direction tracking
$ord_patients = '';
$dir = 'asc'; // default direction
if (isset($_GET['op'])) {
    $ord_patients = $_GET['op'];

    // Check if there's a direction parameter
    if (isset($_GET['dir'])) {
        $dir = ($_GET['dir'] == 'desc') ? 'desc' : 'asc';
    } else {
        // Default direction
        $dir = 'asc';
    }
};

if (is_numeric($ord_patients)) {
    $ord_patients = round(min(max($ord_patients, 1), 6));
} else {
    $ord_patients = 1;
}

// Define order clauses - UPDATED line 6 to use r.Reason
$order_patients = array(
    1 => array('asc' => 'p.Last_name ASC', 'desc' => 'p.Last_name DESC'),
    2 => array('asc' => 'p.First_name ASC', 'desc' => 'p.First_name DESC'),
    3 => array('asc' => 'p.Middle_name ASC', 'desc' => 'p.Middle_name DESC'),
    4 => array('asc' => 'p.Barangay ASC', 'desc' => 'p.Barangay DESC'),
    5 => array('asc' => 'r.Date ASC', 'desc' => 'r.Date DESC'),
    6 => array('asc' => 'r.Reason ASC', 'desc' => 'r.Reason DESC')  // Changed from r.Details to r.Reason
);

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());

// ---------- PAGINATION SETUP ----------
$search = isset($_POST['dosearch']) ? mysqli_real_escape_string($conn, $_POST['dosearch']) : '';

// Number of rows per page
$limit = 20;

// Get current page from URL, default = 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Calculate starting record
$start = ($page - 1) * $limit;

// Check for reason filter
$reason_filter = '';
if (isset($_GET['reason']) && !empty($_GET['reason']) && $_GET['reason'] != 'all') {
    $reason_filter = " AND r.Reason = '" . mysqli_real_escape_string($conn, $_GET['reason']) . "'";
}

$table_patients = "<table align='center'>
    <tr>
    <td align='center' 
      style='color:#b30000; background-color:#ffe6e6; 
             font-weight:bold; padding:10px; border-radius:6px;'>
    No Inactive Patients Found!
    </td>
        </tr>
        </table>";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Hospice - Inactive Patients</title>
    <link rel="stylesheet" type="text/css" href="CSS/style.css">

</head>

<body>
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
        <a href="inactive_patient.php" style="background-color: whitesmoke; padding: 8px 12px; border-radius: 0px; display: inline-block; margin: 4px 0; text-decoration: none; color: #263F73; font-weight: bold;">
            Inactive Patients
        </a>
        <a href="bulk_print.php">
            Bulk Print
        </a>
        <a href="Medicines.php">Medicines</a>

        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <a href="Doctors.php">Doctors</a>
            <a href="Medicines.php">Medicines</a>
            <a href="user_management.php">User Management</a>
        <?php endif; ?>

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
    <h1 align='center' style="background-color: #dc3545; color: white; padding: 12px 20px; margin-left: 210px; margin-top: 0px; font-size: 23px; border-radius: 5px 0 0 10px;">
        <img src="img/inactive_patient.png" alt="inactive_icon" class="logo" style="width: 30px; height: 30px; "> Inactive Patients
    </h1>

    <?php
    // Count total inactive patients
    $count_total_query = mysqli_query($conn, "SELECT COUNT(*) AS total_inactive FROM patient_details WHERE is_active = 0");
    $count_total_result = mysqli_fetch_assoc($count_total_query);

    // Count by reason - USING r.Reason column directly
    $reasons_query = mysqli_query($conn, "
        SELECT 
            COUNT(*) as total,
            COALESCE(r.Reason, 'UNKNOWN') as reason
        FROM patient_details p
        LEFT JOIN remarks_inactive r ON p.Patient_id = r.Patient_id
        WHERE p.is_active = 0
        GROUP BY r.Reason
    ");

    $reason_counts = [];
    while ($row = mysqli_fetch_assoc($reasons_query)) {
        $reason_counts[$row['reason']] = $row['total'];
    }

    if ($count_total_result) {
        echo "<div style='display:flex; align-items:center; gap:15px; margin-bottom:20px; justify-content:center; flex-wrap:wrap;'>";

        // Total Inactive Patients box
        echo "<div class='blink-text' style='background-color:white; 
                     padding:10px 20px; border-radius:8px; font-weight:bold; 
                     color:#263F73; width:180px; text-align:center;
                     box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
        echo "Total Inactive<br><span style='font-size:30px;'>" . $count_total_result['total_inactive'] . "</span>";
        echo "</div>";

        // Reason boxes
        $reason_colors = [
            'DECEASED' => '#dc3545',
            'PATIENT UNLOCATED' => '#fd7e14',
            'EXPIRED MHP CARD' => '#ffc107',
            'REFUSED DELIVERY' => '#6c757d',
            'OTHER' => '#6f42c1',
            'UNKNOWN' => '#adb5bd'
        ];

        foreach ($reason_counts as $reason => $count) {
            $color = $reason_colors[$reason] ?? '#adb5bd';
            echo "<div style='background-color:white; 
                         padding:10px 15px; border-radius:8px; font-weight:bold; 
                         color:$color; width:160px; text-align:center;
                         box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
            echo strtoupper($reason) . "<br><span style='font-size:25px;'>" . $count . "</span>";
            echo "</div>";
        }

        echo "</div>";
    }
    ?>

    <hr style="margin: 20px 250px; border: 1px solid #ccc; width: 80%;">


    <!-- INACTIVE PATIENTS SECTION -->
    <div style="margin-left: 180px; margin-bottom: 40px;">

        <!-- Search container -->
        <div style="background-color: white; padding: 15px 20px; margin: 0 250px 20px 250px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #ddd;">
            <form method="post" action="inactive_patient.php" name="patientsform" style="display: flex; align-items: center; gap: 10px;">
                <input type="text" name="dosearch" placeholder="Enter Lastname, Firstname, or Full Name"
                    value="<?php echo htmlspecialchars($_POST['dosearch'] ?? ''); ?>"
                    style="flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px;">

                <input type="submit" name="action" value="Search"
                    style="padding: 8px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
            </form>
        </div>

        <!-- Reason Filter Buttons -->
        <div style="text-align: center; margin: 0 250px 20px 250px; background-color: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #ddd;">
            <div style="display: inline-flex; align-items: center; gap: 10px;">
                <strong style="color: #263F73; white-space: nowrap;">Filter by Reason:</strong>
                <div style="display: flex; gap: 5px; flex-wrap: wrap; justify-content: center;">
                    <?php
                    $current_reason_filter = $_GET['reason'] ?? 'all';
                    ?>
                    <a href="inactive_patient.php?reason=all&op=<?php echo $ord_patients; ?>&dir=<?php echo $dir; ?>&page=<?php echo $page; ?><?php echo !empty($search) ? '&dosearch=' . urlencode($search) : ''; ?>"
                        style="padding: 6px 15px; 
                              background-color: <?php echo $current_reason_filter == 'all' ? '#263F73' : '#6c757d'; ?>; 
                              color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"
                        onmouseover="if(this.style.backgroundColor!='#263F73')this.style.backgroundColor='#5a6268'"
                        onmouseout="if(this.style.backgroundColor!='#263F73')this.style.backgroundColor='#6c757d'">
                        All Reasons
                    </a>
                    <a href="inactive_patient.php?reason=DECEASED&op=<?php echo $ord_patients; ?>&dir=<?php echo $dir; ?>&page=<?php echo $page; ?><?php echo !empty($search) ? '&dosearch=' . urlencode($search) : ''; ?>"
                        style="padding: 6px 15px; 
                              background-color: <?php echo $current_reason_filter == 'DECEASED' ? '#dc3545' : '#6c757d'; ?>; 
                              color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"
                        onmouseover="if(this.style.backgroundColor!='#dc3545')this.style.backgroundColor='#5a6268'"
                        onmouseout="if(this.style.backgroundColor!='#dc3545')this.style.backgroundColor='#6c757d'">
                        DECEASED
                    </a>
                    <a href="inactive_patient.php?reason=PATIENT UNLOCATED&op=<?php echo $ord_patients; ?>&dir=<?php echo $dir; ?>&page=<?php echo $page; ?><?php echo !empty($search) ? '&dosearch=' . urlencode($search) : ''; ?>"
                        style="padding: 6px 15px; 
                              background-color: <?php echo $current_reason_filter == 'PATIENT UNLOCATED' ? '#fd7e14' : '#6c757d'; ?>; 
                              color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"
                        onmouseover="if(this.style.backgroundColor!='#fd7e14')this.style.backgroundColor='#5a6268'"
                        onmouseout="if(this.style.backgroundColor!='#fd7e14')this.style.backgroundColor='#6c757d'">
                        PATIENT UNLOCATED
                    </a>
                    <a href="inactive_patient.php?reason=EXPIRED MHP CARD&op=<?php echo $ord_patients; ?>&dir=<?php echo $dir; ?>&page=<?php echo $page; ?><?php echo !empty($search) ? '&dosearch=' . urlencode($search) : ''; ?>"
                        style="padding: 6px 15px; 
                              background-color: <?php echo $current_reason_filter == 'EXPIRED MHP CARD' ? '#ffc107' : '#6c757d'; ?>; 
                              color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"
                        onmouseover="if(this.style.backgroundColor!='#ffc107')this.style.backgroundColor='#5a6268'"
                        onmouseout="if(this.style.backgroundColor!='#ffc107')this.style.backgroundColor='#6c757d'">
                        EXPIRED MHP CARD
                    </a>
                    <a href="inactive_patient.php?reason=REFUSED DELIVERY&op=<?php echo $ord_patients; ?>&dir=<?php echo $dir; ?>&page=<?php echo $page; ?><?php echo !empty($search) ? '&dosearch=' . urlencode($search) : ''; ?>"
                        style="padding: 6px 15px; 
                              background-color: <?php echo $current_reason_filter == 'REFUSED DELIVERY' ? '#6c757d' : '#adb5bd'; ?>; 
                              color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"
                        onmouseover="if(this.style.backgroundColor!='#6c757d')this.style.backgroundColor='#5a6268'"
                        onmouseout="if(this.style.backgroundColor!='#6c757d')this.style.backgroundColor='#adb5bd'">
                        REFUSED DELIVERY
                    </a>
                </div>
            </div>
        </div>

        <?php
        // SEARCH FUNCTIONALITY FOR INACTIVE PATIENTS
        if (!empty($search)) {
            $runsearch = mysqli_real_escape_string($conn, $search);
            $sql_patients = "SELECT SQL_CALC_FOUND_ROWS 
                            p.Patient_id, p.Last_name, p.First_name, p.Middle_name, 
                            p.Barangay, p.Birthday, p.House_nos_street_name,
                            r.Date as deactivation_date, r.Reason, r.Details, r.is_set_by
                         FROM patient_details p
                         LEFT JOIN remarks_inactive r ON p.Patient_id = r.Patient_id
                         WHERE p.is_active = 0 
                         AND (p.Last_name LIKE '%$runsearch%' 
                              OR p.First_name LIKE '%$runsearch%'
                              OR CONCAT(p.Last_name, ' ', p.First_name) LIKE '%$runsearch%')
                         $reason_filter
                         ORDER BY " . $order_patients[$ord_patients][$dir];
        } else {
            $sql_patients = "SELECT SQL_CALC_FOUND_ROWS 
                            p.Patient_id, p.Last_name, p.First_name, p.Middle_name, 
                            p.Barangay, p.Birthday, p.House_nos_street_name,
                            r.Date as deactivation_date, r.Reason, r.Details, r.is_set_by
                         FROM patient_details p
                         LEFT JOIN remarks_inactive r ON p.Patient_id = r.Patient_id
                         WHERE p.is_active = 0 
                         $reason_filter
                         ORDER BY " . $order_patients[$ord_patients][$dir];
        }

        // Apply limit for pagination
        $sql_patients .= " LIMIT $start, $limit";

        $result_patients = mysqli_query($conn, $sql_patients) or die(mysqli_error($conn));

        // Get total rows for pagination
        $totalResult = mysqli_query($conn, "SELECT FOUND_ROWS() AS total");
        $totalRows = mysqli_fetch_assoc($totalResult)['total'];
        $totalPages = ceil($totalRows / $limit);

      if (mysqli_num_rows($result_patients) > 0) {
    echo "<div style='margin-left: 40px; margin-right: 10px;'>"; // Reduced from 180px
    echo "<div class='table-container' style='margin: 0;'>"; // Remove any margins
    echo "<table class='patients-table' style='width: 100%; table-layout: fixed; margin-left: 0;'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th style='width: 10px; padding: 10px 5px; text-align: center;'>No.</th>";
    echo "<th style='width: 120px; padding: 10px 5px;'>
        <a href='" . $_SERVER['PHP_SELF'] . "?op=1" . ($ord_patients == 1 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "&reason=" . urlencode($_GET['reason'] ?? '') . "' 
           style='color: white; text-decoration: none; display: block;'>
           Last Name
           " . ($ord_patients == 1 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
    </th>";
    echo "<th style='width: 120px; padding: 10px 5px;'>
        <a href='" . $_SERVER['PHP_SELF'] . "?op=2" . ($ord_patients == 2 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "&reason=" . urlencode($_GET['reason'] ?? '') . "'
           style='color: white; text-decoration: none; display: block;'>
           First Name
           " . ($ord_patients == 2 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
    </th>";
    echo "<th style='width: 100px; padding: 10px 5px;'>
        <a href='" . $_SERVER['PHP_SELF'] . "?op=3" . ($ord_patients == 3 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "&reason=" . urlencode($_GET['reason'] ?? '') . "' 
           style='color: white; text-decoration: none; display: block;'>
           Middle Name
           " . ($ord_patients == 3 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
    </th>";
    echo "<th style='width: 120px; padding: 10px 5px;'>
        <a href='" . $_SERVER['PHP_SELF'] . "?op=4" . ($ord_patients == 4 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "&reason=" . urlencode($_GET['reason'] ?? '') . "' 
           style='color: white; text-decoration: none; display: block;'>
           Barangay
           " . ($ord_patients == 4 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
    </th>";
    echo "<th style='width: 100px; padding: 10px 5px; text-align: center;'>Birthday</th>";
    echo "<th style='width: 120px; padding: 10px 5px;'>
        <a href='" . $_SERVER['PHP_SELF'] . "?op=5" . ($ord_patients == 5 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "&reason=" . urlencode($_GET['reason'] ?? '') . "' 
           style='color: white; text-decoration: none; display: block;'>
           Deactivation Date
           " . ($ord_patients == 5 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
    </th>";
    echo "<th style='width: 150px; padding: 10px 5px;'>
        <a href='" . $_SERVER['PHP_SELF'] . "?op=6" . ($ord_patients == 6 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "&reason=" . urlencode($_GET['reason'] ?? '') . "' 
           style='color: white; text-decoration: none; display: block;'>
           Reason
           " . ($ord_patients == 6 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
    </th>";
    echo "<th style='width: 200px; padding: 10px 5px;'>Details</th>";
    echo "<th style='width: 120px; padding: 10px 5px;'>Deactivated By</th>";
    echo "<th style='width: 80px; padding: 10px 5px; text-align: center;'>Status</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    $row_count = $start + 1;
    while ($row = mysqli_fetch_assoc($result_patients)) {
        $bg_color = ($row_count % 2 == 0) ? '#F2F2FF' : '#FFFFFF';

        // Get reason from Reason column directly - SEPARATED FROM DETAILS
        $reason = $row['Reason'] ?? 'UNKNOWN';
        $details = $row['Details'] ?? '';
        
        // Apply CSS class based on reason
        switch ($reason) {
            case 'DECEASED':
                $reason_class = 'reason-deceased';
                break;
            case 'PATIENT UNLOCATED':
                $reason_class = 'reason-unlocated';
                break;
            case 'EXPIRED MHP CARD':
                $reason_class = 'reason-expired';
                break;
            case 'REFUSED DELIVERY':
                $reason_class = 'reason-refused';
                break;
            default:
                $reason_class = '';
                break;
        }

        echo "<tr style='background-color: " . $bg_color . ";'>";
        echo "<td style='padding: 8px 5px; text-align: center;'>" . $row_count . "</td>";
        echo "<td style='padding: 8px 5px;'>
                <div style='display: flex; align-items: center; justify-content: center; gap: 5px;'>
                    " . htmlspecialchars(strtoupper($row['Last_name'] ?? '')) . "
                </div>
              </td>";
        echo "<td style='padding: 8px 5px;'>" . htmlspecialchars(strtoupper($row['First_name'] ?? '')) . "</td>";
        echo "<td style='padding: 8px 5px;'>" . htmlspecialchars(strtoupper($row['Middle_name'] ?? '')) . "</td>";
        echo "<td style='padding: 8px 5px;'>" . htmlspecialchars(strtoupper($row['Barangay'] ?? '')) . "</td>";
        echo "<td style='padding: 8px 5px; text-align: center;'>" . htmlspecialchars($row['Birthday'] ?? '') . "</td>";
        echo "<td style='padding: 8px 5px; text-align: center;'>" . htmlspecialchars($row['deactivation_date'] ?? '') . "</td>";
        echo "<td style='padding: 8px 5px;'><span class='" . $reason_class . "'>" . $reason . "</span></td>";
        echo "<td style='padding: 8px 5px; word-wrap: break-word; font-size: 12px;'>" .
            nl2br(htmlspecialchars(substr($details, 0, 200))) .
            (strlen($details) > 200 ? '...' : '') . "</td>";
        echo "<td style='padding: 8px 5px; text-align: center;'>" . htmlspecialchars($row['is_set_by'] ?? 'Unknown') . "</td>";
        echo "<td style='padding: 8px 5px; text-align: center;'>";
        // Reactivate button - AVAILABLE TO ALL USERS
        echo "<a href='transact/reactivate_patient.php?c=" . urlencode($row['Patient_id']) . "' 
      class='btn-danger'
      style='padding:6px 8px; border-radius:3px; text-decoration:none; font-size:11px; 
             background-color:#dc3545; color:white; display:inline-block;' 
      onclick=\"return confirm('Are you sure you want to reactivate this patient?');\">
      Inactive</a>";
        echo "</td>";
        echo "</tr>";

        $row_count++;
    }

    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    echo "</div>";

            $row_count = $start + 1;
            while ($row = mysqli_fetch_assoc($result_patients)) {
                $bg_color = ($row_count % 2 == 0) ? '#F2F2FF' : '#FFFFFF';

                // Get reason from Reason column directly - SEPARATED FROM DETAILS
                $reason = $row['Reason'] ?? 'UNKNOWN';
                $details = $row['Details'] ?? '';
                
                // Apply CSS class based on reason
                switch ($reason) {
                    case 'DECEASED':
                        $reason_class = 'reason-deceased';
                        break;
                    case 'PATIENT UNLOCATED':
                        $reason_class = 'reason-unlocated';
                        break;
                    case 'EXPIRED MHP CARD':
                        $reason_class = 'reason-expired';
                        break;
                    case 'REFUSED DELIVERY':
                        $reason_class = 'reason-refused';
                        break;
                    default:
                        $reason_class = '';
                        break;
                }

                echo "<tr style='background-color: " . $bg_color . ";'>";
                echo "<td>" . $row_count . "</td>";
                echo "<td>
                        <div style='display: flex; align-items: center; justify-content: center; gap: 5px;'>
                            " . htmlspecialchars(strtoupper($row['Last_name'] ?? '')) . "
                        </div>
                      </td>";
                echo "<td>" . htmlspecialchars(strtoupper($row['First_name'] ?? '')) . "</td>";
                echo "<td>" . htmlspecialchars(strtoupper($row['Middle_name'] ?? '')) . "</td>";
                echo "<td>" . htmlspecialchars(strtoupper($row['Barangay'] ?? '')) . "</td>";
                echo "<td>" . htmlspecialchars($row['Birthday'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['deactivation_date'] ?? '') . "</td>";
                echo "<td><span class='" . $reason_class . "'>" . $reason . "</span></td>";
                echo "<td style='max-width: 200px; word-wrap: break-word; font-size: 12px;'>" .
                    nl2br(htmlspecialchars(substr($details, 0, 100))) .
                    (strlen($details) > 100 ? '...' : '') . "</td>";
                echo "<td>" . htmlspecialchars($row['is_set_by'] ?? 'Unknown') . "</td>";
                echo "<td>";
                // Reactivate button - AVAILABLE TO ALL USERS
                echo "<a href='transact/reactivate_patient.php?c=" . urlencode($row['Patient_id']) . "' 
      class='btn-danger'
      style='padding:6px 8px; border-radius:3px; text-decoration:none; font-size:11px; 
             background-color:#dc3545; color:white; display:inline-block;' 
      onclick=\"return confirm('Are you sure you want to reactivate this patient?');\">
      Inactive</a>";
                echo "</td>";
                echo "</tr>";

                $row_count++;
            }

            echo "</tbody>";
            echo "</table>";
            echo "</div>";
            echo "</div>";

            // PAGINATION DISPLAY
            echo "<div class='page-info'>Page $page / $totalPages</div>";

            // PAGINATION LINKS - Only show if more than 1 page
            if ($totalPages > 1) {
                echo "<div class='pagination'>";
                if ($page > 1) {
                    $prev_link = "?page=" . ($page - 1) . "&op=" . $ord_patients . "&dir=" . $dir . "&reason=" . urlencode($_GET['reason'] ?? '');
                    if (!empty($search)) $prev_link .= "&dosearch=" . urlencode($search);

                    echo "<a href='$prev_link' class='pagination-btn prev'>Previous</a>";
                }

                if ($page < $totalPages) {
                    $next_link = "?page=" . ($page + 1) . "&op=" . $ord_patients . "&dir=" . $dir . "&reason=" . urlencode($_GET['reason'] ?? '');
                    if (!empty($search)) $next_link .= "&dosearch=" . urlencode($search);

                    echo "<a href='$next_link' class='pagination-btn next'>Next</a>";
                }
                echo "</div>";
            }
        } else {
            echo "<div class='center-container'>";
            echo $table_patients;
            echo "</div>";
        }

        mysqli_close($conn);
        ?>
    </div>
</body>

</html>