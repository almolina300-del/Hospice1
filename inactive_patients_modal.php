<?php
session_start();
require('Config/Config.php');

// Check if user is SUADMIN
if (!isset($_SESSION['Role']) || strtoupper($_SESSION['Role']) != 'SUADMIN') {
    die('<div style="text-align:center; padding:20px; color:red;">Access Denied</div>');
}

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS) or die('Could not connect to database.');
mysqli_select_db($conn, SQL_DB);

// Get search term if any
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Number of rows per page
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Build query with improved search
$where = "WHERE is_active = 0";
if (!empty($search)) {
    // Split search terms by spaces
    $search_terms = explode(' ', $search);
    
    // Build search conditions for each term
    $search_conditions = array();
    foreach ($search_terms as $term) {
        if (!empty(trim($term))) {
            $term_escaped = mysqli_real_escape_string($conn, trim($term));
            $search_conditions[] = "(Last_name LIKE '%$term_escaped%' OR First_name LIKE '%$term_escaped%' OR Middle_name LIKE '%$term_escaped%')";
        }
    }
    
    if (!empty($search_conditions)) {
        $where .= " AND (" . implode(' AND ', $search_conditions) . ")";
    }
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
        /* Your CSS styles remain the same */
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
        
        .search-box button:hover {
            background: #218838;
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
        
        .no-records {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
            font-size: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .search-info {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f0f8ff;
            border-radius: 4px;
            border-left: 4px solid #007bff;
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
        <button class="close-btn" onclick="parent.closeInactiveModal()">Close</button>
    </div>
   <!-- In inactive_patients_modal.php -->
<!-- Search Box - NO FORM TAG! -->
<div class="search-box">
    <input type="text" id="searchInput" 
           placeholder="Search by name (Last name, First name, or Middle name)..." 
           value="<?php echo htmlspecialchars($search); ?>">
    <button type="button" onclick="performSearch()">Search</button>
    <?php if (!empty($search)): ?>
        <button type="button" onclick="performClear()" style="background: #6c757d;">Clear</button>
    <?php endif; ?>
</div>
    
    <!-- Search info if searching -->
    <?php if (!empty($search)): ?>
        <div class="search-info">
            Search results for: "<strong><?php echo htmlspecialchars($search); ?></strong>"
            <?php if ($total_rows == 0): ?>
                - No records found
            <?php else: ?>
                - Found <?php echo $total_rows; ?> record<?php echo $total_rows != 1 ? 's' : ''; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
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
                       <a href="transact/inactive_transact.php?c=<?php echo $row['Patient_id']; ?>&a=Restore Record<?php 
    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
    echo $page > 1 ? '&page=' . $page : ''; 
    ?>"
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
                <a href="#" class="page-link" data-page="<?php echo $page - 1; ?>" style="padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; font-size: 14px;">Previous</a>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($total_pages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i == $page): ?>
                    <span style="padding: 8px 15px; background: #dc3545; color: white; border: 1px solid #dc3545; border-radius: 4px; font-size: 14px;"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="#" class="page-link" data-page="<?php echo $i; ?>" style="padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; font-size: 14px;"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="#" class="page-link" data-page="<?php echo $page + 1; ?>" style="padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; font-size: 14px;">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- No records found message -->
    <div class="no-records">
        <span style="font-size: 48px; color: #dc3545; margin-bottom: 15px; display: block;">🔍</span>
        <div style="margin-bottom: 15px;">
            <strong>No inactive patients found</strong>
        </div>
        <?php if (!empty($search)): ?>
            <div style="margin-bottom: 10px;">
                No records found for: "<strong><?php echo htmlspecialchars($search); ?></strong>"
            </div>
        <?php else: ?>
            <div style="color: #999; font-size: 13px;">
                There are currently no inactive patients in the system.
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
    
<script>
// Global functions (for onclick attributes)
function performSearch() {
    const searchInput = document.getElementById('searchInput');
    const search = searchInput ? searchInput.value.trim() : '';
    
    let url = 'inactive_patients_modal.php';
    if (search) {
        url += '?search=' + encodeURIComponent(search);
    }
    
    if (window.parent && window.parent.reloadInactiveModal) {
        window.parent.reloadInactiveModal(url);
    }
    return false;
}

function performClear() {
    if (window.parent && window.parent.reloadInactiveModal) {
        window.parent.reloadInactiveModal('inactive_patients_modal.php');
    }
    return false;
}

function performPageChange(page) {
    const searchInput = document.getElementById('searchInput');
    const search = searchInput ? searchInput.value.trim() : '';
    
    let url = 'inactive_patients_modal.php?page=' + page;
    if (search) {
        url += '&search=' + encodeURIComponent(search);
    }
    
    if (window.parent && window.parent.reloadInactiveModal) {
        window.parent.reloadInactiveModal(url);
    }
    return false;
}

// Add Enter key support
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        performSearch();
    }
});

// Focus on search input
document.getElementById('searchInput').focus();
</script>
</body>
</html>