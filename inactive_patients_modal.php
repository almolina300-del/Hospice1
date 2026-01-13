    <?php
session_start();
require('Config/Config.php');

// Check if user is SUADMIN
if (!isset($_SESSION['Role']) || strtoupper($_SESSION['Role']) != 'SUADMIN') {
    die('<div style="text-align:center; padding:20px; color:red;">Access Denied</div>');
}

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS) or die('Could not connect to database.');
mysqli_select_db($conn, SQL_DB);

// Number of rows per page
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Get search term if any
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query
$where = "WHERE is_active = 0";
if (!empty($search)) {
    $where .= " AND (Last_name LIKE '%$search%' OR First_name LIKE '%$search%' OR Middle_name LIKE '%$search%')";
}

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM patient_details $where";
$count_result = mysqli_query($conn, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// Get records
$sql = "SELECT * FROM patient_details $where ORDER BY Last_name ASC LIMIT $start, $limit";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #dc3545;
        }
        
        .modal-title {
            font-size: 24px;
            color: #dc3545;
            font-weight: bold;
        }
        
        .close-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .close-btn:hover {
            background: #c82333;
        }
        
        .search-box {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        
        .search-box button {
            padding: 8px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .patient-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .patient-table th {
            background: #dc3545;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }
        
        .patient-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        .patient-table tr:hover {
            background: #f8f9fa;
        }
        
        .restore-btn {
            background: #ffc107;
            color: black;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        .restore-btn:hover {
            background: #e0a800;
        }
        
        .view-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }
        
        .view-btn:hover {
            background: #0056b3;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 15px;
        }
        
        .pagination a, .pagination span {
            padding: 5px 10px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 3px;
            font-size: 13px;
        }
        
        .pagination a:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination .current {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .no-records {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .total-count {
            text-align: right;
            color: #666;
            font-size: 12px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="modal-header">
        <div class="modal-title">
            Inactive Patients
            <span style="font-size: 14px; color: #666; margin-left: 10px;">
                Total: <?php echo $total_rows; ?>
            </span>
        </div>
        <button class="close-btn" onclick="closeInactiveModal()">Close</button>
    </div>
    
    <!-- Search Form -->
    <form method="get" action="" class="search-box" onsubmit="searchInactivePatients(this); return false;">
        <input type="text" name="search" placeholder="Search by name..." 
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
        <?php if (!empty($search)): ?>
            <button type="button" onclick="clearSearch()" style="background: #6c757d;">Clear</button>
        <?php endif; ?>
    </form>
    
<?php if ($total_rows > 0): ?>
<div style="max-height: 500px; overflow-y: auto; overflow-x: hidden; border: 0px solid #ddd; border-radius: 5px;">
    <table class="patient-table" style="width: 90%; table-layout: fixed; margin-left: 20px;">
        <thead>
            <tr>
                <th style="width: 20%; padding: 12px 15px; text-align: left;">Last Name</th>
                <th style="width: 20%; padding: 12px 15px; text-align: left;">First Name</th>
                <th style="width: 15%; padding: 12px 15px; text-align: left;">Middle Name</th>
                <th style="width: 25%; padding: 12px 15px; text-align: left;">Barangay</th>
                <th style="width: 15%; padding: 12px 15px; text-align: left;">Birthday</th>
                <th style="width: 15%; padding: 12px 15px; text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td style="width: 20%; padding: 10px 15px; text-align: left; word-wrap: break-word;"><?php echo strtoupper($row['Last_name']); ?></td>
                    <td style="width: 20%; padding: 10px 15px; text-align: left; word-wrap: break-word;"><?php echo strtoupper($row['First_name']); ?></td>
                    <td style="width: 15%; padding: 10px 15px; text-align: left; word-wrap: break-word;"><?php echo strtoupper($row['Middle_name']); ?></td>
                    <td style="width: 25%; padding: 10px 15px; text-align: left; word-wrap: break-word;"><?php echo strtoupper($row['Barangay']); ?></td>
                    <td style="width: 15%; padding: 10px 15px; text-align: left;"><?php echo $row['Birthday']; ?></td>
                    <td style="width: 15%; padding: 10px 15px; text-align: center;">
                        <a href="transact/inactive_transact.php?c=<?php echo $row['Patient_id']; ?>&a=Restore Record"
                           class="restore-btn" 
                           onclick="return confirm('Restore this patient to active?')">Restore</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 5px; margin-top: 20px;">
            <?php if ($page > 1): ?>
                <a href="#" onclick="changePage(<?php echo $page - 1; ?>)" style="padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; font-size: 14px;">Previous</a>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($total_pages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i == $page): ?>
                    <span style="padding: 8px 15px; background: #dc3545; color: white; border: 1px solid #dc3545; border-radius: 4px; font-size: 14px;"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="#" onclick="changePage(<?php echo $i; ?>)" style="padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; font-size: 14px;"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="#" onclick="changePage(<?php echo $page + 1; ?>)" style="padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; font-size: 14px;">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div style="text-align: center; padding: 40px; color: #666; font-style: italic; font-size: 16px;">
        No inactive patients found.
        <?php if (!empty($search)): ?>
            <br><span style="font-size: 14px; color: #999;">Try a different search term.</span>
        <?php endif; ?>
    </div>
<?php endif; ?>
    
    <script>
 function searchInactivePatients(form) {
    const search = form.search.value;
    const url = new URL('transact/inactive_patient_modal.php', window.location);
    
    if (search.trim() !== '') {
        url.searchParams.set('search', search);
    }
    
    // Remove page parameter when searching
    url.searchParams.delete('page');
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            document.querySelector('#inactivePatientsModal > div').innerHTML = html;
        });
}

function clearSearch() {
    fetch('transact/inactive_patient_modal.php')
        .then(response => response.text())
        .then(html => {
            document.querySelector('#inactivePatientsModal > div').innerHTML = html;
        });
}

function changePage(page) {
    const url = new URL('transact/inactive_patient_modal.php', window.location);
    const search = new URLSearchParams(window.location.search).get('search');
    
    if (search) {
        url.searchParams.set('search', search);
    }
    url.searchParams.set('page', page);
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            document.querySelector('#inactivePatientsModal > div').innerHTML = html;
        });
}
    </script>
</body>
</html>