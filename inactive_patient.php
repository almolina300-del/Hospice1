<?php
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

require('Config/Config.php');

$bg_patients = 'F2F2FF';

// Order handling with direction tracking
$ord_patients = '';
$dir = 'asc'; // default direction
if (isset($_GET['op'])) {
    $ord_patients = $_GET['op'];
    if (isset($_GET['dir'])) {
        $dir = ($_GET['dir'] == 'desc') ? 'desc' : 'asc';
    } else {
        $dir = 'asc';
    }
};

if (is_numeric($ord_patients)) {
    $ord_patients = round(min(max($ord_patients, 1), 6));
} else {
    $ord_patients = 1;
}

// Define order clauses
$order_patients = array(
    1 => array('asc' => 'p.Last_name ASC', 'desc' => 'p.Last_name DESC'),
    2 => array('asc' => 'p.First_name ASC', 'desc' => 'p.First_name DESC'),
    3 => array('asc' => 'p.Middle_name ASC', 'desc' => 'p.Middle_name DESC'),
    4 => array('asc' => 'p.Barangay ASC', 'desc' => 'p.Barangay DESC'),
    5 => array('asc' => 'r.Date ASC', 'desc' => 'r.Date DESC'),
    6 => array('asc' => 'r.Reason ASC', 'desc' => 'r.Reason DESC')
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

// Check for reason filter - FIXED VERSION
$reason_filter = '';
$current_reason = isset($_GET['reason']) ? trim($_GET['reason']) : 'all';

if ($current_reason !== '' && strtoupper($current_reason) !== 'ALL') {
    // Use LOWER() for case-insensitive comparison
    $reason_filter = " AND LOWER(r.Reason) = LOWER('" . mysqli_real_escape_string($conn, $current_reason) . "')";
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
    <style>
        .filter-btn {
            padding: 6px 15px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .filter-btn:hover {
            opacity: 0.9;
        }
        .filter-btn.active {
            font-weight: bold;
            box-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
    </style>
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
        <img src="img/inactive_patient.png" alt="inactive_icon" class="logo" style="width: 30px; height: 30px;"> Inactive Patients
    </h1>

    <?php
    // Count total inactive patients
    $count_total_query = mysqli_query($conn, "SELECT COUNT(*) AS total_inactive FROM patient_details WHERE is_active = 0");
    $count_total_result = mysqli_fetch_assoc($count_total_query);

    // Count by reason
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
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="submit" name="action" value="Search"
                    style="padding: 8px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
            </form>
        </div>

        <!-- Reason Filter Buttons - FIXED VERSION -->
        <div style="text-align: center; margin: 0 250px 20px 250px; background-color: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #ddd;">
            <div style="display: inline-flex; align-items: center; gap: 10px;">
                <strong style="color: #263F73; white-space: nowrap;">Filter by Reason:</strong>
                <div style="display: flex; gap: 5px; flex-wrap: wrap; justify-content: center;">
                    <?php
                    // Create filter URLs
                    $base_url = "inactive_patient.php?op=" . $ord_patients . "&dir=" . $dir . "&page=1";
                    if (!empty($search)) {
                        $base_url .= "&dosearch=" . urlencode($search);
                    }
                    
                    // Define all filter options
                    $filter_options = [
                        'all' => ['label' => 'All Reasons', 'color' => '#263F73'],
                        'DECEASED' => ['label' => 'DECEASED', 'color' => '#dc3545'],
                        'PATIENT UNLOCATED' => ['label' => 'PATIENT UNLOCATED', 'color' => '#fd7e14'],
                        'EXPIRED MHP CARD' => ['label' => 'EXPIRED MHP CARD', 'color' => '#ffc107'],
                        'REFUSED DELIVERY' => ['label' => 'REFUSED DELIVERY', 'color' => '#6c757d'],
                    ];
                    
                    // Generate filter buttons
                    foreach ($filter_options as $reason_value => $option) {
                        $is_active = ($current_reason === $reason_value || 
                                     (strtoupper($current_reason) === strtoupper($reason_value)));
                        
                        $btn_url = $base_url . "&reason=" . urlencode($reason_value);
                        $btn_color = $is_active ? $option['color'] : '#6c757d';
                        $btn_class = $is_active ? 'active' : '';
                        
                        echo "<a href='{$btn_url}' 
                              class='filter-btn {$btn_class}'
                              style='background-color: {$btn_color};'
                              onmouseover=\"if(!this.classList.contains('active'))this.style.backgroundColor='#5a6268'\"
                              onmouseout=\"if(!this.classList.contains('active'))this.style.backgroundColor='#6c757d'\">
                              {$option['label']}
                          </a>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <?php
        // Build SQL query
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

        $result_patients = mysqli_query($conn, $sql_patients) or die('Query failed: ' . mysqli_error($conn));

        // Get total rows for pagination
        $totalResult = mysqli_query($conn, "SELECT FOUND_ROWS() AS total");
        $totalRows = mysqli_fetch_assoc($totalResult)['total'];
        $totalPages = ceil($totalRows / $limit);

        if (mysqli_num_rows($result_patients) > 0) {
            // Show filter info if active
            if ($current_reason !== 'all') {
                echo "<div style='text-align: center; margin: 10px 0; padding: 8px; background-color: #e7f3ff; border-radius: 4px;'>
                        <strong>Filter Active:</strong> Showing patients with reason: <span style='color: #dc3545; font-weight: bold;'>" . htmlspecialchars($current_reason) . "</span>
                        <a href='inactive_patient.php?op=" . $ord_patients . "&dir=" . $dir . "&page=1" . (!empty($search) ? "&dosearch=" . urlencode($search) : "") . "' 
                           style='margin-left: 10px; color: #007bff; text-decoration: none;'>[Clear Filter]</a>
                      </div>";
            }
            
            echo "<div style='margin-left: 40px; margin-right: 10px;'>";
            echo "<div class='table-container' style='margin: 0;'>";
            echo "<table class='patients-table' style='width: 100%; table-layout: fixed; margin-left: 0; border-collapse: collapse;'>";
            
            // Table headers with sorting
            echo "<thead>";
            echo "<tr style='background-color: #263F73; color: white;'>";
            echo "<th style='width: 10px; padding: 10px 5px; text-align: center;'>No.</th>";
            
            // Sorting headers
            $sort_headers = [
                1 => 'Last Name',
                2 => 'First Name',
                3 => 'Middle Name',
                4 => 'Barangay',
                6 => 'Reason',
                5 => 'Deactivation Date'
            ];
            
            foreach ($sort_headers as $col_num => $col_name) {
                $new_dir = ($ord_patients == $col_num && $dir == 'asc') ? 'desc' : 'asc';
                $sort_url = "inactive_patient.php?op=" . $col_num . "&dir=" . $new_dir . "&page=" . $page;
                if (!empty($search)) $sort_url .= "&dosearch=" . urlencode($search);
                if ($current_reason !== 'all') $sort_url .= "&reason=" . urlencode($current_reason);
                
                $sort_indicator = '';
                if ($ord_patients == $col_num) {
                    $sort_indicator = $dir == 'asc' ? ' ↑' : ' ↓';
                }
                
                echo "<th style='width: " . ($col_num == 6 ? '150' : '120') . "px; padding: 10px 5px;'>
                    <a href='" . $sort_url . "' style='color: white; text-decoration: none; display: block;'>
                    " . $col_name . $sort_indicator . "
                    </a>
                </th>";
            }
            
            echo "<th style='width: 200px; padding: 10px 5px;'>Details</th>";
            echo "<th style='width: 120px; padding: 10px 5px;'>Deactivated By</th>";
            echo "<th style='width: 80px; padding: 10px 5px; text-align: center;'>Status</th>";
            echo "</tr>";
            echo "</thead>";
            
            echo "<tbody>";
            $row_count = $start + 1;
            while ($row = mysqli_fetch_assoc($result_patients)) {
                $bg_color = ($row_count % 2 == 0) ? '#F2F2FF' : '#FFFFFF';
                $reason = $row['Reason'] ?? 'UNKNOWN';
                $details = $row['Details'] ?? '';
                
                // Apply CSS class based on reason
                $reason_class = '';
                switch (strtoupper($reason)) {
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
                }
                
                echo "<tr style='background-color: " . $bg_color . ";'>";
                echo "<td style='padding: 8px 5px; text-align: center;'>" . $row_count . "</td>";
                echo "<td style='padding: 8px 5px;'>" . htmlspecialchars(strtoupper($row['Last_name'] ?? '')) . "</td>";
                echo "<td style='padding: 8px 5px;'>" . htmlspecialchars(strtoupper($row['First_name'] ?? '')) . "</td>";
                echo "<td style='padding: 8px 5px;'>" . htmlspecialchars(strtoupper($row['Middle_name'] ?? '')) . "</td>";
                echo "<td style='padding: 8px 5px;'>" . htmlspecialchars(strtoupper($row['Barangay'] ?? '')) . "</td>";
                echo "<td style='padding: 8px 5px;'><span class='" . $reason_class . "'>" . htmlspecialchars($reason) . "</span></td>";
                echo "<td style='padding: 8px 5px; text-align: center;'>" . htmlspecialchars($row['deactivation_date'] ?? '') . "</td>";
                echo "<td style='padding: 8px 5px; word-wrap: break-word; font-size: 12px;'>" .
                    nl2br(htmlspecialchars(substr($details, 0, 200))) .
                    (strlen($details) > 200 ? '...' : '') . "</td>";
                echo "<td style='padding: 8px 5px; text-align: center;'>" . htmlspecialchars($row['is_set_by'] ?? 'Unknown') . "</td>";
                echo "<td style='padding: 2px 2px; text-align: center;'>";
                
                // Reactivate button - DISABLE IF REASON IS DECEASED
                if (strtoupper($reason) === 'DECEASED') {
                    echo "<button disabled
                        style='padding:6px 8px; border-radius:3px; font-size:11px; 
                               background-color:#6c757d; color:white; border:none; cursor:not-allowed; opacity:0.65;'>
                        INACTIVE</button>";
                } else {
                    echo "<a href='transact/reactivate_patient.php?c=" . urlencode($row['Patient_id']) . "' 
                        class='btn-danger'
                        style='padding:6px 8px; border-radius:3px; text-decoration:none; font-size:11px; 
                               background-color:#dc3545; color:white; display:inline-block;' 
                        onclick=\"return confirm('Are you sure you want to reactivate this patient?');\">
                        INACTIVE</a>";
                }
                echo "</td>";
                echo "</tr>";
                
                $row_count++;
            }
            
            echo "</tbody>";
            echo "</table>";
            echo "</div>";
            echo "</div>";
            
            // PAGINATION
            echo "<div style='text-align: center; margin: 20px 0;'>";
            if ($totalPages > 1) {
                echo "<div class='pagination' style='display: inline-block;'>";
                echo "<div class='page-info' style='margin-bottom: 10px;'>Page $page of $totalPages</div>";
                echo "<div style='display: flex; justify-content: center; align-items: center; gap: 10px;'>";
                
                // Previous button
                if ($page > 1) {
                    $prev_url = "inactive_patient.php?page=" . ($page - 1) . "&op=" . $ord_patients . "&dir=" . $dir;
                    if (!empty($search)) $prev_url .= "&dosearch=" . urlencode($search);
                    if ($current_reason !== 'all') $prev_url .= "&reason=" . urlencode($current_reason);
                    
                    echo "<a href='$prev_url' class='pagination-btn prev' style='padding: 8px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Previous</a>";
                }
                
                // Page numbers
                echo "<div style='display: flex; gap: 5px;'>";
                for ($i = 1; $i <= $totalPages; $i++) {
                    if ($i == $page) {
                        echo "<span style='padding: 8px 12px; background: #28a745; color: white; border-radius: 4px; font-weight: bold;'>$i</span>";
                    } else {
                        $page_url = "inactive_patient.php?page=" . $i . "&op=" . $ord_patients . "&dir=" . $dir;
                        if (!empty($search)) $page_url .= "&dosearch=" . urlencode($search);
                        if ($current_reason !== 'all') $page_url .= "&reason=" . urlencode($current_reason);
                        
                        echo "<a href='$page_url' style='padding: 8px 12px; background: #f0f0f0; color: #333; text-decoration: none; border-radius: 4px;'>$i</a>";
                    }
                }
                echo "</div>";
                
                // Next button
                if ($page < $totalPages) {
                    $next_url = "inactive_patient.php?page=" . ($page + 1) . "&op=" . $ord_patients . "&dir=" . $dir;
                    if (!empty($search)) $next_url .= "&dosearch=" . urlencode($search);
                    if ($current_reason !== 'all') $next_url .= "&reason=" . urlencode($current_reason);
                    
                    echo "<a href='$next_url' class='pagination-btn next' style='padding: 8px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Next</a>";
                }
                
                echo "</div>";
                echo "</div>";
            } else {
                echo "<div class='page-info' style='margin: 20px 0;'>Page $page of $totalPages</div>";
            }
            echo "</div>";
            
        } else {
            echo "<div style='text-align: center;'>";
            if ($current_reason !== 'all') {
                echo "<div style='color: #b30000; background-color: #ffe6e6; font-weight: bold; padding: 10px; border-radius: 6px; max-width: 500px; margin: 20px auto;'>
                    No inactive patients found with reason: <span style='color: #dc3545;'>" . htmlspecialchars($current_reason) . "</span>
                    <br><a href='inactive_patient.php?op=" . $ord_patients . "&dir=" . $dir . "&page=1" . (!empty($search) ? "&dosearch=" . urlencode($search) : "") . "' 
                          style='color: #007bff; text-decoration: none; margin-top: 5px; display: inline-block;'>[Show All Inactive Patients]</a>
                </div>";
            } else {
                echo $table_patients;
            }
            echo "</div>";
        }
        
        mysqli_close($conn);
        ?>
    </div>
</body>
</html>