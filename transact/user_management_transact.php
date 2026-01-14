<?php
session_start();
require('../Config/Config.php');

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    header("Location: ../index.php");
    exit();
}

// Check if user is SUADMIN
if (!isset($_SESSION['Role']) || $_SESSION['Role'] !== 'SUADMIN') {
    $_SESSION['error_message'] = 'Access denied. SUADMIN privileges required.';
    header("Location: ../user_management.php");
    exit();
}

// Capture GET values
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_GET['User_id'] ?? $_POST['User_id'] ?? '';

// Database connection
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// Redirect target
$redirect = '../user_management.php';

switch ($action) {
    /* ---------------------- DEACTIVATE RECORD ---------------------- */
    case "deactivate":
        if (!empty($user_id)) {
            // Prevent deactivating your own account
            if ($user_id == ($_SESSION['User_id'] ?? '')) {
                $_SESSION['error_message'] = 'You cannot deactivate your own account.';
                header("Location: $redirect");
                exit();
            }

            // Validate user exists
            $check_sql = "SELECT User_id, Username FROM user_management WHERE User_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $user_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($check_stmt);

            if (!$user) {
                $_SESSION['error_message'] = 'User not found.';
                header("Location: $redirect");
                exit();
            }

            // Deactivate user
            $sql = "UPDATE user_management SET is_active = 0 WHERE User_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "User '{$user['Username']}' has been deactivated successfully!";
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error_message'] = 'Error deactivating user: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
        header("Location: $redirect");
        exit();

    /* ---------------------- ACTIVATE RECORD ---------------------- */
    case "activate":
        if (!empty($user_id)) {
            // Validate user exists
            $check_sql = "SELECT User_id, Username FROM user_management WHERE User_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $user_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($check_stmt);

            if (!$user) {
                $_SESSION['error_message'] = 'User not found.';
                header("Location: $redirect");
                exit();
            }

            // Activate user
            $sql = "UPDATE user_management SET is_active = 1 WHERE User_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "User '{$user['Username']}' has been activated successfully!";
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error_message'] = 'Error activating user: ' . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
        header("Location: $redirect");
        exit();
        
    /* ---------------------- OTHER ACTIONS ---------------------- */
    // You can add other actions here like "create", "update", etc.
    default:
        $_SESSION['error_message'] = 'Invalid action specified.';
        header("Location: $redirect");
        exit();
}

// Close database connection
mysqli_close($conn);
?>