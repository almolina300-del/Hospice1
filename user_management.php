<!DOCTYPE html>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

// Check if user is SUADMIN - if not, show access restricted
$is_suadmin = ($_SESSION['Role'] === 'SUADMIN');

// Check for success message from login or user operations
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it after getting
}

require('Config/Config.php');

// Only proceed with database operations if user is SUADMIN
if ($is_suadmin) {
    // THE $BG VARIABLE HAS THE COLOR VALUE OF ALL THE ODD ROWS. YOU CAN CHANGE THIS TO ANOTHER COLOR
    $bg = 'F2F2FF';

    // THESE CODES GETS THE VALUE OF 'O', WHICH IS USED TO FIND OUT HOW THE RECORDS WILL BE ORDERED
    $ord = '';
    if (isset($_GET['o'])) {
        $ord = $_GET['o'];
    };

    // IF THE VALUE OF $ORD IS A NUMBER, IT IS ROUNDED OF TO AN INTEGER. IF IT ISN'T, $ORD IS ASSIGNED A VALUE OF 1
    if (is_numeric($ord)) {
        $ord = round(min(max($ord, 1), 4));
    } else {
        $ord = 1;
    }
    // $ORDER IS ASSIGNED A STRING VALUE BASED ON THE VALUE OF $ORD.
    $order = array(
        1 => 'Username ASC',
        2 => 'First_name ASC',
        3 => 'Last_name ASC',
        4 => 'User_type ASC'
    );

    // A $CONN VARIABLE IS ASSIGNED THE DATA OBTAINED FROM CONNECTING TO THE MYSQL SERVER. SQL_HOST, SQL_USER, SQL_PASS ALL HOLD VALUES STORED IN CONFIG.PHP
    $conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS)
        or die('Could not connect to MySQL database. ' . mysqli_connect_error());

    // THE DATABASE IS SELECTED USING THE DATA STORED IN $CONN
    mysqli_select_db($conn, SQL_DB);

    // Handle user deactivation/reactivation
    if (isset($_GET['action']) && isset($_GET['User_id'])) {
        $user_id = mysqli_real_escape_string($conn, $_GET['User_id']);
        $action = mysqli_real_escape_string($conn, $_GET['action']);
        
        if ($action === 'deactivate') {
            // Prevent deactivating your own account
            if ($user_id == ($_SESSION['User_id'] ?? '')) {
                $error_message = "You cannot deactivate your own account!";
            } else {
                $sql = "UPDATE user_management SET is_active = 0 WHERE User_id = '$user_id'";
                $message = "User deactivated successfully!";
            }
        } elseif ($action === 'activate') {
            $sql = "UPDATE user_management SET is_active = 1 WHERE User_id = '$user_id'";
            $message = "User activated successfully!";
        }
        
        if (isset($sql)) {
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success_message'] = $message;
                // Refresh the page
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error_message = "Error updating user: " . mysqli_error($conn);
            }
        }
    }
}

?>

<html>

