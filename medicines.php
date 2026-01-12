<?php
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

require('Config/Config.php');

// Check if user role is SUADMIN - if not, disable functionality
$is_suadmin = isset($_SESSION['Role']) && $_SESSION['Role'] === 'SUADMIN';

// ---------- DATABASE CONNECTION ----------
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS);
if (!$conn) die('Could not connect to MySQL database: ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// ---------- FETCH ALL MEDICINES FOR LISTING ----------
$search = isset($_GET['dosearch']) ? mysqli_real_escape_string($conn, $_GET['dosearch']) : '';

// Number of rows per page
$limit = 15;

// Get current page from URL, default = 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Calculate starting record
$start = ($page - 1) * $limit;

// Build query with search if exists
if (!empty($search)) {
    $sql = "SELECT * FROM medicine 
            WHERE Medicine_name LIKE '%$search%' 
            OR Dose LIKE '%$search%'
            OR Form LIKE '%$search%'
            ORDER BY Medicine_name ASC 
            LIMIT $start, $limit";
    
    $countSql = "SELECT COUNT(*) AS total FROM medicine 
                 WHERE Medicine_name LIKE '%$search%'
                 OR Dose LIKE '%$search%'
                 OR Form LIKE '%$search%'";
} else {
    $sql = "SELECT * FROM medicine 
            ORDER BY Medicine_name ASC 
            LIMIT $start, $limit";
    
    $countSql = "SELECT COUNT(*) AS total FROM medicine";
}

$result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$countResult = mysqli_query($conn, $countSql) or die(mysqli_error($conn));
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $limit);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Medicine Management</title>
    <link rel="stylesheet" type="text/css" href="CSS/medicine.css">
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

        <a href="patiententry.php">
            Patient Records
        </a>
        <a href="bulk_print.php">
            Bulk Print
        </a>

        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <a href="Doctors.php">
                Doctors
            </a> <?php endif; ?>
        <a href="Medicines.php" style="background-color: whitesmoke; padding: 8px 12px; border-radius: 0px; display: inline-block; margin: 4px 0; text-decoration: none; color: #263F73; font-weight: bold;">
            Medicines</a>

         <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>    
            <a href="user_management.php">
                User Management
            </a> <?php endif; ?>
      

        

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

    <h1 align='center'> <img src="img/medicine_header.png" alt="medicine_icon" class="logo">Medicine</h1>

    <?php
    // Display success/error messages from medicine_transact.php
    if (isset($_GET['msg'])) {
        $msg = urldecode($_GET['msg']);
        echo "<div style='text-align:center; margin:70px 250px 10px 250px; width: calc(100% - 500px);'>";
        echo "<div style='color:green; background-color:#e6ffe6;
                        font-weight:bold; padding:10px; border-radius:6px;
                        border: 1px solid #ccffcc;'>
                $msg
              </div>";
        echo "</div>";
    }
    
    if (isset($_GET['error'])) {
        $error = urldecode($_GET['error']);
        echo "<div style='text-align:center; margin:70px 250px 10px 250px; width: calc(100% - 500px);'>";
        echo "<div style='color:#b30000; background-color:#ffe6e6;
                        font-weight:bold; padding:10px; border-radius:6px;
                        border: 1px solid #ffcccc;'>
                $error
              </div>";
        echo "</div>";
    }

    // Count total medicines
    $count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM medicine");
    $count_result = mysqli_fetch_assoc($count_query);

    if ($count_result) {
        echo "<div class='blink-text stats-box' style='margin: 60px 250px 20px 250px; width: calc(50% - 500px);'>";
        echo "Medicines Registered<br><span class='stats-number'>" . $count_result['total'] . "</span>";
        echo "</div>";
    }
    ?>
    
    <hr style="margin: 20px 250px; border: 1px solid #ccc; width: calc(100% - 500px);">
    
    <style>
        .blink-text {
            animation: blinker 1.5s ease-in-out 1;
        }

        @keyframes blinker {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0;
            }
        }
        
        .disabled-btn {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
        }
        
        .access-warning {
            text-align: center;
            margin: 10px 250px;
            padding: 10px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            color: #856404;
            font-weight: bold;
            width: calc(100% - 500px);
        }
    </style>

    <?php if (!$is_suadmin): ?>
    <div class="access-warning">
         You are viewing in READ-ONLY mode. Only SUADMIN users can add or edit medicines.
    </div>
    <?php endif; ?>

    <!-- Search container with Enter Search bar, Add New Medicine button, and Search button -->
    <div class="search-container" style="margin: 20px 250px; width: calc(100% - 500px);">
        <form method="GET" action="medicines.php" name="theform" style="display: flex; align-items: center; gap: 10px; width: 100%;">
            <input type="text" name="dosearch" placeholder="Enter Medicine Name, Dose, or Form" 
                   value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
            
            <input type="submit" value="Search">
            
            <button type="button" id="addMedicineBtn" class="add-medicine-btn <?php echo !$is_suadmin ? 'disabled-btn' : ''; ?>"
                    <?php echo !$is_suadmin ? 'disabled title="Only SUADMIN can add medicines"' : ''; ?>>
                Add New Medicine
            </button>
        </form>
    </div>

    <?php
    if (mysqli_num_rows($result) > 0) {
        echo "<div class='table-container' style='margin: 20px 250px; width: calc(110% - 500px);'>";
        echo "<table class='medicine-table' style='width: 100%; margin: 0 auto;'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th style='width: 5%; min-width: 50px; text-align: center;'>No.</th>";
        echo "<th style='width: 40%; min-width: 250px; text-align: left;'>Medicine Name</th>";
        echo "<th style='width: 25%; min-width: 150px; text-align: left;'>Dose</th>";
        echo "<th style='width: 25%; min-width: 120px; text-align: left;'>Form</th>";
        echo "<th style='width: 15%; min-width: 100px; text-align: center;'>Actions</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        $row_count = $start + 1;
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td style='text-align: center;'>" . $row_count . "</td>";
            echo "<td style='text-align: left;'><strong>" . strtoupper(htmlspecialchars($row['Medicine_name'])) . "</strong></td>";
            echo "<td style='text-align: left;'>" . strtoupper(htmlspecialchars($row['Dose'])) . "</td>";
            echo "<td style='text-align: left;'>" . strtoupper(htmlspecialchars($row['Form'])) . "</td>";
            echo "<td style='text-align: center;'>";
            if ($is_suadmin) {
                echo "<button type='button' class='btn-edit edit-medicine-btn' 
                       data-id='" . $row['Medicine_id'] . "'
                       data-name='" . htmlspecialchars($row['Medicine_name']) . "'
                       data-dose='" . htmlspecialchars($row['Dose']) . "'
                       data-form='" . htmlspecialchars($row['Form']) . "'>
                    Edit
                </button>";
            } else {
                echo "<button type='button' class='btn-edit disabled-btn' disabled title='Only SUADMIN can edit medicines'>
                    Edit
                </button>";
            }
            echo "</td>";
            echo "</tr>";
            $row_count++;
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    } else {
        echo "<div style='text-align:center; margin:20px 250px; width: calc(100% - 500px);'>";
        echo "<div style='color:#b30000; background-color:#ffe6e6;
                        font-weight:bold; padding:15px; border-radius:6px;
                        border: 1px solid #ffcccc;'>";
        if (!empty($search)) {
            echo "No medicines found for: <strong>" . htmlspecialchars($search) . "</strong>";
        } else {
            echo "No Medicine Found!";
        }
        echo "</div>";
        echo "</div>";
    }

    // PAGINATION DISPLAY: e.g. "Page 1/2"
    if ($totalPages > 1) {
        echo "<div style='text-align:center; margin:15px 250px 5px 250px; font-weight:bold; color: #263F73; width: calc(100% - 500px);'>";
        echo "Page $page of $totalPages";
        echo "</div>";

        // PAGINATION LINKS
        echo "<div style='text-align:center; margin:10px 250px 30px 250px; width: calc(100% - 500px);'>";

        if ($page > 1) {
            $prev_link = "?page=" . ($page - 1);
            if (!empty($search)) $prev_link .= "&dosearch=" . urlencode($search);
            
            echo "<a href='$prev_link' 
                  style='padding:8px 20px; margin-right:10px; 
                         background:#DAA520; color:white; 
                         text-decoration:none; border-radius:5px;
                         font-weight:bold; transition: background-color 0.2s;'>
                  Previous
                </a>";
        }

        if ($page < $totalPages) {
            $next_link = "?page=" . ($page + 1);
            if (!empty($search)) $next_link .= "&dosearch=" . urlencode($search);
            
            echo "<a href='$next_link' 
                  style='padding:8px 20px; 
                         background:#28A745; color:white; 
                         text-decoration:none; border-radius:5px;
                         font-weight:bold; transition: background-color 0.2s;'>
                  Next
                </a>";
        }
        
        // Clear search button if there's a search term
        if (!empty($search)) {
            echo "<a href='medicines.php' 
                  style='padding:8px 20px; margin-left:10px;
                         background:#6C757D; color:white; 
                         text-decoration:none; border-radius:5px;
                         font-weight:bold; transition: background-color 0.2s;'>
                  Clear Search
                </a>";
        }
        
        echo "</div>";
    }
    ?>

    <!-- ADD MEDICINE MODAL POPUP -->
    <div id="addMedicineModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeAddModalBtn">&times;</span>
            <div class="modal-header">
                <h2 style="margin: 0; color: white;">Add New Medicine</h2>
            </div>
            <form method="POST" action="medicine_transact.php" id="addMedicineForm">
                <input type="hidden" name="action" value="Add Medicine">
                <input type="hidden" name="redirect" value="medicines.php"> <!-- Updated to medicines.php -->
                
                <div class="form-group">
                    <label>Medicine Name:<span style="color: #dc3545; font-weight: bold;">*</span></label>
                    <input type="text" name="Medicine_name" required class="auto-uppercase"
                           oninput="this.value = this.value.toUpperCase()">
                </div>
                
                <div class="form-group">
                    <label>Dose:<span style="color: #dc3545; font-weight: bold;">*</span></label>
                    <input type="text" name="Dose" required class="auto-uppercase"
                           oninput="this.value = this.value.toUpperCase()"
                           placeholder="e.g., 500MG, 250MG/5ML">
                </div>
                
                <div class="form-group">
                    <label>Form:<span style="color: #dc3545; font-weight: bold;">*</span></label>
                    <input type="text" name="Form" required class="auto-uppercase"
                           oninput="this.value = this.value.toUpperCase()"
                           placeholder="e.g., TABLET, CAPSULE, SYRUP">
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn btn-primary">Add Medicine</button>
                    <button type="button" id="cancelAddBtn" class="btn btn-danger">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MEDICINE MODAL POPUP -->
    <div id="editMedicineModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeEditModalBtn">&times;</span>
            <div class="modal-header">
                <h2 style="margin: 0; color: white;">Edit Medicine Information</h2>
            </div>
            <form method="POST" action="medicine_transact.php" id="editMedicineForm">
                <input type="hidden" name="action" value="Update Medicine">
                <input type="hidden" name="Medicine_id" id="editMedicineId">
                <input type="hidden" name="redirect" value="medicines.php"> <!-- Updated to medicines.php -->
                
                <div class="form-group">
                    <label>Medicine Name:<span style="color: #dc3545; font-weight: bold;">*</span></label>
                    <input type="text" id="editMedicineName" name="Medicine_name" required class="auto-uppercase"
                           oninput="this.value = this.value.toUpperCase()">
                </div>
                
                <div class="form-group">
                    <label>Dose:<span style="color: #dc3545; font-weight: bold;">*</span></label>
                    <input type="text" id="editDose" name="Dose" required class="auto-uppercase"
                           oninput="this.value = this.value.toUpperCase()">
                </div>
                
                <div class="form-group">
                    <label>Form:<span style="color: #dc3545; font-weight: bold;">*</span></label>
                    <input type="text" id="editForm" name="Form" required class="auto-uppercase"
                           oninput="this.value = this.value.toUpperCase()">
                </div>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" id="cancelEditBtn" class="btn btn-danger">Cancel</button>
                </div>
            </form>
        </div>
    </div>

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

    <style>
        @keyframes spin {
            1% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
    </style>

<!-- JAVASCRIPT FOR MODAL AND LOADER -->
<script>
    // Immediately hide loader when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Hide loader immediately
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'none';
        }
        
        // Check if user is SUADMIN
        const isSuadmin = <?php echo $is_suadmin ? 'true' : 'false'; ?>;
        
        // Modal functionality
        const addMedicineBtn = document.getElementById('addMedicineBtn');
        const addModal = document.getElementById('addMedicineModal');
        const editModal = document.getElementById('editMedicineModal');
        const searchForm = document.querySelector('form[name="theform"]');
        
        // Close buttons
        const closeAddModalBtn = document.getElementById('closeAddModalBtn');
        const closeEditModalBtn = document.getElementById('closeEditModalBtn');
        const cancelAddBtn = document.getElementById('cancelAddBtn');
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        
        // ========== FIX: Check if page has an error/success message ==========
        // If there's an error or success message, ensure loader is hidden
        const hasMessage = document.querySelector('[style*="margin:70px 250px"], [style*="color:green"], [style*="color:#b30000"]');
        if (hasMessage && loader) {
            loader.style.display = 'none';
        }
        
        // Show add modal when Add New Medicine button is clicked
        if (addMedicineBtn && isSuadmin) {
            addMedicineBtn.addEventListener('click', function() {
                addModal.style.display = 'block';
                // Clear form when opening modal
                document.getElementById('addMedicineForm').reset();
            });
        } else if (addMedicineBtn && !isSuadmin) {
            addMedicineBtn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Only SUADMIN users can add new medicines.');
            });
        }
        
        // Show edit modal when Edit buttons are clicked
        const editButtons = document.querySelectorAll('.edit-medicine-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (!isSuadmin) {
                    alert('Only SUADMIN users can edit medicines.');
                    return;
                }
                
                const medicineId = this.getAttribute('data-id');
                const medicineName = this.getAttribute('data-name');
                const dose = this.getAttribute('data-dose');
                const form = this.getAttribute('data-form');
                
                // Fill the edit form with data
                document.getElementById('editMedicineId').value = medicineId;
                document.getElementById('editMedicineName').value = medicineName.toUpperCase();
                document.getElementById('editDose').value = dose.toUpperCase();
                document.getElementById('editForm').value = form.toUpperCase();
                
                // Show edit modal
                editModal.style.display = 'block';
            });
        });
        
        // Close add modal
        if (closeAddModalBtn) {
            closeAddModalBtn.addEventListener('click', function() {
                addModal.style.display = 'none';
            });
        }
        
        // Close edit modal
        if (closeEditModalBtn) {
            closeEditModalBtn.addEventListener('click', function() {
                editModal.style.display = 'none';
            });
        }
        
        // Cancel add button
        if (cancelAddBtn) {
            cancelAddBtn.addEventListener('click', function() {
                addModal.style.display = 'none';
            });
        }
        
        // Cancel edit button
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', function() {
                editModal.style.display = 'none';
            });
        }
        
        // Close modal when clicking outside the modal
        window.addEventListener('click', function(event) {
            if (event.target === addModal) {
                addModal.style.display = 'none';
            }
            if (event.target === editModal) {
                editModal.style.display = 'none';
            }
        });
        
        // Form submission for adding medicine
        const addMedicineForm = document.getElementById('addMedicineForm');
        if (addMedicineForm) {
            addMedicineForm.addEventListener('submit', function(e) {
                if (!isSuadmin) {
                    e.preventDefault();
                    alert('Only SUADMIN users can add medicines.');
                    return false;
                }
                
                // Validate form
                const medicineName = addMedicineForm.querySelector('input[name="Medicine_name"]').value.trim();
                const dose = addMedicineForm.querySelector('input[name="Dose"]').value.trim();
                const form = addMedicineForm.querySelector('input[name="Form"]').value.trim();
                
                if (!medicineName || !dose || !form) {
                    e.preventDefault();
                    alert('Please fill in all fields!');
                    return false;
                }
                
                // Show loader when form is submitted
                const loader = document.getElementById('loader');
                if (loader) {
                    loader.style.display = 'block';
                }
                addModal.style.display = 'none';
            });
        }
        
        // Form submission for editing medicine
        const editMedicineForm = document.getElementById('editMedicineForm');
        if (editMedicineForm) {
            editMedicineForm.addEventListener('submit', function(e) {
                if (!isSuadmin) {
                    e.preventDefault();
                    alert('Only SUADMIN users can edit medicines.');
                    return false;
                }
                
                // Validate form
                const medicineName = document.getElementById('editMedicineName').value.trim();
                const dose = document.getElementById('editDose').value.trim();
                const form = document.getElementById('editForm').value.trim();
                
                if (!medicineName || !dose || !form) {
                    e.preventDefault();
                    alert('Please fill in all fields!');
                    return false;
                }
                
                // Show loader when form is submitted
                const loader = document.getElementById('loader');
                if (loader) {
                    loader.style.display = 'block';
                }
                editModal.style.display = 'none';
            });
        }
        
        // Show loader when search form is submitted
        if (searchForm) {
            searchForm.addEventListener('submit', function() {
                const loader = document.getElementById('loader');
                if (loader) {
                    loader.style.display = 'block';
                }
            });
        }
        
        // Auto-uppercase all text inputs in modals
        const textInputs = document.querySelectorAll('.auto-uppercase');
        textInputs.forEach(input => {
            // Convert existing value to uppercase
            if (input.value) {
                input.value = input.value.toUpperCase();
            }
            
            // Convert to uppercase on paste
            input.addEventListener('paste', function(e) {
                setTimeout(() => {
                    this.value = this.value.toUpperCase();
                }, 0);
            });
        });
    });
    
    // ========== FIX: Global window load event ==========
    // Hide loader when page fully loads (including images, etc.)
    window.addEventListener('load', function() {
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'none';
        }
    });
    
    // ========== FIX: Hide loader when browser back/forward buttons are used ==========
    window.addEventListener('pageshow', function(event) {
        // If page is loaded from cache (back/forward navigation)
        if (event.persisted) {
            const loader = document.getElementById('loader');
            if (loader) {
                loader.style.display = 'none';
            }
        }
    });
</script>
</body>
</html>