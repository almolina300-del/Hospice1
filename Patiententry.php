<!DOCTYPE html>
<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for success message from login
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it after getting
}

if (!isset($_SESSION['Username'])) {
    // Redirect to login page
    header("Location: index.php");
    exit();
}

require('Config/Config.php');

// THE $BG VARIABLE HAS THE COLOR VALUE OF ALL THE ODD ROWS. YOU CAN CHANGE THIS TO ANOTHER COLOR
$bg = 'F2F2FF';

// THESE CODES GETS THE VALUE OF 'O', WHICH IS USED TO FIND OUT HOW THE RECORDS WILL BE ORDERED
$ord = '';
if (isset($_GET['o'])) {
    $ord = $_GET['o'];
};

// IF THE VALUE OF $ORD IS A NUMBER, IT IS ROUNDED OF TO AN INTEGER. IF IT ISN'T, $ORD IS ASSIGNED A VALUE OF 1
if (is_numeric($ord)) {
    $ord = round(min(max($ord, 1), 3));
} else {
    $ord = 1;
}
// $ORDER IS ASSIGNED A STRING VALUE BASED ON THE VALUE OF $ORD.
$order = array(
    1 => 'Last_name ASC',
    2 => 'First_name ASC',
    3 => 'Middle_name ASC',
);

// A $CONN VARIABLE IS ASSIGNED THE DATA OBTAINED FROM CONNECTING TO THE MYSQL SERVER. SQL_HOST, SQL_USER, SQL_PASS ALL HOLD VALUES STORED IN CONFIG.PHP
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS)
    or die('Could not connect to MySQL database. ' . mysqli_connect_error());

// THE DATABASE IS SELECTED USING THE DATA STORED IN $CONN
mysqli_select_db($conn, SQL_DB);

// Check if barangay filter is active
$barangay_filter = isset($_GET['barangay_filter']) ? mysqli_real_escape_string($conn, $_GET['barangay_filter']) : '';
$barangayFilterActive = !empty($barangay_filter) && $barangay_filter != 'all';

// Get count for selected barangay
$barangay_count = 0;
if ($barangayFilterActive) {
    $count_query = "SELECT COUNT(*) as count FROM patient_details WHERE is_active = 1 AND Barangay = '$barangay_filter'";
    $result = mysqli_query($conn, $count_query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $barangay_count = $row['count'];
    }
}

?>

<html>

