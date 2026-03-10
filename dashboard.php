<?php
// dashboard.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

require('Config/Config.php');

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS)
    or die('Could not connect to MySQL database. ' . mysqli_connect_error());

mysqli_select_db($conn, SQL_DB);
?>

<html>
<head>
    <title>Employees Clinic - Dashboard</title>
    <link rel="stylesheet" type="text/css" href="CSS/style.css">
    <style>
        /* Loader styles */
        #dashboard-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            z-index: 99999;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s;
        }
        
        .loader-content {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .loader-spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #263F73;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            animation: spin 1s linear infinite;
        }
        
        .loader-text {
            color: #263F73;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .loader-subtext {
            color: #666;
            font-size: 14px;
        }
        
        .loader-progress {
            width: 300px;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            margin: 20px auto 0;
            overflow: hidden;
        }
        
        .loader-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #263F73, #4a6bb5);
            width: 0%;
            transition: width 0.3s;
            border-radius: 3px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Hide main content initially */
        #main-content {
            opacity: 0;
            transition: opacity 0.5s;
        }
        
        #main-content.visible {
            opacity: 1;
        }
        
        .dashboard-container {
            margin-left: 250px;
            padding: 20px;
        }
        
        .dashboard-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-title {
            color: #263F73;
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        
        .date-display {
            color: #666;
            font-size: 16px;
            background: #f5f5f5;
            padding: 8px 15px;
            border-radius: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card.total {
            background: linear-gradient(135deg, #263F73, #1a2b4d);
            color: white;
        }
        
        .stat-card.total .stat-icon {
            color: rgba(255,255,255,0.3);
        }
        
        .stat-icon {
            font-size: 48px;
            float: right;
            color: #263F73;
            opacity: 0.2;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
        }
        
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .search-container {
            flex: 1;
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #263F73;
        }
        
        .search-btn {
            padding: 12px 25px;
            background: #263F73;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .search-btn:hover {
            background: #1a2b4d;
        }
        
        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dept-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid #263F73;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .dept-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(38,63,115,0.2);
        }
        
        .dept-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dept-name {
            font-size: 18px;
            font-weight: bold;
            color: #263F73;
            text-transform: uppercase;
        }
        
        .dept-count {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }
        
        .no-dept-card {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .no-dept-card .dept-name {
            color: #856404;
        }
        
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 10px;
            color: #666;
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                margin-left: 0;
                padding: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .department-grid {
                grid-template-columns: 1fr;
            }
            
            .search-section {
                flex-direction: column;
            }
            
            .search-container {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- Loader -->
<div id="dashboard-loader">
    <div class="loader-content">
        <div class="loader-spinner"></div>
        <div class="loader-text">Loading </div>
        <div class="loader-progress">
            <div class="loader-progress-bar" id="progressBar"></div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div id="main-content">
    <div class="sidebar">
        <?php if (isset($_SESSION['First_name'])): ?>
            <div class="welcome-user" style="color: white; text-align: center; padding: 15px; margin-bottom: 10px; background: rgba(255,255,255,0.1); border-radius: 5px;">
                <div style="font-size: 25px; color: white; font-weight: bold; margin-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 5px;">
                    Employees Clinic<br>
                    <div style="margin-top: 5px; font-size: 18px; color: rgba(255,255,255,0.8);">
                        Dashboard
                    </div>
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

        <a href="dashboard.php" style="background-color: whitesmoke; padding: 8px 12px; border-radius: 0px; display: inline-block; margin: 4px 0; text-decoration: none; color: #263F73; font-weight: bold;">
            Dashboard
        </a>
        <a href="patiententry.php">Patient Records</a>
        <a href="inactive_patient.php">Inactive Patients</a>
        <a href="bulk_print.php">Bulk Print</a>

        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <a href="Doctors.php">Doctors</a>
        <?php endif; ?>
        <a href="Medicines.php">Medicines</a>

        <?php if (isset($_SESSION['Role']) && strtoupper($_SESSION['Role']) == 'SUADMIN'): ?>
            <a href="user_management.php">User Management</a>
        <?php endif; ?>

        <div class="spacer"></div>
        <div class="logout-container">
            <script>
                function confirmLogout() {
                    return confirm("Are you sure you want to log out?");
                }
            </script>
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

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Dashboard Overview</h1>
            <div class="date-display" id="liveDateTime">
                <?php
                date_default_timezone_set('Asia/Manila');
                echo date('F j, Y') . ' | ' . date('h:i:s A');
                ?>
            </div>
        </div>

        <?php
        // Get total active patients
        $total_query = "SELECT COUNT(*) as total FROM patient_details WHERE is_active = 1";
        $total_result = mysqli_query($conn, $total_query);
        $total_active = mysqli_fetch_assoc($total_result)['total'];
        
        // Get patients without department
        $no_dept_query = "SELECT COUNT(*) as count FROM patient_details WHERE is_active = 1 AND (Department IS NULL OR Department = '')";
        $no_dept_result = mysqli_query($conn, $no_dept_query);
        $no_dept_count = mysqli_fetch_assoc($no_dept_result)['count'];
        
        // Get total departments
        $dept_count_query = "SELECT COUNT(DISTINCT Department) as count FROM patient_details WHERE is_active = 1 AND Department IS NOT NULL AND Department != ''";
        $dept_count_result = mysqli_query($conn, $dept_count_query);
        $total_departments = mysqli_fetch_assoc($dept_count_result)['count'];
        ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo number_format($total_active); ?></div>
                <div class="stat-label">Total Active Patients</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-number"><?php echo number_format($total_departments); ?></div>
                <div class="stat-label">Total Departments</div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="search-container">
                <input type="text" id="departmentSearch" class="search-input" placeholder="Search departments..." autocomplete="off">
                <button class="search-btn" onclick="searchDepartments()">Search</button>
            </div>
        </div>

        <!-- Department Grid -->
        <h2 style="color: #263F73; margin-bottom: 20px;">Department</h2>
        
        <div class="department-grid" id="departmentGrid">
            <?php
            // Get all department counts
            $dept_query = "SELECT 
                Department,
                COUNT(*) as patient_count
            FROM patient_details 
            WHERE is_active = 1 
                AND Department IS NOT NULL 
                AND Department != ''
            GROUP BY Department 
            ORDER BY Department ASC";
            
            $dept_result = mysqli_query($conn, $dept_query);
            $departments = [];
            
            if (mysqli_num_rows($dept_result) > 0) {
                while ($dept = mysqli_fetch_assoc($dept_result)) {
                    $departments[] = $dept;
                    $dept_name = strtoupper($dept['Department']);
                    $count = $dept['patient_count'];
                    ?>
                    <div class="dept-card" data-department="<?php echo htmlspecialchars(strtolower($dept_name)); ?>" onclick="window.location='patiententry.php?department_filter=<?php echo urlencode($dept_name); ?>'">
                        <div class="dept-header">
                            <span class="dept-name"><?php echo htmlspecialchars($dept_name); ?></span>
                            <span class="dept-count"><?php echo number_format($count); ?></span>
                        </div>
                    </div>
                    <?php
                }
            }
            
            // Add "No Department" card
            if ($no_dept_count > 0) {
                ?>
                <div class="dept-card no-dept-card" data-department="no department" onclick="window.location='patiententry.php?department_filter='">
                    <div class="dept-header">
                        <span class="dept-name">NO DEPARTMENT SPECIFIED</span>
                        <span class="dept-count"><?php echo number_format($no_dept_count); ?></span>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<script>
    // Loader with 5-second display
    document.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('dashboard-loader');
        const mainContent = document.getElementById('main-content');
        const progressBar = document.getElementById('progressBar');
        
        let progress = 0;
        const totalTime = 1000; // 2 seconds
        const interval = 50; // Update every 50ms
        const increment = (interval / totalTime) * 100;
        
        // Update progress bar
        const progressInterval = setInterval(function() {
            progress += increment;
            if (progress <= 100) {
                progressBar.style.width = progress + '%';
            }
        }, interval);
        
        // Hide loader after 5 seconds
        setTimeout(function() {
            clearInterval(progressInterval);
            progressBar.style.width = '100%';
            
            setTimeout(function() {
                loader.style.opacity = '0';
                setTimeout(function() {
                    loader.style.display = 'none';
                    mainContent.classList.add('visible');
                }, 500);
            }, 300);
        }, totalTime);
    });

    // Update live datetime
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

    // Search functionality
    function searchDepartments() {
        const searchTerm = document.getElementById('departmentSearch').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.dept-card');
        let hasResults = false;

        cards.forEach(card => {
            const department = card.getAttribute('data-department');
            if (department.includes(searchTerm) || searchTerm === '') {
                card.style.display = 'block';
                hasResults = true;
            } else {
                card.style.display = 'none';
            }
        });

        // Show/hide no results message
        const grid = document.getElementById('departmentGrid');
        let noResultsMsg = document.getElementById('noResultsMessage');
        
        if (!hasResults && searchTerm !== '') {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.id = 'noResultsMessage';
                noResultsMsg.className = 'no-results';
                noResultsMsg.innerHTML = 'No departments found matching "' + searchTerm + '"';
                grid.appendChild(noResultsMsg);
            }
        } else {
            if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }
    }

    // Search on input change
    document.getElementById('departmentSearch').addEventListener('input', searchDepartments);

    // Search on Enter key
    document.getElementById('departmentSearch').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchDepartments();
        }
    });
</script>

</body>
</html>