<head>
    <title>Prescription</title>
    <link rel="stylesheet" type="text/css" href="CSS/style.css">
    <script src="js/notifications.js"></script>
    <style>
        /* Additional styles for user management */
        .user-type-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .user-type-admin {
            background-color: #FF6B6B;
            color: white;
        }
        
        .user-type-staff {
            background-color: #4ECDC4;
            color: white;
        }
        
        .user-type-doctor {
            background-color: #45B7D1;
            color: white;
        }
        
        .user-type-nurse {
            background-color: #96CEB4;
            color: white;
        }
        
        .status-active {
            color: #3CB371;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #FF6B6B;
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .action-btn {
            padding: 4px 8px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 10px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .view-btn {
            background-color: #007bff;
            color: white;
        }
        
        .edit-btn {
            background-color: #FFA500;
            color: white;
        }
        
        .deactivate-btn {
            background-color: #3CB371;
            color: white;
        }
        
        .activate-btn {
            background-color: #FF6B6B;
            color: white;
        }
        
        .reset-btn {
            background-color: #9C27B0;
            color: white;
        }
        
        /* Disabled button styles */
        .disabled-link {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
            text-decoration: none;
        }
        
        .disabled-button {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
        }
        
        /* Access Restricted Message Styles */
        .access-restricted {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 70vh;
            text-align: center;
        }
        
        .access-restricted-box {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            max-width: 500px;
            width: 100%;
            border: 2px solid #dc3545;
        }
        
        .access-restricted-box h2 {
            color: #dc3545;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .access-restricted-box p {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
            font-size: 16px;
        }
        
        .current-role {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
            color: #495057;
        }
        
        .blink-text {
            animation: blinker 1.5s ease-in-out 1;
        }
        
        @keyframes blinker {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
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

        <style>
            .logo {
                width: 30px;
                height: 30px;
                vertical-align: middle;
                margin-right: 6px;
            }
        </style>

        <a href="patiententry.php">
            Patient Records
        </a>
        <a href="inactive_patient.php">
            Inactive Patients
        </a>
        <a href="bulk_print.php">
            Bulk Print
        </a>
        <a href="Doctors.php">
            Doctors
        </a>
         <a href="Medicines.php">Medicines</a>
        <a href="user_management.php" style="background-color: whitesmoke; padding: 8px 12px; border-radius: 0px; display: inline-block; margin: 4px 0; text-decoration: none; color: #263F73; font-weight: bold;">
            User Management
        </a>
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

    <?php if ($is_suadmin): ?>
        <!-- SUADMIN VIEW: Show full user management interface -->
        <h1 align='center'> <img src="img/user_management_icon.png" alt="user_management_icon" class="logo">User Management</h1>

        <?php
        // Count total users
        $count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM user_management");
        $count_result = mysqli_fetch_assoc($count_query);
        
        // Count active users
        $active_query = mysqli_query($conn, "SELECT COUNT(*) AS active FROM user_management WHERE is_active = 1");
        $active_result = mysqli_fetch_assoc($active_query);
        
        // REMOVED: Admin and Staff counts

        if ($count_result) {
            echo "<div style='display:flex; align-items:center; gap:20px; margin-bottom:20px; justify-content:center; flex-wrap:wrap;'>";
            
            // Total Users box
            echo "<div class='blink-text' style='background-color:white; 
                             padding:10px 20px; border-radius:8px; font-weight:bold; 
                             color:#263F73; width:180px; text-align:center;
                             box-shadow: 0 4px 8px rgba(0,0,0,0.2); margin-left:0;'>";
            echo "Total Users<br><span style='font-size:30px;'>" . $count_result['total'] . "</span>";
            echo "</div>";
            
            // Active Users box
            echo "<div style='background-color:white; 
                             padding:10px 20px; border-radius:8px; font-weight:bold; 
                             color:#3CB371; width:180px; text-align:center;
                             box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
            echo "Active Users<br><span style='font-size:30px;'>" . $active_result['active'] . "</span>";
            echo "</div>";
            
            echo "</div>"; // close flex container
        }
        ?>
        
        <hr style="margin: 20px 250px; border: 1px solid #ccc; width: 80%;">

        <!-- Search form with Add New User button -->
        <form method="post" action="user_management.php" name="userform"
            style="text-align:left; margin-bottom:5px; margin-left:300px;">
            <input type="text" name="dosearch" placeholder="Search by username or name" value="<?php echo htmlspecialchars($_GET['dosearch'] ?? ''); ?>">
            
            <!-- Search button -->
            <input type="submit" name="action" value="Search" style="padding:5px 12px; margin-left:5px;">
            
            <!-- Add New User button next to search - DISABLED -->
            <span class="disabled-button"
                style="padding:6px 80px ; border-radius:5px; border:1px solid #ccc; background-color:#263F73; 
                                   text-decoration:none; color:white; display:inline-block; margin-left:450px;">
                Add New User
            </span>
        </form>

        <?php
        // Number of rows per page
        $limit = 15;

        // Get current page from URL, default = 1
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;

        // Calculate starting record
        $start = ($page - 1) * $limit;

        $runsearch = isset($_POST['dosearch']) ? mysqli_real_escape_string($conn, $_POST['dosearch']) : (isset($_GET['dosearch']) ? mysqli_real_escape_string($conn, $_GET['dosearch']) : '');

        // SEARCH
        if (!empty($runsearch)) {
            $sql = "SELECT * FROM User_management
            WHERE (Username LIKE '%$runsearch%' OR First_name LIKE '%$runsearch%' OR Last_name LIKE '%$runsearch%')
            ORDER BY " . $order[$ord] . " 
            LIMIT $start, $limit";

            // Count total results for pagination
            $countSql = "SELECT COUNT(*) AS total FROM User_management 
            WHERE (Username LIKE '%$runsearch%' OR First_name LIKE '%$runsearch%' OR Last_name LIKE '%$runsearch%')";
        } else {
            // Show all users
            $sql = "SELECT * FROM User_management
            ORDER BY " . $order[$ord] . " 
            LIMIT $start, $limit";

            // Count all records
            $countSql = "SELECT COUNT(*) AS total FROM User_management";
        }

        // EXECUTE QUERIES
        $result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
        $countResult = mysqli_query($conn, $countSql) or die(mysqli_error($conn));
        $totalRows = mysqli_fetch_assoc($countResult)['total'];

        $totalPages = ceil($totalRows / $limit);

        // Display error message if exists
        if (isset($error_message)) {
            echo "<div style='text-align:center; margin:10px 0;'>
                    <div style='color:#b30000; background-color:#ffe6e6;
                                font-weight:bold; padding:10px; border-radius:6px;
                                width:300px; margin:0 auto;'>
                        " . htmlspecialchars($error_message) . "
                    </div>
                  </div>";
        }

        // TABLE
        if (mysqli_num_rows($result) > 0) {
            echo "<div style='max-height:400px; overflow-y:auto;'>";

            echo "<table align='center' border='5' cellpadding='2' width='100%'>";
            echo "<tr style='background-color:#263F73; color:white;'>";
            echo "<th><a href='" . $_SERVER['PHP_SELF'] . "?o=3' style='color:white; text-decoration:none;'>Last Name</a></th>";
            echo "<th><a href='" . $_SERVER['PHP_SELF'] . "?o=2' style='color:white; text-decoration:none;'>First Name</a></th>";
            echo "<th><a href='" . $_SERVER['PHP_SELF'] . "?o=4' style='color:white; text-decoration:none;'>Role</a></th>";
            echo "<th>Email</th>";
            echo "<th>Status</th>";
            echo "<th>Actions</th></tr>";

            while ($row = mysqli_fetch_assoc($result)) {
                // Determine user type badge class
                $type_class = 'user-type-staff';
                if ($row['Role'] == 'ADMIN') $type_class = 'Role-ADMIN';
                if ($row['Role'] == 'USER') $type_class = 'Role-USER';
                
                // Determine status
                $status_class = $row['is_active'] ? 'status-active' : 'status-inactive';
                $status_text = $row['is_active'] ? 'Active' : 'Inactive';
                
             echo "<tr>
                <td align='center'>" . htmlspecialchars($row['First_name']) . "</td>
                <td align='center'>" . htmlspecialchars($row['Last_name']) . "</td>
                <td align='center'><span class='user-type-badge $type_class'>" . strtoupper($row['Role']) . "</span></td>
                <td align='center'>" . htmlspecialchars($row['Email']) . "</td>
                <td align='center' class='$status_class'>$status_text</td>
                <td align='center'>
                    <div class='action-buttons'>
                        <!-- View button - DISABLED -->
                        <span class='action-btn view-btn disabled-link'>View</span>
                        
                        <!-- Edit button - DISABLED -->
                        <span class='action-btn edit-btn disabled-link'>Edit</span>";
                
                // Show deactivate/activate button based on current status (NOT DISABLED)
            if ($row['is_active']) {
    echo "<a href='user_management.php?User_id=" . $row['User_id'] . "&action=deactivate' 
            class='action-btn deactivate-btn'
            onclick=\"return confirm('Deactivate user " . htmlspecialchars($row['Username']) . "?');\">Deactivate</a>";
} else {
    echo "<a href='user_management.php?User_id=" . $row['User_id'] . "&action=activate' 
            class='action-btn activate-btn'
            onclick=\"return confirm('Activate user " . htmlspecialchars($row['Username']) . "?');\">Activate</a>";
}

                echo "</div></td></tr>";
            }

            echo "</table>";
            echo "</div>";
        } else {
            echo "<div style='text-align:center; margin-top:20px;'>
                <div style='color:#b30000; background-color:#ffe6e6;
                            font-weight:bold; padding:10px; border-radius:6px;
                            width:300px; margin:0 auto;'>
                    No Users Found!
                </div>
              </div>";
        }

        // PAGINATION DISPLAY: e.g. "Page 1/2"
        echo "<div style='text-align:center; margin-top:10px; font-weight:bold;'>";
        echo "Page $page / $totalPages";
        echo "</div>";

        // PAGINATION LINKS
        echo "<div style='text-align:center; margin-top:10px;'>";

        if ($page > 1) {
            echo "<a href='?page=" . ($page - 1) . (empty($runsearch) ? '' : '&dosearch=' . urlencode($runsearch)) . "' 
                style='padding:8px 15px; margin-right:10px; 
                       background:#DAA520; color:white; 
                       text-decoration:none; border-radius:5px;'>
                Previous
              </a>";
        }

        if ($page < $totalPages) {
            echo "<a href='?page=" . ($page + 1) . (empty($runsearch) ? '' : '&dosearch=' . urlencode($runsearch)) . "' 
                style='padding:8px 15px; 
                       background:#28A745; color:white; 
                       text-decoration:none; border-radius:5px;'>
                Next
              </a>";
        }
        ?>

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

        <!-- JAVASCRIPT FOR LOADER -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchForm = document.querySelector('form[name="userform"]');
                const loader = document.getElementById('loader');

                if (searchForm) {
                    searchForm.addEventListener('submit', function() {
                        // Show loader when form is submitted
                        loader.style.display = 'block';
                    });
                }
                
                // Hide loader when page loads
                loader.style.display = 'none';
            });
        </script>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                <?php if (isset($success_message) && !empty($success_message)): ?>
                    // Show success notification
                    if (window.CustomNotification) {
                        window.CustomNotification.show(
                            "<?php echo $success_message; ?>",
                            'success',
                            5000
                        );
                    } else {
                        // Fallback if CustomNotification is not loaded
                        alert("<?php echo $success_message; ?>");
                    }
                <?php endif; ?>
            });
        </script>

    <?php else: ?>
        <!-- NON-SUADMIN VIEW: Show only access restricted message -->
        <div class="access-restricted">
            <div class="access-restricted-box">
                <h2>Access Restricted</h2>
                <p>You do not have permission to access the User Management section.</p>
                <div class="current-role">
                    Your Current Role: <?php echo htmlspecialchars($_SESSION['Role'] ?? 'Not Set'); ?>
                </div>
                <p>Please contact your system Health Informatics Division if you need access to this feature.</p>
            </div>
        </div>
    <?php endif; ?>

</body>

</html>