<head>
    <title>Hospice</title>
    <link rel="stylesheet" type="text/css" href="CSS/style.css">
    <script src="js/notifications.js"></script>
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

        <a href="patiententry.php" style="background-color: whitesmoke; padding: 8px 12px; border-radius: 0px; display: inline-block; margin: 4px 0; text-decoration: none; color: #263F73; font-weight: bold;">
            Patient Records
        </a>
        <a href="bulk_print.php">
            Bulk Print
        </a>

        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <a href="Doctors.php">
                Doctors
            </a> <?php endif; ?>
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

    <h1 align='center'> <img src="img/patient_record_icon.png" alt="patient_record_icon" class="logo">Patient Records</h1>

    <div class="datetime-header" id="liveDateTime">
        <?php
        date_default_timezone_set('Asia/Manila');
        echo date('F j, Y') . ' | ' . date('h:i:s A');
        ?>
    </div>

    <?php
    // Count total records
    $count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM patient_details WHERE is_active = 1");
    $count_result = mysqli_fetch_assoc($count_query);

    if ($count_result) {
        echo "<div style='display:flex; align-items:center; gap:20px; margin-bottom:20px;'>";
        echo "<div class='blink-text' style='background-color:white; 
                         padding:10px 20px; margin-left:300px; border-radius:8px; font-weight:bold; 
                         color:#263F73; width:200px; text-align:center;
                         box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
        echo "Active Patient<br><span style='font-size:30px;'>" . $count_result['total'] . "</span>";
        echo "</div>";

        // Show barangay count if filter is active
        if ($barangayFilterActive && $barangay_count > 0) {
            echo "<div style='background-color:#4CAF50; color:white; padding:10px 20px; border-radius:8px; font-weight:bold;
                         box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
            echo htmlspecialchars($barangay_filter) . "<br><span style='font-size:30px;'>" . $barangay_count . "</span>";
            echo "</div>";
        }

        echo "</div>";
    }
    ?>
    <hr style="margin: 20px 250px; border: 1px solid #ccc; width: 80%;">

    <!-- Search container -->
    <div style="background-color: white; padding: 15px 15px; margin: 0 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #ddd; margin-bottom: 20px;">
        <form method="post" action="Patiententry.php" name="theform" style="display: flex; align-items: center; gap: 10px;">
            <input type="text" name="dosearch" placeholder="Enter Lastname, Firstname, or Full Name"
                value="<?php echo htmlspecialchars($_GET['dosearch'] ?? ''); ?>"
                style="flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px;">
            <input type="submit" name="action" value="Search"
                style="padding: 8px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            <a href="ptedit.php"
                style="padding: 7px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; white-space: nowrap;">
                Add New Patient
            </a>
        </form>
        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <!-- Add this button somewhere in your Patiententry.php -->
            <div style="text-align: right; margin: 20px 0;">
                <button onclick="showInactivePatientsModal()"
                    style="background-color: #dc3545; color: white; border: none; padding: 10px 10px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                    View Inactive Patients
                </button>
            </div>

            <!-- Modal container -->
            <div id="inactivePatientsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center;">
                <div style="background:white; padding:20px; border-radius:8px; width:90%; max-width:1200px; max-height:80vh; overflow-y:auto;">
                    <!-- Content will be loaded here -->
                </div>
            </div>

            <script>
                function showInactivePatientsModal() {
                    const modal = document.getElementById('inactivePatientsModal');
                    modal.style.display = 'flex';

                    // Load content via AJAX
                    fetch('inactive_patients_modal.php')
                        .then(response => response.text())
                        .then(html => {
                            modal.querySelector('div').innerHTML = html;
                        })
                        .catch(error => {
                            modal.querySelector('div').innerHTML = '<p style="color:red;">Error loading inactive patients</p>';
                            console.error('Error:', error);
                        });
                }

                // Update this function in your parent window
                function reloadInactiveModal(url) {
                    console.log('reloadInactiveModal called with URL:', url);
                    const modal = document.getElementById('inactivePatientsModal');
                    const modalContent = modal.querySelector('div'); // The div inside the modal

                    fetch(url)
                        .then(response => response.text())
                        .then(html => {
                            modalContent.innerHTML = html;
                            console.log('Modal content reloaded successfully');
                        })
                        .catch(error => {
                            console.error('Error reloading modal:', error);
                            modalContent.innerHTML = '<p style="color:red; padding:20px;">Error reloading content: ' + error.message + '</p>';
                        });
                }

                function closeInactiveModal() {
                    document.getElementById('inactivePatientsModal').style.display = 'none';
                }

                // Close with Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeInactiveModal();
                    }
                });
            </script>
        <?php endif; ?>
    </div>

    <?php
    // Number of rows per page
    $limit = 50;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $start = ($page - 1) * $limit;
    $runsearch = mysqli_real_escape_string($conn, $_GET['dosearch'] ?? '');

    // SEARCH
    if (isset($_POST['dosearch'])) {
        $runsearch = $_POST['dosearch'];

        // Split the search term by spaces
        $searchTerms = explode(' ', trim($runsearch));

        // Build the WHERE clause dynamically
        $whereClauses = [];

        if (!empty($searchTerms)) {
            // If single term, search in both first and last name
            if (count($searchTerms) == 1) {
                $term = mysqli_real_escape_string($conn, $searchTerms[0]);
                $whereClauses[] = "(`Last_name` LIKE '%$term%' OR `First_name` LIKE '%$term%')";
            }
            // If two terms, assume first is firstname and second is lastname
            elseif (count($searchTerms) == 2) {
                $term1 = mysqli_real_escape_string($conn, $searchTerms[0]); // First name
                $term2 = mysqli_real_escape_string($conn, $searchTerms[1]); // Last name

                // Search for exact combination: firstname + lastname
                $whereClauses[] = "(`First_name` LIKE '%$term1%' AND `Last_name` LIKE '%$term2%')";
                // Also search for reversed combination: lastname + firstname
                $whereClauses[] = "(`Last_name` LIKE '%$term1%' AND `First_name` LIKE '%$term2%')";
            }
            // If more than two terms, search for each term in either field
            else {
                foreach ($searchTerms as $term) {
                    $safeTerm = mysqli_real_escape_string($conn, $term);
                    if (!empty($safeTerm)) {
                        $whereClauses[] = "(`Last_name` LIKE '%$safeTerm%' OR `First_name` LIKE '%$safeTerm%' OR `Middle_name` LIKE '%$safeTerm%')";
                    }
                }
            }
        }

        // Build the final SQL query
        if (!empty($whereClauses)) {
            $whereCondition = "(" . implode(" OR ", $whereClauses) . ") AND is_active = 1";
        } else {
            $whereCondition = "is_active = 1";
        }

        $sql = "SELECT * FROM patient_details 
            WHERE $whereCondition";

        if (!empty($barangay_filter)) {
            $sql .= " AND Barangay = '$barangay_filter'";
        }

        $sql .= " ORDER BY " . $order[$ord] . " LIMIT $start, $limit";

        // Count query
        $countSql = "SELECT COUNT(*) AS total FROM patient_details 
                 WHERE $whereCondition";

        if (!empty($barangay_filter)) {
            $countSql .= " AND Barangay = '$barangay_filter'";
        }
    } else {
        // NO SEARCH - SHOW ALL RECORDS
        $sql = "SELECT * FROM patient_details 
            WHERE is_active = 1";

        if (!empty($barangay_filter)) {
            $sql .= " AND Barangay = '$barangay_filter'";
        }

        $sql .= " ORDER BY " . $order[$ord] . " LIMIT $start, $limit";

        $countSql = "SELECT COUNT(*) AS total FROM patient_details 
                 WHERE is_active = 1";

        if (!empty($barangay_filter)) {
            $countSql .= " AND Barangay = '$barangay_filter'";
        }
    }

    // EXECUTE QUERIES
    $result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
    $countResult = mysqli_query($conn, $countSql) or die(mysqli_error($conn));
    $totalRows = mysqli_fetch_assoc($countResult)['total'];
    $totalPages = ceil($totalRows / $limit);
    // TABLE
    if (mysqli_num_rows($result) > 0) {
        echo "<div style='max-height:500px; overflow-y:auto;'>";

        echo "<table align='center' border='5' cellpadding='2' width='100%'>";
        echo "<tr style='background-color:#263F73; color:white;'>";
        echo "<th><a href='" . $_SERVER['PHP_SELF'] . "?o=1' style='color:white; text-decoration:none;'>Last name</a></th>";
        echo "<th><a href='" . $_SERVER['PHP_SELF'] . "?o=2' style='color:white; text-decoration:none;'>First name</a></th>";
        echo "<th><a href='" . $_SERVER['PHP_SELF'] . "?o=3' style='color:white; text-decoration:none;'>Middle name</a></th>";

        // Barangay header with filter button
        echo "<th>";
        echo "<div class='barangay-header-container'>";

        // Barangay link
        echo "<span style='color:white;'>Barangay</span>";

        // Show filter indicator if active
        if ($barangayFilterActive) {
            echo "<span class='filter-indicator'>🔍</span>";

            // Show count badge in the header
            echo "<span class='barangay-count-badge' title='$barangay_count patients in $barangay_filter'>$barangay_count</span>";
        }

        // Filter button with dynamic icon
        $filterIcon = $barangayFilterActive ? "🔽" : "▼";
        echo "<button class='filter-btn' onclick=\"showBarangayFilter()\" title='Filter by Barangay'>$filterIcon</button>";

        // Clear filter button (only show when filter is active)
        if ($barangayFilterActive) {
            // Build clear URL
            $queryParams = $_GET;
            unset($queryParams['barangay_filter']);
            unset($queryParams['page']); // Reset to page 1
            $clearUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($queryParams);

            echo "<a href='" . $clearUrl . "' class='clear-filter-btn' title='Clear Barangay Filter'>❌</a>";
        }

        echo "</div>";
        echo "</th>";

        echo "<th>Birthday</th>";
        echo "<th>Status</th></tr>";

        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>
            <td align='center' style='cursor: pointer;' onclick=\"window.location='ptedit.php?c=" . $row['Patient_id'] . "'\">" . strtoupper($row['Last_name']) . "</td>
            <td align='center' style='cursor: pointer;' onclick=\"window.location='ptedit.php?c=" . $row['Patient_id'] . "'\">" . strtoupper($row['First_name']) . "</td>
            <td align='center' style='cursor: pointer;' onclick=\"window.location='ptedit.php?c=" . $row['Patient_id'] . "'\">" . strtoupper($row['Middle_name']) . "</td>
            <td align='center' style='cursor: pointer;' onclick=\"window.location='ptedit.php?c=" . $row['Patient_id'] . "'\">" . strtoupper($row['Barangay']) . "</td>
            <td align='center' style='cursor: pointer;' onclick=\"window.location='ptedit.php?c=" . $row['Patient_id'] . "'\">" . strtoupper($row['Birthday']) . "</td>
            <td align='center'>
                <a href='Pttransact.php?c=" . $row['Patient_id'] . "&a=Deactivate Record' 
                    style='background-color:#3CB371; color:white; padding:4px 5px; border-radius:3px; 
                    text-decoration:none; font-size:10px;' 
                    onclick=\"return confirm('Deactivate this patient?');\">Active</a>
            </td>
        </tr>";
        }

        echo "</table>";
        echo "</div>";
    } else {
        echo "<div style='text-align:center; margin-top:20px;'>
            <div style='color:#b30000; background-color:#ffe6e6;
                        font-weight:bold; padding:10px; border-radius:6px;
                        width:300px; margin:0 auto;'>
                No Record Found!
            </div>
          </div>";
    }

    // PAGINATION DISPLAY
    echo "<div style='text-align:center; margin-top:10px; font-weight:bold;'>";
    echo "Page $page / $totalPages";
    echo "</div>";

    // PAGINATION LINKS
    echo "<div style='text-align:center; margin-top:10px;'>";

    if ($page > 1) {
        $prev_link = "?page=" . ($page - 1) . "&o=" . $ord;
        if (!empty($runsearch)) $prev_link .= "&dosearch=" . urlencode($runsearch);
        if (!empty($barangay_filter)) $prev_link .= "&barangay_filter=" . urlencode($barangay_filter);

        echo "<a href='" . $prev_link . "' 
            style='padding:8px 15px; margin-right:10px; 
                   background:#DAA520; color:white; 
                   text-decoration:none; border-radius:5px;'>
            Previous
          </a>";
    }

    if ($page < $totalPages) {
        $next_link = "?page=" . ($page + 1) . "&o=" . $ord;
        if (!empty($runsearch)) $next_link .= "&dosearch=" . urlencode($runsearch);
        if (!empty($barangay_filter)) $next_link .= "&barangay_filter=" . urlencode($barangay_filter);

        echo "<a href='" . $next_link . "' 
            style='padding:8px 15px; 
                   background:#28A745; color:white; 
                   text-decoration:none; border-radius:5px;'>
            Next
          </a>";
    }

    echo "</div>";
    ?>

    <!-- Barangay Filter Modal -->
    <div id="barangayFilterModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; justify-content:center; align-items:center;">
        <div style="background:white; padding:20px; border-radius:8px; width:400px; max-height:80vh; overflow-y:auto;">
            <h3 style="margin-top:0; color:#263F73;">Filter by Barangay</h3>
            <form method="get" action="Patiententry.php" id="barangayFilterForm">
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Select Barangay:</label>
                    <select name="barangay_filter" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                        <option value="">All Barangays</option>
                        <?php
                        $barangay_query = mysqli_query($conn, "SELECT DISTINCT Barangay FROM patient_details WHERE is_active = 1 AND Barangay != '' ORDER BY Barangay ASC");
                        $current_filter = $_GET['barangay_filter'] ?? '';
                        while ($barangay_row = mysqli_fetch_assoc($barangay_query)) {
                            $barangay_name = strtoupper($barangay_row['Barangay']);

                            // Get count for this barangay
                            $count_query = "SELECT COUNT(*) as count FROM patient_details WHERE is_active = 1 AND Barangay = '$barangay_name'";
                            $count_result = mysqli_query($conn, $count_query);
                            $count = 0;
                            if ($count_result) {
                                $count_row = mysqli_fetch_assoc($count_result);
                                $count = $count_row['count'];
                            }

                            $selected = ($current_filter == $barangay_name) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($barangay_name) . "' $selected>" . $barangay_name . " ($count patients)</option>";
                        }
                        ?>
                    </select>
                </div>

                <input type="hidden" name="page" value="1">
                <input type="hidden" name="o" value="<?php echo $ord; ?>">
                <?php if (!empty($runsearch)): ?>
                    <input type="hidden" name="dosearch" value="<?php echo htmlspecialchars($runsearch); ?>">
                <?php endif; ?>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" style="flex:1; padding:10px; background:#263F73; color:white; border:none; border-radius:4px; cursor:pointer;">Apply Filter</button>
                    <button type="button" onclick="hideBarangayFilter()" style="flex:1; padding:10px; background:#6c757d; color:white; border:none; border-radius:4px; cursor:pointer;">Cancel</button>
                    <?php if (!empty($current_filter)): ?>
                        <a href="?page=1&o=<?php echo $ord; ?><?php echo !empty($runsearch) ? '&dosearch=' . urlencode($runsearch) : ''; ?>"
                            style="flex:1; padding:10px; background:#dc3545; color:white; text-decoration:none; border-radius:4px; text-align:center; line-height:38px;">
                            Clear Filter
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript for Barangay Filter -->
    <script>
        function showBarangayFilter() {
            document.getElementById('barangayFilterModal').style.display = 'flex';
        }

        function hideBarangayFilter() {
            document.getElementById('barangayFilterModal').style.display = 'none';
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideBarangayFilter();
            }
        });
    </script>

    <!-- Rest of your existing scripts remain the same -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.querySelector('form[name="theform"]');
            const loader = document.getElementById('loader');

            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    loader.style.display = 'block';

                    const minLoaderTime = 500;
                    const fakeProcessing = new Promise((resolve) => {
                        setTimeout(resolve, 100);
                    });

                    const loaderTimeout = new Promise((resolve) => {
                        setTimeout(resolve, minLoaderTime);
                    });

                    Promise.all([fakeProcessing, loaderTimeout]).then(() => {
                        loader.style.display = 'none';
                        searchForm.submit();
                    });
                });
            }

            <?php if (isset($success_message) && !empty($success_message)): ?>
                if (window.CustomNotification) {
                    window.CustomNotification.show(
                        "<?php echo $success_message; ?>",
                        'success',
                        5000
                    );
                } else {
                    alert("<?php echo $success_message; ?>");
                }
            <?php endif; ?>
        });
    </script>

    <!-- LOADER -->
    <div id="loader" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.7); z-index:9999; text-align:center; padding-top:200px;">
        <div class="spinner" style="
                border: 8px solid #f3f3f3;
                border-top: 8px solid #263F73;
                border-radius: 50%;
                width: 60px;
                height: 60px;
                margin: 0 auto;
                animation: spin 1s linear infinite;">
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.querySelector('form[name="theform"]');
            const loader = document.getElementById('loader');

            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    loader.style.display = 'block';
                });
            }
        });
    </script>

    <script>
        function updateDateTime() {
            const now = new Date();
            const months = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            const month = months[now.getMonth()];
            const day = now.getDate();
            const year = now.getFullYear();

            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';

            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            const dateStr = month + ' ' + day + ', ' + year;
            const timeStr = hours + ':' + minutes + ':' + seconds + ' ' + ampm;

            document.getElementById('liveDateTime').innerHTML = dateStr + ' | ' + timeStr;
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>

</body>

</html>