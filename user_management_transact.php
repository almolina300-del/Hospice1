<?php
require('Config/Config.php');
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

// Capture GET and POST values
$action = $_GET['a'] ?? $_POST['a'] ?? '';
$User_id = $_GET['c'] ?? $_POST['c'] ?? '';

// Database connection
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// Define variables safely with input sanitization
$Last_name = mysqli_real_escape_string($conn, $_POST['Last_name'] ?? '');
$First_name = mysqli_real_escape_string($conn, $_POST['First_name'] ?? '');
$Email = mysqli_real_escape_string($conn, $_POST['Email'] ?? '');
$Username = mysqli_real_escape_string($conn, $_POST['Username'] ?? '');
$Password = mysqli_real_escape_string($conn, $_POST['Password'] ?? '');
$Facility_name = mysqli_real_escape_string($conn, $_POST['Facility_name'] ?? '');
$Role = mysqli_real_escape_string($conn, $_POST['Role'] ?? '');

// Redirect target
$redirect = 'user_management.php';

switch ($action) {

    /* ---------------------- CREATE RECORD ---------------------- */
    case "Create Record":
        $Last_name = strtoupper($Last_name);
        $First_name = strtoupper($First_name);
        $Email = strtolower($Email);
        $Username = strtolower($Username);
        $Facility_name = strtoupper($Facility_name);
        $Role = strtoupper($Role);

        // Validate required fields
        if (empty($Last_name) || empty($First_name) || empty($Email) || empty($Username) || empty($Password)) {
            echo "<script>
                    alert('Last Name, First Name, Email, Username, and Password are required fields.');
                    window.history.back();
                  </script>";
            exit();
        }

        // Check for duplicate username or email
        $dup_sql = "SELECT User_id FROM users 
                    WHERE Username = ? OR Email = ?";
        $stmt = mysqli_prepare($conn, $dup_sql);
        mysqli_stmt_bind_param($stmt, "ss", $Username, $Email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo "<script>
                    alert('User with this Username or Email already exists.');
                    window.history.back();
                  </script>";
            mysqli_stmt_close($stmt);
            exit();
        }
        mysqli_stmt_close($stmt);

        // Hash the password for security
        $hashed_password = password_hash($Password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users 
                (Last_name, First_name, Email, Username, Password, Facility_name, Role, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssss",
            $Last_name, $First_name, $Email, $Username, $hashed_password, $Facility_name, $Role);

        if (mysqli_stmt_execute($stmt)) {
            echo "<script>
                    alert('User record successfully created!');
                    window.location.href = 'user_management.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Error creating user record.');
                    window.history.back();
                  </script>";
        }
        mysqli_stmt_close($stmt);
        exit();

    /* ---------------------- DEACTIVATE RECORD ---------------------- */
    case "deactivate":
        if (!empty($User_id)) {
            // Prevent deactivating your own account
            if ($User_id == $_SESSION['User_id'] ?? '') {
                echo "<script>
                        alert('You cannot deactivate your own account.');
                        window.location.href = 'user_management.php';
                      </script>";
                exit();
            }

            $sql = "UPDATE users SET is_active = 0 WHERE User_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $User_id);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
                        alert('User account has been deactivated.');
                        window.location.href = 'user_management.php';
                      </script>";
            }
            mysqli_stmt_close($stmt);
        }
        exit();

    /* ---------------------- ACTIVATE RECORD ---------------------- */
    case "activate":
        if (!empty($User_id)) {
            $sql = "UPDATE users SET is_active = 1 WHERE User_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $User_id);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
                        alert('User account has been activated.');
                        window.location.href = 'user_management.php';
                      </script>";
            }
            mysqli_stmt_close($stmt);
        }
        exit();

    /* ---------------------- UPDATE RECORD ---------------------- */
    case "Update Record":
        // Get the original user ID from the key
        $original_user_id = mysqli_real_escape_string($conn, $User_id);
        
        // Get form values
        $Last_name = strtoupper(mysqli_real_escape_string($conn, $_POST['Last_name'] ?? ''));
        $First_name = strtoupper(mysqli_real_escape_string($conn, $_POST['First_name'] ?? ''));
        $Email = strtolower(mysqli_real_escape_string($conn, $_POST['Email'] ?? ''));
        $Username = strtolower(mysqli_real_escape_string($conn, $_POST['Username'] ?? ''));
        $Facility_name = strtoupper(mysqli_real_escape_string($conn, $_POST['Facility_name'] ?? ''));
        $Role = strtoupper(mysqli_real_escape_string($conn, $_POST['Role'] ?? ''));
        
        // Password update is optional
        $Password = $_POST['Password'] ?? '';
        $update_password = !empty($Password);

        // Validate required fields
        if (empty($Last_name) || empty($First_name) || empty($Email) || empty($Username)) {
            echo "<script>
                    alert('Last Name, First Name, Email, and Username are required fields.');
                    window.history.back();
                  </script>";
            exit();
        }

        // Verify the user exists
        $check_sql = "SELECT User_id FROM users WHERE User_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $original_user_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) == 0) {
            mysqli_stmt_close($check_stmt);
            echo "<script>
                    alert('User record not found.');
                    window.history.back();
                  </script>";
            exit();
        }
        mysqli_stmt_close($check_stmt);

        // Check for duplicate username or email (excluding current user)
        $dup_sql = "SELECT User_id FROM users 
                    WHERE (Username = ? OR Email = ?) 
                    AND User_id != ?";
        $dup_stmt = mysqli_prepare($conn, $dup_sql);
        mysqli_stmt_bind_param($dup_stmt, "sss", $Username, $Email, $original_user_id);
        mysqli_stmt_execute($dup_stmt);
        mysqli_stmt_store_result($dup_stmt);

        if (mysqli_stmt_num_rows($dup_stmt) > 0) {
            echo "<script>
                    alert('Username or Email already exists for another user.');
                    window.history.back();
                  </script>";
            mysqli_stmt_close($dup_stmt);
            exit();
        }
        mysqli_stmt_close($dup_stmt);

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            if ($update_password) {
                // Update with password
                $hashed_password = password_hash($Password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users 
                               SET Last_name = ?,
                                   First_name = ?,
                                   Email = ?,
                                   Username = ?,
                                   Password = ?,
                                   Facility_name = ?,
                                   Role = ?
                               WHERE User_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, "ssssssss", 
                    $Last_name, $First_name, $Email, $Username, $hashed_password, $Facility_name, $Role, $original_user_id);
            } else {
                // Update without changing password
                $update_sql = "UPDATE users 
                               SET Last_name = ?,
                                   First_name = ?,
                                   Email = ?,
                                   Username = ?,
                                   Facility_name = ?,
                                   Role = ?
                               WHERE User_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, "sssssss", 
                    $Last_name, $First_name, $Email, $Username, $Facility_name, $Role, $original_user_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                mysqli_commit($conn);
                mysqli_stmt_close($stmt);
                
                echo "<script>
                        alert('User record successfully updated.');
                        window.location.href = 'user_management.php';
                      </script>";
                exit();
            } else {
                $error_msg = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new Exception("Update failed: $error_msg");
            }

        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<script>
                    alert('Error updating record: " . addslashes($e->getMessage()) . "');
                    window.history.back();
                  </script>";
            exit();
        }
}

// Close database connection
mysqli_close($conn);
?>