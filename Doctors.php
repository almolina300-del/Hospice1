<?php
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

// Check if user is SUADMIN
$is_suadmin = isset($_SESSION['Role']) && $_SESSION['Role'] === 'SUADMIN';

require('Config/Config.php');

$bg_doctors = 'F2F2FF';

// Order handling with direction tracking
$ord_doctors = '';
$dir = 'asc'; // default direction
if (isset($_GET['od'])) {
    $ord_doctors = $_GET['od'];

    // Check if there's a direction parameter
    if (isset($_GET['dir'])) {
        $dir = ($_GET['dir'] == 'desc') ? 'desc' : 'asc';
    } else {
        // Default direction
        $dir = 'asc';
    }
};

if (is_numeric($ord_doctors)) {
    $ord_doctors = round(min(max($ord_doctors, 1), 4));
} else {
    $ord_doctors = 1;
}

// Define order clauses with both ASC and DESC options
$order_doctors = array(
    1 => array('asc' => 'License_number ASC', 'desc' => 'License_number DESC'),
    2 => array('asc' => 'Last_name ASC', 'desc' => 'Last_name DESC'),
    3 => array('asc' => 'First_name ASC', 'desc' => 'First_name DESC'),
    4 => array('asc' => 'is_active ASC', 'desc' => 'is_active DESC') // Status: 0 (Inactive) to 1 (Active)
);

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());

mysqli_select_db($conn, SQL_DB);

// ---------- PAGINATION SETUP ----------
$search = isset($_POST['dosearch']) ? mysqli_real_escape_string($conn, $_POST['dosearch']) : '';

// Number of rows per page
$limit = 20;

// Get current page from URL, default = 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Calculate starting record
$start = ($page - 1) * $limit;

