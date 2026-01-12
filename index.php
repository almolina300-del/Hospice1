<!DOCTYPE html>
<?php
session_start();

include('Config/Config.php'); // $conn already exists

$error = ""; // initialize error variable

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $Username = mysqli_real_escape_string($conn, $_POST['Username'] ?? '');
    $Password = $_POST['Password'] ?? '';

    if ($Username === '' || $Password === '') {
        $error = "Please enter both username and password.";
    } else {
        // Use BINARY to make the comparison case-sensitive
        $sql = "SELECT * FROM user_management 
                WHERE BINARY Username='$Username' 
                AND BINARY Password='$Password'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            $_SESSION['Username'] = $row['Username'];
            $_SESSION['First_name'] = $row['First_name'];
            $_SESSION['Role'] = $row['Role']; // Add Role to session

            // Set welcome message using first_name
            $First_name = $row['First_name'];
            $_SESSION['success_message'] = "Hello $First_name, Welcome to MHD Prescription System.";

            header("Location: Patiententry.php"); // redirect after login
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<html>

<head>
    <title>Login - MHD Prescription System</title>
    <link rel="stylesheet" type="text/css" href="CSS/index.css">
</head>

<body>
    <header>
        <img src="img/mhd_logo.png" alt="Makati Logo" class="logo">
        <span>MAKATI HEALTH DEPARTMENT<br>
            <span style="font-size: 18px; color: #F2F0EF; font-weight: normal;">Prescription System</span>
        </span>
    </header>

    <div class="login-container">
        <!-- Login Form Section -->
        <div class="form-section">
            <h2>
                Login
            </h2>

            <!-- FORM STARTS HERE -->
            <form method="POST" action="">
                <input type="text" name="Username" placeholder="Username" required>

                <input type="password" name="Password" placeholder="Password" required>

                <button type="submit">Login</button>

                <?php if (isset($error) && !empty($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
               
    <div class="footer-note">
        For technical assistance, contact <br><strong>
              <div style="text-align: center; margin: 5px 0 10px 0;">
        <img src="img/hid_logo_icon.png" alt="Hid Logo" style="max-width: 150px; height: auto;">
    </div>Health Informatics Division</strong><br>
        Email: <strong>mhims@makati.gov.ph</strong><br>
        Local: <strong>1444</strong><br>
    </div>
                
            </form>
            <!-- FORM ENDS HERE -->
        </div>

        <!-- Image Section -->
        <div class="image-section">
            <img src="img/rx1.jpg" alt="Healthcare">
        </div>

</body>

</html>