// Function to get doctor data for editing
function getDoctorData($conn, $license_number)
{
    $sql = "SELECT * FROM doctors WHERE License_number = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $license_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

// Check if we're fetching doctor data for edit modal
if (isset($_GET['get_doctor']) && !empty($_GET['get_doctor'])) {
    $license_number = mysqli_real_escape_string($conn, $_GET['get_doctor']);
    $doctor_data = getDoctorData($conn, $license_number);

    if ($doctor_data) {
        echo json_encode($doctor_data);
    } else {
        echo json_encode(['error' => 'Doctor not found']);
    }
    exit();
}

$table_doctors = "<table align='center'>
    <tr>
    <td align='center' 
      style='color:#b30000; background-color:#ffe6e6; 
             font-weight:bold; padding:10px; border-radius:6px;'>
    No Doctors Found!
    </td>
        </tr>
        </table>";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Hospice - Doctors</title>
    <link rel="stylesheet" type="text/css" href="CSS/doctors.css">
    <style>
        .disabled-btn {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
        }

        .access-warning {
            text-align: center;
            margin: 10px 300px;
            padding: 10px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            color: #856404;
            font-weight: bold;
        }

        .sort-indicator {
            margin-left: 5px;
            font-size: 12px;
        }

        .sort-asc:after {
            content: " ↑";
        }

        .sort-desc:after {
            content: " ↓";
        }

        th a {
            display: inline-block;
            padding: 5px;
            transition: background-color 0.3s;
        }

        th a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
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
        <a href="inactive_patient.php">
            Inactive Patients
        </a>
        <a href="bulk_print.php">
            Bulk Print
        </a>

        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <a href="Doctors.php" style="background-color: whitesmoke; padding: 8px 12px; border-radius: 0px; display: inline-block; margin: 4px 0; text-decoration: none; color: #263F73; font-weight: bold;">
                Doctors
            </a>
            <a href="Medicines.php">Medicines</a>
            <a href="user_management.php">
                User Management
            </a>
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

    <h1 align='center'>
        <img src="img/doctor_icon.png" alt="doctor_icon" class="logo">Doctors Management
    </h1>



    <?php
    // Count active doctors
    $count_active_query = mysqli_query($conn, "SELECT COUNT(*) AS active_doctors FROM doctors WHERE is_active = 1");
    $count_active_result = mysqli_fetch_assoc($count_active_query);

    // Count inactive doctors
    $count_inactive_query = mysqli_query($conn, "SELECT COUNT(*) AS inactive_doctors FROM doctors WHERE is_active = 0");
    $count_inactive_result = mysqli_fetch_assoc($count_inactive_query);

    // Count total doctors
    $count_total_query = mysqli_query($conn, "SELECT COUNT(*) AS total_doctors FROM doctors");
    $count_total_result = mysqli_fetch_assoc($count_total_query);

    if ($count_total_result) {
        echo "<div style='display:flex; align-items:center; gap:20px; margin-bottom:20px; justify-content:center; flex-wrap:wrap;'>";

        // Total Doctors box
        echo "<div class='blink-text' style='background-color:white; 
                     padding:10px 20px; border-radius:8px; font-weight:bold; 
                     color:#263F73; width:180px; text-align:center;
                     box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
        echo "Total Doctors<br><span style='font-size:30px;'>" . $count_total_result['total_doctors'] . "</span>";
        echo "</div>";

        // Active Doctors box
        echo "<div style='background-color:white; 
                     padding:10px 20px; border-radius:8px; font-weight:bold; 
                     color:#3CB371; width:180px; text-align:center;
                     box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
        echo "Active Doctors<br><span style='font-size:30px;'>" . $count_active_result['active_doctors'] . "</span>";
        echo "</div>";

        // Inactive Doctors box
        echo "<div style='background-color:white; 
                     padding:10px 20px; border-radius:8px; font-weight:bold; 
                     color:#FF6B6B; width:180px; text-align:center;
                     box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>";
        echo "Inactive Doctors<br><span style='font-size:30px;'>" . $count_inactive_result['inactive_doctors'] . "</span>";
        echo "</div>";

        echo "</div>";
    }

    ?>

    <hr style="margin: 20px 250px; border: 1px solid #ccc; width: 80%;">
    <?php if (!$is_suadmin): ?>
        <div class="access-warning">
            You are viewing in READ-ONLY mode. Only SUADMIN can add or edit doctors.
        </div>
    <?php endif; ?>
    <!-- DOCTORS SECTION -->
    <div style="margin-left: 180px; margin-bottom: 40px;">

        <!-- Search container with Enter Search bar, Add New Doctor button, and Search button -->
        <div style="background-color: white; padding: 15px 20px; margin: 0 250px 20px 250px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #ddd;">
            <form method="post" action="Doctors.php" name="doctorsform" style="display: flex; align-items: center; gap: 10px;">
                <input type="text" name="dosearch" placeholder="Enter Lastname"
                    value="<?php echo htmlspecialchars($_POST['dosearch'] ?? ''); ?>"
                    style="flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px;">

                <input type="submit" name="action" value="Search"
                    style="padding: 8px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">

                <button type="button" id="openModalBtn" class="<?php echo !$is_suadmin ? 'disabled-btn' : ''; ?>"
                    <?php echo !$is_suadmin ? 'disabled title="Only SUADMIN can add doctors"' : ''; ?>
                    style="padding: 8px 20px; background-color: #263F73; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                    Add New Doctor
                </button>
            </form>
        </div>
<!-- Status Filter Buttons -->
<div style="text-align: center; margin: 0 250px 20px 250px; background-color: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #ddd;">
    <div style="display: inline-flex; align-items: center; gap: 10px;">
        <strong style="color: #263F73; white-space: nowrap;">Filter by Status:</strong>
        <div style="display: flex; gap: 5px;">
            <?php
            $current_filter = $_GET['status'] ?? 'all';
            ?>
            <a href="Doctors.php?status=all&od=<?php echo $ord_doctors; ?>&dir=<?php echo $dir; ?>&page=<?php echo $page; ?><?php echo !empty($search) ? '&dosearch=' . urlencode($search) : ''; ?>" 
               style="padding: 6px 15px; 
                      background-color: <?php echo $current_filter == 'all' ? '#263F73' : '#6c757d'; ?>; 
                      color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"
               onmouseover="if(this.style.backgroundColor!='#263F73')this.style.backgroundColor='#5a6268'"
               onmouseout="if(this.style.backgroundColor!='#263F73')this.style.backgroundColor='#6c757d'">
                All
            </a>
            <a href="Doctors.php?status=active&od=<?php echo $ord_doctors; ?>&dir=<?php echo $dir; ?>&page=<?php echo $page; ?><?php echo !empty($search) ? '&dosearch=' . urlencode($search) : ''; ?>" 
               style="padding: 6px 15px; 
                      background-color: <?php echo $current_filter == 'active' ? '#3CB371' : '#6c757d'; ?>; 
                      color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"
               onmouseover="if(this.style.backgroundColor!='#3CB371')this.style.backgroundColor='#5a6268'"
               onmouseout="if(this.style.backgroundColor!='#3CB371')this.style.backgroundColor='#6c757d'">
                Active Only
            </a>
            <a href="Doctors.php?status=inactive&od=<?php echo $ord_doctors; ?>&dir=<?php echo $dir; ?>&page=<?php echo $page; ?><?php echo !empty($search) ? '&dosearch=' . urlencode($search) : ''; ?>" 
               style="padding: 6px 15px; 
                      background-color: <?php echo $current_filter == 'inactive' ? '#FF6B6B' : '#6c757d'; ?>; 
                      color: white; text-decoration: none; border-radius: 4px; font-size: 14px;"
               onmouseover="if(this.style.backgroundColor!='#FF6B6B')this.style.backgroundColor='#5a6268'"
               onmouseout="if(this.style.backgroundColor!='#FF6B6B')this.style.backgroundColor='#6c757d'">
                Inactive Only
            </a>
        </div>
    </div>
</div>
        <!-- Modal Structure for Add Doctor -->
        <div id="addDoctorModal" class="modal">
            <div class="modal-content" style="width: 450px;">
                <span class="close-modal" id="closeModalBtn">&times;</span>
                <div class="modal-header">
                    <h2 style="margin: 0; color: white;">Add Doctor</h2>
                </div>
                <form method="post" action="transact/doctortransact.php" id="addDoctorForm">
                    <input type="hidden" name="a" value="Create Record">

                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">License Number:<span style="color: red;">*</span></label>
                        <input type="text" name="License_number" required
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc;">
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">Last Name:<span style="color: red;">*</span></label>
                        <input type="text" name="Last_name" required class="auto-uppercase"
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc; text-transform: uppercase;">
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">First Name:<span style="color: red;">*</span></label>
                        <input type="text" name="First_name" required class="auto-uppercase"
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc; text-transform: uppercase;">
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">Middle Name:</label>
                        <input type="text" name="Middle_name" class="auto-uppercase"
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc; text-transform: uppercase;">
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 20px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">PTR Number:<span style="color: red;">*</span></label>
                        <input type="text" name="Ptr_number" required
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc;">
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn-primary" style="flex: 1; padding: 10px;">Add Doctor</button>
                        <button type="button" id="cancelBtn" class="btn-danger" style="flex: 1; padding: 10px;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Structure for Edit Doctor -->
        <div id="editDoctorModal" class="modal">
            <div class="modal-content" style="width: 450px;">
                <span class="close-modal" id="closeEditModalBtn">&times;</span>
                <div class="modal-header">
                    <h2 style="margin: 0; color: white;">Edit Doctor Information</h2>
                </div>
                <form method="post" action="transact/doctortransact.php" id="editDoctorForm">
                    <input type="hidden" name="a" value="Update Record">
                    <input type="hidden" name="c" id="original_license">

                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">License Number:</label>
                        <input type="text" id="edit_license" name="License_number" readonly
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5;">
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">Last Name:</label>
                        <input type="text" id="edit_last_name" name="Last_name" required class="auto-uppercase"
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc; text-transform: uppercase;">
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">First Name:</label>
                        <input type="text" id="edit_first_name" name="First_name" required class="auto-uppercase"
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc; text-transform: uppercase;">
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 12px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">Middle Name:</label>
                        <input type="text" id="edit_middle_name" name="Middle_name" class="auto-uppercase"
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc; text-transform: uppercase;">
                    </div>

                    <div style="display: flex; align-items: center; margin-bottom: 20px;">
                        <label style="font-weight: bold; width: 140px; margin-right: 10px;">PTR Number:</label>
                        <input type="text" id="edit_ptr_number" name="Ptr_number" readonly
                            style="flex: 1; padding:8px; border-radius: 4px; border: 1px solid #ccc; background-color: #f5f5f5;">
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn-primary" style="flex: 1; padding: 10px;">Save Changes</button>
                        <button type="button" id="cancelEditBtn" class="btn-danger" style="flex: 1; padding: 10px;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            // Get modal elements for Add Doctor
            const addDoctorModal = document.getElementById('addDoctorModal');
            const openBtn = document.getElementById('openModalBtn');
            const closeBtn = document.getElementById('closeModalBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const addDoctorForm = document.getElementById('addDoctorForm');

            // Get modal elements for Edit Doctor
            const editDoctorModal = document.getElementById('editDoctorModal');
            const closeEditBtn = document.getElementById('closeEditModalBtn');
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            const editDoctorForm = document.getElementById('editDoctorForm');

            // Check if user is SUADMIN (passed from PHP)
            const isSuadmin = <?php echo $is_suadmin ? 'true' : 'false'; ?>;

            // Open Add Doctor modal
            openBtn.onclick = () => {
                if (!isSuadmin) {
                    alert('Only SUADMIN users can add new doctors.');
                    return;
                }
                addDoctorModal.style.display = 'flex';
                addDoctorForm.reset();
            }

            // Close Add Doctor modal
            closeBtn.onclick = () => {
                addDoctorModal.style.display = 'none';
            }

            cancelBtn.onclick = () => {
                addDoctorModal.style.display = 'none';
            }

            // Close Edit Doctor modal
            closeEditBtn.onclick = () => {
                editDoctorModal.style.display = 'none';
            }

            cancelEditBtn.onclick = () => {
                editDoctorModal.style.display = 'none';
            }

            // Form validation for Add Doctor
            addDoctorForm.onsubmit = function(e) {
                if (!isSuadmin) {
                    e.preventDefault();
                    alert('Only SUADMIN users can add doctors.');
                    return false;
                }

                const license = document.querySelector('input[name="License_number"]').value;
                const lastName = document.querySelector('input[name="Last_name"]').value;
                const firstName = document.querySelector('input[name="First_name"]').value;
                const ptr = document.querySelector('input[name="Ptr_number"]').value;

                if (!license || !lastName || !firstName || !ptr) {
                    alert('Please fill in all required fields.');
                    e.preventDefault();
                    return false;
                }
                return true;
            }

            // Form validation for Edit Doctor
            editDoctorForm.onsubmit = function(e) {
                if (!isSuadmin) {
                    e.preventDefault();
                    alert('Only SUADMIN users can edit doctors.');
                    return false;
                }

                const license = document.getElementById('edit_license').value;
                const lastName = document.getElementById('edit_last_name').value;
                const firstName = document.getElementById('edit_first_name').value;

                if (!license || !lastName || !firstName) {
                    alert('Please fill in all required fields.');
                    e.preventDefault();
                    return false;
                }
                return true;
            }

            // Function to load doctor data and open edit modal
            function loadDoctorData(licenseNumber) {
                if (!isSuadmin) {
                    alert('Only SUADMIN users can edit doctors.');
                    return;
                }

                // Show loading state
                const submitBtn = editDoctorForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = 'Loading...';
                submitBtn.disabled = true;

                // Fetch doctor data
                fetch(`Doctors.php?get_doctor=${encodeURIComponent(licenseNumber)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            editDoctorModal.style.display = 'none';
                            return;
                        }

                        // Populate form fields - Make sure this is setting the correct field
                        // The element with id="original_license" now has name="c"
                        document.getElementById('original_license').value = data.License_number;
                        document.getElementById('edit_license').value = data.License_number;
                        document.getElementById('edit_last_name').value = data.Last_name;
                        document.getElementById('edit_first_name').value = data.First_name;
                        document.getElementById('edit_middle_name').value = data.Middle_name || '';
                        document.getElementById('edit_ptr_number').value = data.Ptr_number || '';

                        // Show modal
                        editDoctorModal.style.display = 'flex';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to load doctor data. Please try again.');
                    })
                    .finally(() => {
                        // Restore button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
            }

            // Add click handler for edit buttons
            document.addEventListener('DOMContentLoaded', function() {
                // This will be used by dynamically created buttons
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('edit-doctor-btn')) {
                        e.preventDefault();
                        const licenseNumber = e.target.getAttribute('data-license');
                        loadDoctorData(licenseNumber);
                    }
                });

                // Auto-uppercase functionality for name fields
                const nameInputs = document.querySelectorAll('.auto-uppercase');
                nameInputs.forEach(input => {
                    // Convert existing value to uppercase
                    input.value = input.value.toUpperCase();

                    // Add event listener for new input
                    input.addEventListener('input', function() {
                        this.value = this.value.toUpperCase();
                    });

                    // Add event listener for paste
                    input.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                        this.value = pastedText.toUpperCase();
                    });
                });
            });
        </script>

        <?php
        // SEARCH FUNCTIONALITY FOR DOCTORS
        // Get status filter
$status_filter = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'active') {
        $status_filter = " AND is_active = 1";
    } elseif ($_GET['status'] == 'inactive') {
        $status_filter = " AND is_active = 0";
    }
}

// SEARCH FUNCTIONALITY FOR DOCTORS
if (!empty($search)) {
    $runsearch = mysqli_real_escape_string($conn, $search);
    $sql_doctors = "SELECT SQL_CALC_FOUND_ROWS * FROM doctors 
                    WHERE `Last_name` LIKE '%$runsearch%' 
                    $status_filter 
                    ORDER BY " . $order_doctors[$ord_doctors][$dir];
} else {
    $sql_doctors = "SELECT SQL_CALC_FOUND_ROWS * FROM doctors  
                    WHERE 1=1 $status_filter 
                    ORDER BY " . $order_doctors[$ord_doctors][$dir];
}

        // Apply limit for pagination (always show page 1 with limit)
        $sql_doctors .= " LIMIT $start, $limit";

        $result_doctors = mysqli_query($conn, $sql_doctors) or die(mysqli_error($conn));

        // Get total rows for pagination
        $totalResult = mysqli_query($conn, "SELECT FOUND_ROWS() AS total");
        $totalRows = mysqli_fetch_assoc($totalResult)['total'];
        $totalPages = ceil($totalRows / $limit);

        if (mysqli_num_rows($result_doctors) > 0) {
            echo "<div class='center-container'>";
            echo "<div class='table-container'>";
            echo "<table class='doctors-table'>";
            echo "<thead>";
            echo "<tr>";
            echo "<th>No.</th>";
            echo "<th>
        <a href='" . $_SERVER['PHP_SELF'] . "?od=1" . ($ord_doctors == 1 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "' 
           style='color: white; text-decoration: none;'>
           License Number
           " . ($ord_doctors == 1 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
      </th>";
            echo "<th>
        <a href='" . $_SERVER['PHP_SELF'] . "?od=2" . ($ord_doctors == 2 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "'
           style='color: white; text-decoration: none;'>
           Last name
           " . ($ord_doctors == 2 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
      </th>";
            echo "<th>
        <a href='" . $_SERVER['PHP_SELF'] . "?od=3" . ($ord_doctors == 3 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "' 
           style='color: white; text-decoration: none;'>
           First name
           " . ($ord_doctors == 3 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
      </th>";
            echo "<th>Middle name</th>";
            echo "<th>PTR Number</th>";
            echo "<th>
        <a href='" . $_SERVER['PHP_SELF'] . "?od=4" . ($ord_doctors == 4 && $dir == 'asc' ? "&dir=desc" : "&dir=asc") . "&page=" . $page . "&dosearch=" . urlencode($search) . "' 
           style='color: white; text-decoration: none;'>
           Status
           " . ($ord_doctors == 4 ? '<span class="sort-indicator">' . ($dir == 'asc' ? '↑' : '↓') . '</span>' : '') . "
        </a>
      </th>";
            echo "<th>Action</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";

            $row_count = $start + 1;
            while ($row = mysqli_fetch_assoc($result_doctors)) {
                $bg_color = ($row_count % 2 == 0) ? '#F2F2FF' : '#FFFFFF';

                echo "<tr style='background-color: " . $bg_color . ";'>";
                echo "<td>" . $row_count . "</td>";
                echo "<td>" . htmlspecialchars($row['License_number'] ?? '') . "</td>";
                echo "<td>
                        <div style='display: flex; align-items: center; justify-content: center; gap: 5px;'>
                            <img src='img/doctor_icon.png' alt='doctor' style='width: 16px; height: 16px;'>
                            " . htmlspecialchars(strtoupper($row['Last_name'] ?? '')) . "
                        </div>
                      </td>";
                echo "<td>" . htmlspecialchars(strtoupper($row['First_name'] ?? '')) . "</td>";
                echo "<td>" . htmlspecialchars(strtoupper($row['Middle_name'] ?? '')) . "</td>";
                echo "<td>" . htmlspecialchars($row['Ptr_number'] ?? '') . "</td>";
                // ADD THIS SECTION FOR STATUS:
                echo "<td>";
                if ($row['is_active'] == 1) {
                    echo "<span style='color: #3CB371; font-weight: bold;'>● Active</span>";
                } else {
                    echo "<span style='color: #FF6B6B; font-weight: bold;'>● Inactive</span>";
                }
                echo "</td>";
                echo "<td>";

                // Edit button - conditionally enabled
                if ($is_suadmin) {
                    echo "<a href='#' class='edit-doctor-btn' data-license='" . htmlspecialchars($row['License_number']) . "'
                           style='background-color:#007bff; color:white; padding:6px 5px; border-radius:3px; 
                           text-decoration:none; font-size:11px; margin-right:5px; cursor:pointer;'>
                           View/Edit</a>";
                } else {
                    echo "<button class='disabled-btn' disabled 
                           style='background-color:#007bff; color:white; padding:6px 5px; border-radius:3px; 
                           font-size:11px; margin-right:5px; border:none; cursor:not-allowed;'
                           title='Only SUADMIN can edit doctors'>
                           View/Edit</button>";
                }

                // Action buttons - conditionally enabled
                if ($is_suadmin) {
                    // Check doctor status
                    if ($row['is_active'] == 1) {
                        // Doctor is active - show Deactivate button
                        echo "<a href='transact/doctortransact.php?c=" . urlencode($row['License_number']) . "&a=deactivate' 
              class='btn-danger'
              style='padding:6px 5px; border-radius:3px; text-decoration:none; font-size:11px; 
                     background-color:#dc3545; color:white; display:inline-block; margin-left:5px;' 
              onclick=\"return confirm('Are you sure you want to deactivate this doctor?');\">
              Deactivate</a>";
                    } else {
                        // Doctor is inactive - show Activate button
                        echo "<a href='transact/doctortransact.php?c=" . urlencode($row['License_number']) . "&a=activate' 
              class='btn-success'
              style='padding:6px 8px; border-radius:3px; text-decoration:none; font-size:11px; 
                     background-color:#28a745; color:white; display:inline-block; margin-left:5px;' 
              onclick=\"return confirm('Are you sure you want to activate this doctor?');\">
              Activate</a>";
                    }
                } else {
                    // Non-SUADMIN users see disabled buttons
                    if ($row['is_active'] == 1) {
                        echo "<button class='disabled-btn' disabled 
               style='padding:6px 5px; border-radius:3px; font-size:11px; border:none; cursor:not-allowed;
                      background-color:#dc3545; color:white; margin-left:5px;'
               title='Only SUADMIN can deactivate doctors'>
               Deactivate</button>";
                    } else {
                        echo "<button class='disabled-btn' disabled 
               style='padding:6px 5px; border-radius:3px; font-size:11px; border:none; cursor:not-allowed;
                      background-color:#28a745; color:white; margin-left:5px;'
               title='Only SUADMIN can activate doctors'>
               Activate</button>";
                    }
                }

                echo "</td>";
                echo "</tr>";

                $row_count++;
            }

            echo "</tbody>";
            echo "</table>";
            echo "</div>";
            echo "</div>";

            // PAGINATION DISPLAY: e.g. "Page 1/2"
            // ALWAYS SHOW PAGE INFO EVEN IF ONLY ONE PAGE
            echo "<div class='page-info'>Page $page / $totalPages</div>";

            // PAGINATION LINKS - Only show if more than 1 page
            if ($totalPages > 1) {
                echo "<div class='pagination'>";
if ($page > 1) {
    $prev_link = "?page=" . ($page - 1) . "&od=" . $ord_doctors . "&dir=" . $dir;
    if (!empty($search)) $prev_link .= "&dosearch=" . urlencode($search);

    echo "<a href='$prev_link' class='pagination-btn prev'>Previous</a>";
}

if ($page < $totalPages) {
    $next_link = "?page=" . ($page + 1) . "&od=" . $ord_doctors . "&dir=" . $dir;
    if (!empty($search)) $next_link .= "&dosearch=" . urlencode($search);

    echo "<a href='$next_link' class='pagination-btn next'>Next</a>";
}

                echo "</div>";
            }
        } else {
            echo "<div class='center-container'>";
            echo $table_doctors;
            echo "</div>";
        }

        mysqli_close($conn);
        ?>
    </div>
</body>

</html>