<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['Username'])) {
    header("Location: index.php");
    exit();
}

require('Config/Config.php');

// ---------- GET PATIENT ID ----------
$char = isset($_GET['c']) && is_numeric($_GET['c']) ? intval($_GET['c']) : 0;

// ---------- DEFAULTS ----------
$subtype = "Create";
$subhead = "Please enter character data and click '$subtype Record.'";
$tablebg = '#EEEEFF';

// ---------- DATABASE CONNECTION ----------
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS);
if (!$conn) die('Could not connect to MySQL database: ' . mysqli_connect_error());
mysqli_select_db($conn, SQL_DB);

// ---------- LOAD EXISTING PATIENT ----------
$ch = null;
if ($char > 0) {
    $sql = "
        SELECT 
            pd.*,
            yc.Yellow_card_nos,
            yc.Membership_type,
            yc.Yc_expiry_date
        FROM patient_details pd
        LEFT JOIN yellow_card yc ON pd.Patient_id = yc.Patient_id
        WHERE pd.Patient_id = $char
    ";
    $result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
    if ($result && mysqli_num_rows($result) > 0) {
        $ch = mysqli_fetch_assoc($result);
        $subtype = "Update";
        $tablebg = '#EEFFEE';
        if (!empty($ch['alias'])) {
            $subhead = "Edit data for <i>" . htmlspecialchars($ch['alias']) . "</i> and click '$subtype Record.'";
        }
    }
}

// ---------- FETCH PATIENT SUMMARY ----------
$patientName = '';
$patientAddress = '';
$patientSex = '';
$patientAge = '';
$hasBirthday = false; // Add this variable

if ($char > 0) {
    $sql = "
        SELECT 
            CONCAT(pd.Last_name, ', ', pd.First_name, ' ', pd.Middle_name) AS FullName,
            CONCAT(pd.House_nos_street_name, ', ', pd.Barangay) AS FullAddress,
            pd.Sex,
            pd.Birthday
        FROM patient_details pd
        LEFT JOIN yellow_card yc ON pd.Patient_id = yc.Patient_id
        WHERE pd.Patient_id = $char
        LIMIT 1
    ";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $patientName = 'Name: ' . htmlspecialchars($row['FullName']);
        $patientAddress = 'Address: ' . htmlspecialchars($row['FullAddress']);
        $patientSex = htmlspecialchars($row['Sex']);

        // Check if birthday exists and is not empty/null
        if (!empty($row['Birthday']) && $row['Birthday'] != '0000-00-00') {
            $birthDate = new DateTime($row['Birthday']);
            $today = new DateTime();
            $patientAge = $birthDate->diff($today)->y;
            $hasBirthday = true; // Birthday exists
        } else {
            $patientAge = 'N/A';
            $hasBirthday = false; // No birthday
        }

        $patientName .= "    Age: $patientAge    Sex: $patientSex";
    }
}

// ---------- FETCH LATEST PRESCRIPTION MEDICINES ----------
$latestMeds = [];
$latestRefillDay = null;
if ($char > 0) {
    $presQuery = "SELECT Prescription_id, Refill_day FROM prescription WHERE Patient_id = $char ORDER BY Date DESC LIMIT 1";
    $presResult = mysqli_query($conn, $presQuery);
    if ($presResult && mysqli_num_rows($presResult) > 0) {
        $prescription = mysqli_fetch_assoc($presResult);
        $latestPrescriptionId = intval($prescription['Prescription_id']);
        $latestRefillDay = isset($prescription['Refill_day']) ? $prescription['Refill_day'] : null;

        $medQuery = "
            SELECT m.Medicine_name, m.Dose, m.Form, r.Quantity, r.Frequency
            FROM rx r
            INNER JOIN medicine m ON m.Medicine_id = r.Medicine_id
            WHERE r.Prescription_id = $latestPrescriptionId
        ";
        $medResult = mysqli_query($conn, $medQuery);
        if ($medResult && mysqli_num_rows($medResult) > 0) {
            while ($row = mysqli_fetch_assoc($medResult)) {
                $latestMeds[] = $row;
            }
        }
    }
}

// ---------- FETCH REFERENCE MEDICINES ----------
$refMeds = [];
$refQuery = "SELECT Medicine_name, Dose, Form FROM medicine ORDER BY Medicine_name ASC";
$refResult = mysqli_query($conn, $refQuery);
if ($refResult && mysqli_num_rows($refResult) > 0) {
    while ($row = mysqli_fetch_assoc($refResult)) {
        $refMeds[] = $row;
    }
}

// Fetch doctors for dropdown
$doctorOptions = [];
$docQuery = "SELECT License_number, Ptr_number, Last_name, First_name, Middle_name FROM doctors ORDER BY Last_name ASC";
$docResult = mysqli_query($conn, $docQuery);
if ($docResult && mysqli_num_rows($docResult) > 0) {
    while ($doc = mysqli_fetch_assoc($docResult)) {
        $doctorOptions[] = [
            'name' => trim($doc['Last_name'] . ', ' . $doc['First_name'] . ' ' . $doc['Middle_name']),
            'license' => $doc['License_number'],
            'ptr' => $doc['Ptr_number'] ?? '' // Add this line
        ];
    }
}

// Yellow Card Expiry Check
$ycExpired = false;
$ycExpiryDate = $ch['Yc_expiry_date'] ?? null;
if ($ycExpiryDate) {
    $today = new DateTime();
    $expiry = new DateTime($ycExpiryDate);
    if ($expiry < $today) {
        $ycExpired = true;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Patient Record</title>
    <link rel="stylesheet" type="text/css" href="CSS/ptinfo.css">
    <script>
        // PREVENT ENTER KEY FROM SUBMITTING FORMS
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent Enter in main patient form
            const mainForm = document.querySelector('form[name="theform"]');
            if (mainForm) {
                mainForm.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        return false;
                    }
                });

                const mainInputs = mainForm.querySelectorAll('input, select, textarea');
                mainInputs.forEach(input => {
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            return false;
                        }
                    });
                });
            }

            // Prevent Enter in prescription modal forms
            const prescriptionForm = document.getElementById('addPrescriptionForm');
            const editPrescriptionForm = document.getElementById('editPrescriptionForm');

            [prescriptionForm, editPrescriptionForm].forEach(form => {
                if (form) {
                    form.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            return false;
                        }
                    });

                    const prescriptionInputs = form.querySelectorAll('input, select, textarea');
                    prescriptionInputs.forEach(input => {
                        input.addEventListener('keydown', function(e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                return false;
                            }
                        });
                    });
                }
            });
        });
    </script>
</head>

<body>
    <!-- Success Messages Display -->
    <?php
    // Check for success messages from prescription update
    if (isset($_GET['success']) && $_GET['success'] == 1) {
        echo "<div id='successMessage' style='
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-weight: bold;
        '>
            ✓ Prescription updated successfully!
        </div>
        <script>
            setTimeout(function() {
                document.getElementById('successMessage').style.display = 'none';
            }, 5000);
        </script>";
    }

    // Check for success messages from prescription creation
    if (isset($_GET['added']) && $_GET['added'] == 1) {
        echo "<div id='addMessage' style='
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-weight: bold;
        '>
            ✓ Prescription added successfully!
        </div>
        <script>
            setTimeout(function() {
                document.getElementById('addMessage').style.display = 'none';
            }, 5000);
        </script>";
    }

    // Check for delete messages
    if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
        echo "<div id='deleteMessage' style='
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px 20px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-weight: bold;
        '>
            ✓ Prescription deleted successfully!
        </div>
        <script>
            setTimeout(function() {
                document.getElementById('deleteMessage').style.display = 'none';
            }, 5000);
        </script>";
    }
    ?>

    <div class="sidebar">
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

        <!-- Patient Records Link -->
        <a href="Patiententry.php" style="margin: 5px 0; padding: 10px; text-decoration: none; color: white; background: rgba(255,255,255,0.1); border-radius: 5px;">
            Patient Records
        </a>



        <!-- Single Logout Container -->
        <div class="logout-container" style="margin-top: auto; padding: 10px 0;">
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
                <img src="img/logout_icon.png" alt="Logout" class="logo" style="width: 20px; height: 20px;">
                <span>Logout</span>
            </a>
        </div>
    </div>

    <h1>
        <a href='Patiententry.php' style="
    color: white;
    background-color: #007bff;
    text-decoration: none;
    padding: 4px 12px;
    border-radius: 4px;
    font-weight: bold;
    margin-right: 10px;
    font-size: 14px;
    display: inline-block;">
            &lt;
        </a>
        <?php
        if ($ch) {
            $birthday = new DateTime($ch['Birthday'] ?? '');
            $today = new DateTime();
            $age = $today->diff($birthday)->y;
            echo htmlspecialchars(($ch['Last_name'] ?? '') . ', ' . ($ch['First_name'] ?? '') . ' ' . ($ch['Middle_name'] ?? ''));
            echo " ($age yrs. old)";
        } else {
            echo "Add New Patient";
        }
        ?>
    </h1>


    <!-- Patient Details Form -->
    <form action='pttransact.php' name='theform' method='post'>
        <table align="center" border="1" cellpadding="5" width="70%" bgcolor="<?php echo $tablebg; ?>">
            <tr>
                <th style="white-space:nowrap; padding-left:30px; position:relative;">
                    <img src="img/personal_det_icon.png" alt="Patient Details Icon" style="position:absolute; left:2px; top:40%; transform:translateY(-50%); height:30px; width:30px;">
                    Patient Details
                </th>
            </tr>
            <!-- Last Name -->
            <tr>
                <td style="white-space:nowrap;">Last name: <span style="color:red;">*</span></td>
                <td style="white-space:nowrap;">
                    <input type="text" name="Last_name" size="20" style="text-transform:uppercase;"
                        value="<?php echo isset($ch['Last_name']) ? htmlspecialchars($ch['Last_name']) : ''; ?>" required>
                </td>
                <!-- First Name -->
                <td style="white-space:nowrap;">First name: <span style="color:red;">*</span></td>
                <td style="white-space:nowrap;">
                    <input type="text" name="First_name" size="20" style="text-transform:uppercase; width:250px;"
                        value="<?php echo isset($ch['First_name']) ? htmlspecialchars($ch['First_name']) : ''; ?>" required>
                </td>
                <!-- Middle Name -->
                <td style="white-space:nowrap;">Middle name:</td>
                <td style="white-space:nowrap;">
                    <input type="text" name="Middle_name" size="20" style="text-transform:uppercase;"
                        value="<?php echo isset($ch['Middle_name']) ? htmlspecialchars($ch['Middle_name']) : ''; ?>">
                </td>
                <!-- Suffix -->
                <?php
                $suffixes = ["", "Jr.", "Sr.", "III", "IV"];
                ?>
                <td>Suffix:</td>
                <td colspan="2">
                    <select name="Suffix">
                        <?php
                        foreach ($suffixes as $s) {
                            $selected = (isset($ch['Suffix']) && $ch['Suffix'] == $s) ? 'selected' : '';
                            echo "<option value=\"$s\" $selected>$s</option>";
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <!-- Sex and Birthday -->
            <tr>
                <td style="white-space:nowrap;">Sex: <span style="color:red;">*</span></td>
                <td style="white-space:nowrap;">
                    <label style="margin-right:6px;">
                        <input type="radio" name="Sex" value="MALE" required <?php echo (isset($ch['Sex']) && $ch['Sex'] == 'MALE') ? 'checked' : ''; ?>> MALE
                    </label>
                    <label>
                        <input type="radio" name="Sex" value="FEMALE" required <?php echo (isset($ch['Sex']) && $ch['Sex'] == 'FEMALE') ? 'checked' : ''; ?>> FEMALE
                    </label>
                </td>
                <td>Birthday: <span style="color:red;">*</span></td>
                <td colspan="2">
                    <input type="date" name="Birthday" max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>"
                        value="<?php echo isset($ch['Birthday']) ? date('Y-m-d', strtotime($ch['Birthday'])) : ''; ?>" required>
                </td>
            </tr>
            <!-- Contact Nos., House Nos., and Barangay on same row -->
            <tr>
                <!-- Contact Nos. -->
                <td>Contact Nos.: </td>
                <td style="white-space:nowrap;">
                    <input type="text" name="Contact_nos" maxlength="11" pattern="\d{11}" title="Please enter exactly 11 digits (e.g., 09123456789)"
                        style="width:150px; padding:4px 6px; font-size:13.5px; border:1px solid #ccc; border-radius:4px;"
                        value="<?php echo isset($ch['Contact_nos']) ? htmlspecialchars($ch['Contact_nos']) : ''; ?>"
                        placeholder="09XXXXXXXXX" oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                </td>

                <!-- House Nos. and St. Name -->
                <td style="white-space:nowrap;">House Nos. and St. Name: <span style="color:red;">*</span></td>
                <td style="white-space:nowrap;">
                    <input type="text" name="House_nos_street_name" style="text-transform:uppercase; width:250px; padding:4px 6px; font-size:13.5px; border:1px solid #ccc; border-radius:4px;"
                        value="<?php echo isset($ch['House_nos_street_name']) ? htmlspecialchars($ch['House_nos_street_name']) : ''; ?>" required>
                </td>

                <!-- Barangay -->
                <td style="white-space:nowrap;">Barangay: <span style="color:red;">*</span></td>
                <td style="white-space:nowrap;">
                    <input type="text"
                        name="Barangay"
                        id="barangayInput"
                        list="barangayList"
                        required
                        value="<?php echo isset($ch['Barangay']) ? htmlspecialchars($ch['Barangay']) : ''; ?>"
                        style="width: 180px; padding:4px 6px; font-size:13.5px; border:1px solid #ccc; border-radius:4px;"
                        placeholder="Type or select barangay"
                        autocomplete="off">

                    <datalist id="barangayList">
                        <?php
                        $sql = "SELECT barangay FROM barangay WHERE is_active = 1 ORDER BY barangay ASC";
                        $result = mysqli_query($conn, $sql);

                        if ($result && mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $b = htmlspecialchars($row['barangay']);
                                echo "<option value=\"$b\">$b</option>";
                            }
                        }
                        ?>
                    </datalist>
                </td>
            </tr>
            <!-- Action Buttons -->
            <tr>
                <td align="center">
                    <?php if ($subtype == "Update" && $char > 0): ?>
                        <button type="button" onclick="showDeactivateModal(<?php echo $char; ?>, '<?php echo htmlspecialchars(addslashes($ch['Last_name'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($ch['First_name'] ?? '')); ?>')"
                            style="background-color:#d9534f; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold;">
                            Deactivate Record
                        </button>
                    <?php endif; ?>
                </td>
                <td align="center"><input type="reset"></td>
                <td align="center">
                    <input type='submit' name='action' value='<?php echo $subtype; ?> Record' style="background-color:#3CB371; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;"
                        id="updateBtn" <?php if ($subtype == "Update") echo "onclick=\"return confirm('Are you sure you want to update this record?');\""; ?>>
                </td>
            </tr>
        </table>
        <input type='hidden' name='cid' value='<?php echo $char; ?>'>
    </form>

    <!-- Yellow Card and Remarks Container -->
    <?php if ($subtype == "Update" && $char > 0): ?>
        <div style="display: flex; align-items: flex-start; margin: 10px 0; gap: 20px; margin-left: 220px">
            <!-- Left side: Yellow Card -->
            <div style="flex: 0 0 300px;">
                <?php
                // Fetch existing yellow card data
                $yellowCardSql = "SELECT * FROM yellow_card WHERE Patient_id = $char";
                $yellowCardResult = mysqli_query($conn, $yellowCardSql);
                $hasYellowCard = mysqli_num_rows($yellowCardResult) > 0;
                $yellowCardData = $hasYellowCard ? mysqli_fetch_assoc($yellowCardResult) : null;
                ?>

                <div style="text-align: left; padding: 5px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
                    <?php if ($hasYellowCard): ?>
                        <div style="margin-bottom: 10px;">
                            <div style="background: white; padding: 15px; border-radius: 8px; border: 2px dashed #ccc; min-width: 300px; max-width: 200px;">
                                <div style="margin-bottom: 8px; font-size: 16px; font-weight: bold; color: #333;">
                                    <img src="img/yellow_card_pic.png" alt="Yellow Card" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px;">
                                    Yellow Card Details
                                </div>
                                <div style="margin-bottom: 5px;"><strong>Yellow Card No.:</strong> <?php echo htmlspecialchars($yellowCardData['Yellow_card_nos']); ?></div>
                                <div style="margin-bottom: 5px;"><strong>Membership:</strong> <?php echo htmlspecialchars($yellowCardData['Membership_type']); ?></div>
                                <div style="margin-bottom: 5px;"><strong>Expiry:</strong> <?php echo htmlspecialchars($yellowCardData['Yc_expiry_date']); ?></div>

                                <?php
                                // Check if expired
                                $expiry = new DateTime($yellowCardData['Yc_expiry_date']);
                                $today = new DateTime();
                                if ($expiry < $today): ?>
                                    <div style="color: #dc3545; font-weight: bold; margin-top: 10px; font-size: 14px;">
                                        ⚠ This card has expired<br>
                                        <button onclick="openYellowCardModal('edit')"
                                            style="background-color:#3CB371; color:white; border:none; padding: 8px 16px; border-radius: 4px; cursor:pointer; font-weight:bold; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 5px; width: 100%;"> Edit Yellow Card
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top: 10px;">
                                        <button onclick="openYellowCardModal('edit')"
                                            style="background-color:#3CB371; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">
                                            Edit Yellow Card
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Add Yellow Card section -->
                        <div style="margin-bottom: 10px;">
                            <div style="background: white; padding: 15px; border-radius: 8px; border: 2px dashed #ccc; min-width: 300px; max-width: 200px;">
                                <div style="margin-bottom: 8px; font-size: 16px; font-weight: bold; color: #333;">
                                    <img src="img/yellow_card_pic.png" alt="Yellow Card" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px; opacity: 0.6;">
                                    <span style="color: #dc3545; margin-left: 8px;">⚠</span> No Yellow Card
                                </div>
                                <div style="margin-bottom: 5px;"><strong>Yellow Card No.:</strong> <span style="color: red; font-style: italic;">-</span></div>
                                <div style="margin-bottom: 5px;"><strong>Membership-Type:</strong> <span style="color: red; font-style: italic;">-</span></div>
                                <div style="margin-bottom: 5px;"><strong>Expiry:</strong> <span style="color: red; font-style: italic;">-</span></div>

                                <div style="margin-top: 15px; text-align: center;">
                                    <button onclick="openYellowCardModal('add')"
                                        style="background-color:#3CB371; color:white; border:none; padding: 8px 16px; border-radius: 4px; cursor:pointer; font-weight:bold; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 5px; width: 100%;">
                                        <span style="font-size: 18px;"></span> Add Yellow Card
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right side: Remarks Section -->
            <div style="flex: 1; min-width: 350px; max-width: 920px;">
                <!-- REMARKS SECTION WITH ADD BUTTON -->
                <div style="color: #333; text-align: center; margin: 0 0 10px 0; font-weight: bold; font-size: 16px; display: flex; align-items: center; justify-content: space-between;">
                    <span>Remarks</span>
                    <button onclick="openAddRemarkModal()" style="background: #28a745; color: white; border: none; width: 200px; height: 24px; border-radius: 2%; font-size: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center;">Add remarks</button>
                </div>

                <!-- TABLE WITH SCROLLBAR -->
                <div style="height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 0px;">
                    <table class="remarks-table" style="margin: 0; width: 100%;">
                        <thead style="position: sticky; top: 0; background: #2c3e50; color: white; z-index: 10;">
                            <tr>
                                <th style="padding: 8px 5px; width: 60px;">Date</th>
                                <th style="padding: 8px 5px; width: 250px;">Details</th>
                                <th style="padding: 8px 5px; width: 80px;">By</th>
                                <th style="text-align: center; padding: 8px 5px; width: 5px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT Date, Created_by, Ptremarks_id, Details 
                            FROM patient_remarks 
                            WHERE patient_id = $char 
                            ORDER BY Date DESC 
                            LIMIT 10";
                            $result = mysqli_query($conn, $sql);
                            $remark_count = 0;

                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $remark_count++;
                                    $date = !empty($row['Date']) ? date('m/d', strtotime($row['Date'])) : 'N/A';
                                    $fullDate = !empty($row['Date']) ? $row['Date'] : 'N/A';
                                    $created_by = htmlspecialchars($row['Created_by']);
                                    $remark_id = $row['Ptremarks_id'];
                                    $details = htmlspecialchars($row['Details'] ?? '');

                                    // Escape for JavaScript
                                    $js_details = str_replace("'", "\\'", $details);
                                    $js_details = str_replace("\n", "\\n", $js_details);
                                    $js_created_by = str_replace("'", "\\'", $created_by);

                                    echo '<tr>';
                                    echo '<td style="font-size: 11px; padding: 6px 5px;">' . $date . '</td>';
                                    echo '<td style="font-size: 11px; padding: 6px 5px;">' . $details . '</td>';
                                    echo '<td style="font-size: 11px; padding: 6px 5px;">' . $created_by . '</td>';
                                    echo '<td style="text-align: center; font-size: 11px; padding: 6px 5px;">';
                                    echo '<button onclick="viewRemark(' . $remark_id . ', \'' . $fullDate . '\', \'' . $js_details . '\', \'' . $js_created_by . '\')" 
                                  style="background-color:#007bff; color:white; border:none; padding:4px 8px; font-size:11px; border-radius:3px; cursor:pointer; white-space: nowrap;">
                                  View</button>';
                                    echo '</td>';
                                    echo '</tr>';
                                }

                                // Show count if more than 5 rows
                                if ($remark_count > 5) {
                                    echo '<tr>';
                                    echo '<td colspan="3" style="text-align: center; padding: 8px; font-size: 10px; color: #666; font-style: italic; background: #f9f9f9;">';
                                    echo 'Showing ' . $remark_count . ' remarks (scroll for more)';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr>';
                                echo '<td colspan="3" style="text-align: center; padding: 20px; font-size: 12px; color: #666;">';
                                echo 'No remarks found';
                                echo '</td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Yellow Card Modal -->
    <div id="yellowCardModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:10001; justify-content:center; align-items:center;">
        <div style="background:white; padding:20px; border-radius:8px; width:600px; box-shadow:0 0 10px rgba(0,0,0,0.3); position:relative; max-height:80vh; overflow-y:auto;">
            <h3 style="margin-top:0; margin-bottom:20px; color:#333; border-bottom:2px solid #3CB371; padding-bottom:10px;">
                <img src="img/yellow_card_pic.png" alt="Yellow Card" style="width:24px; height:24px; vertical-align:middle; margin-right:8px;">
                <span id="yellowCardModalTitle">Add Yellow Card</span>
            </h3>

            <form id="yellowCardForm" method="post" action="transact/yc_transact.php">
                <input type="hidden" name="Patient_id" id="yellowCardPatientId" value="<?php echo $char; ?>">
                <input type="hidden" name="action" id="yellowCardAction" value="Create Yellow Card">

                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Yellow Card Number: <span style="color:red;">*</span></label>
                    <input type="text"
                        name="Yellow_card_nos"
                        id="yellowCardNos"
                        maxlength="12"
                        pattern="^\d{4}-\d{7}$"
                        title="Format: 1234-1234567"
                        placeholder="XXXX-XXXXXXX"
                        required
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:14px;"
                        oninput="
                let v = this.value.replace(/[^0-9]/g,'');
                if(v.length > 4) {
                    this.value = v.slice(0,4) + '-' + v.slice(4,11);
                } else {
                    this.value = v;
                }
            ">
                    <div style="font-size:12px; color:#666; margin-top:5px;">
                        Format: XXXX-XXXXXXX (e.g., 1234-1234567)
                    </div>
                </div>

                <div style="display:flex; gap:15px; margin-bottom:15px;">
                    <div style="flex:1;">
                        <label style="display:block; margin-bottom:5px; font-weight:bold;">Membership Type: <span style="color:red;">*</span></label>
                        <select name="Membership_type" id="membershipType" required
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                            <option value="">-- Select --</option>
                            <option value="MCG-SOLO">MCG-SOLO</option>
                            <option value="PERMANENT">PERMANENT</option>
                            <option value="SENIOR CITIZEN">SENIOR CITIZEN</option>
                            <option value="FAMILY">FAMILY</option>
                            <option value="MCG-SENIOR">MCG-SENIOR</option>
                            <option value="MCG-FAMILY">MCG-FAMILY</option>
                            <option value="SC-PERMANENT">SC-PERMANENT</option>
                            <option value="SOLO-CLR">SOLO-CLR</option>
                        </select>
                    </div>

                    <div style="flex:1;">
                        <label style="display:block; margin-bottom:5px; font-weight:bold;">Expiry Date: <span style="color:red;">*</span></label>
                        <input type="date"
                            name="Yc_expiry_date"
                            id="ycExpiryDate"
                            required
                            style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                    </div>
                </div>

                <div id="existingYellowCardInfo" style="display:none; margin-bottom:15px; padding:10px; background:#f9f9f9; border-radius:4px; border-left:4px solid #ffc107;">
                    <div style="font-size:12px; color:#666; margin-bottom:5px;">Current Yellow Card:</div>
                    <div id="currentYellowCardDetails" style="font-weight:bold;"></div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="closeYellowCardModal()"
                        style="background:#ccc; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">
                        Cancel
                    </button>
                    <button type="submit" id="yellowCardSubmitBtn"
                        style="background:#3CB371; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Remark Modal -->
    <div id="addRemarkModal" style="display:none; position:fixed; top:0px; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; padding:20px; border-radius:8px; width:400px; box-shadow:0 0 10px rgba(0,0,0,0.3); position:relative;">
            <h3 style="margin-top:0;">Add New Remark</h3>
            <form id="addRemarkForm" method="post" action="transact/remarks_transact.php">
                <!-- Add patient_id field -->
                <input type="hidden" name="patient_id" value="<?php echo $char; ?>">

                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Date:</label>
                    <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Details:</label>
                    <textarea name="Details" required
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; height:100px; resize:vertical; text-transform:uppercase;"
                        placeholder="Enter remark details..."
                        oninput="this.value = this.value.toUpperCase()"></textarea>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Remarks By:</label>
                    <div style="width:100%; padding:8px; border:1px solid #eee; border-radius:4px; background:#f9f9f9; color:#555; margin-bottom:5px; text-transform:uppercase;">
                        <?php echo strtoupper(htmlspecialchars($_SESSION['First_name'] ?? 'Unknown')); ?>
                    </div>
                    <input type="hidden" name="Created_by" value="<?php echo strtoupper(htmlspecialchars($_SESSION['First_name'] ?? 'Unknown')); ?>">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="closeAddRemarkModal()"
                        style="background:#ccc; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit" name="action" value="Add Remark"
                        style="background:#28a745; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View/Edit Remark Modal -->
    <div id="viewRemarkModal" style="display:none; position:fixed; top:0px; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:10000; justify-content:center; align-items:center;">
        <div style="background:white; padding:20px; border-radius:8px; width:500px; box-shadow:0 0 10px rgba(0,0,0,0.3); position:relative; max-height:80vh; overflow-y:auto;">
            <h3 style="margin-top:0; margin-bottom:20px; color:#333;" id="modalTitle">View Remark</h3>

            <!-- Form for editing -->
            <form id="editRemarkForm" method="post" action="transact/remarks_transact.php" style="display:none;">
                <input type="hidden" name="remark_id" id="editRemarkId">
                <input type="hidden" name="patient_id" value="<?php echo $char; ?>">
                <input type="hidden" name="Date" id="editDate">

                <div style="margin-bottom:15px;">
                    <div style="font-weight:bold; margin-bottom:5px; color:#555;" id="editRemarkIdText"></div>
                </div>

                <div style="margin-bottom:15px;">
                    <div style="font-weight:bold; margin-bottom:5px; color:#555;" id="displayEditDate"></div>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-weight:bold;">Details:</label>
                    <textarea name="Details" id="editDetails" required
                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; height:100px; resize:vertical; text-transform:uppercase;"
                        oninput="this.value = this.value.toUpperCase()"></textarea>
                </div>

                <div style="margin-bottom:15px;">
                    <div style="font-weight:bold; margin-bottom:5px; color:#555;" id="displayEditCreatedBy"></div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="cancelEdit()"
                        style="background:#ccc; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit" name="action" value="Update Remark"
                        style="background:#ffc107; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;">
                        Update
                    </button>
                </div>
            </form>

            <!-- View mode content -->
            <div id="viewModeContent">
                <div style="margin-bottom:15px;">
                    <div style="font-weight:bold; margin-bottom:5px; color:#555;" id="viewRemarkId"></div>
                </div>

                <div style="margin-bottom:15px;">
                    <div style="font-weight:bold; margin-bottom:5px; color:#555;" id="viewRemarkDate"></div>
                </div>

                <div style="margin-bottom:15px;">
                    <div style="font-weight:bold; margin-bottom:5px; color:#555;">Details:</div>
                    <div style="background:#f9f9f9; padding:15px; border-radius:5px; border:1px solid #eee; min-height:100px; white-space:pre-wrap;" id="viewRemarkDetails"></div>
                </div>

                <div style="margin-bottom:20px;">
                    <div style="font-weight:bold; margin-bottom:5px; color:#555;" id="viewRemarkCreatedBy"></div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;" id="viewModeButtons">
                    <button onclick="closeViewRemarkModal()"
                        style="background:#6c757d; color:white; border:none; padding:8px 20px; border-radius:4px; cursor:pointer; font-weight:bold;">
                        Close
                    </button>
                    <button onclick="enableEditMode()" id="editButton"
                        style="background:#ffc107; color:white; border:none; padding:8px 20px; border-radius:4px; cursor:pointer; font-weight:bold; display:none;">
                        Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for remarks modals -->
    <script>
        // Modal functions
        function openAddRemarkModal() {
            document.getElementById('addRemarkModal').style.display = 'flex';
        }

        function closeAddRemarkModal() {
            document.getElementById('addRemarkModal').style.display = 'none';
        }

        // Global variables for remark editing
        let currentRemarkCreatedBy = '';
        let currentLoggedInUser = "<?php echo strtoupper(htmlspecialchars($_SESSION['First_name'] ?? 'Unknown')); ?>";

        // View remark function
        function viewRemark(remarkId, date, details, createdBy) {
            currentRemarkCreatedBy = createdBy.toUpperCase();

            // Populate view mode
            document.getElementById('viewRemarkDate').innerText = 'Date: ' + date;
            document.getElementById('viewRemarkDetails').innerText = details;
            document.getElementById('viewRemarkCreatedBy').innerText = 'Created By: ' + createdBy;

            // Also populate edit form fields
            document.getElementById('editRemarkId').value = remarkId;
            document.getElementById('editRemarkIdText').innerText = 'Ptremarks_id: ' + remarkId;
            document.getElementById('editDate').value = date;
            document.getElementById('displayEditDate').innerText = 'Date: ' + date;
            document.getElementById('editDetails').value = details;
            document.getElementById('displayEditCreatedBy').innerText = 'Created By: ' + createdBy;

            // Show/hide edit button based on user permission
            const editButton = document.getElementById('editButton');
            if (currentRemarkCreatedBy === currentLoggedInUser) {
                editButton.style.display = 'inline-block';
            } else {
                editButton.style.display = 'none';
            }

            // Reset to view mode
            document.getElementById('viewModeContent').style.display = 'block';
            document.getElementById('editRemarkForm').style.display = 'none';
            document.getElementById('modalTitle').innerText = 'View Remark';

            // Show the modal
            document.getElementById('viewRemarkModal').style.display = 'flex';
        }

        function enableEditMode() {
            // Switch to edit mode
            document.getElementById('viewModeContent').style.display = 'none';
            document.getElementById('editRemarkForm').style.display = 'block';
            document.getElementById('modalTitle').innerText = 'Edit Remark';
        }

        function cancelEdit() {
            // Switch back to view mode
            document.getElementById('viewModeContent').style.display = 'block';
            document.getElementById('editRemarkForm').style.display = 'none';
            document.getElementById('modalTitle').innerText = 'View Remark';
        }

        function closeViewRemarkModal() {
            document.getElementById('viewRemarkModal').style.display = 'none';
            currentRemarkCreatedBy = '';
        }
    </script>

    <!-- JavaScript for yellow card modal -->
    <script>
        const patientFullName = "<?php echo isset($patientFullName) ? addslashes($patientFullName) : 'Patient'; ?>";
        // Yellow Card Modal Functions
        function openYellowCardModal(action = 'add') {
            // Reset form
            document.getElementById('yellowCardForm').reset();
            document.getElementById('existingYellowCardInfo').style.display = 'none';

            if (action === 'add') {
                // Add mode
                document.getElementById('yellowCardModalTitle').innerText = 'Add Yellow Card';
                document.getElementById('yellowCardAction').value = 'Create Yellow Card';
                document.getElementById('yellowCardSubmitBtn').innerText = 'Save';
                document.getElementById('yellowCardSubmitBtn').style.backgroundColor = '#3CB371';

                // Set default expiry date (1 year ago)
                const today = new Date();
                const lastYear = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
                document.getElementById('ycExpiryDate').value = lastYear.toISOString().split('T')[0];

            } else if (action === 'edit') {
                // Edit mode - fetch existing data via AJAX
                fetchExistingYellowCard();

                document.getElementById('yellowCardModalTitle').innerText = 'Edit Yellow Card';
                document.getElementById('yellowCardAction').value = 'Update Yellow Card';
                document.getElementById('yellowCardSubmitBtn').innerText = 'Update';
                document.getElementById('yellowCardSubmitBtn').style.backgroundColor = '#ffc107';
            }

            document.getElementById('yellowCardModal').style.display = 'flex';
        }

        function closeYellowCardModal() {
            document.getElementById('yellowCardModal').style.display = 'none';
        }

        function fetchExistingYellowCard() {
            // You can implement AJAX to fetch existing yellow card data
            // For now, we'll use PHP data if available
            <?php if (isset($yellowCardData) && !empty($yellowCardData)): ?>
                document.getElementById('yellowCardNos').value = '<?php echo $yellowCardData["Yellow_card_nos"] ?? ""; ?>';
                document.getElementById('membershipType').value = '<?php echo $yellowCardData["Membership_type"] ?? ""; ?>';
                document.getElementById('ycExpiryDate').value = '<?php echo $yellowCardData["Yc_expiry_date"] ?? ""; ?>';

                // Show existing info
                document.getElementById('existingYellowCardInfo').style.display = 'block';
                document.getElementById('currentYellowCardDetails').innerHTML =
                    '<?php echo $yellowCardData["Yellow_card_nos"] ?? ""; ?> - ' +
                    '<?php echo $yellowCardData["Membership_type"] ?? ""; ?>';
            <?php endif; ?>
        }

        // Simple form validation - NO FUTURE DATE CHECKING
        document.getElementById('yellowCardForm').addEventListener('submit', function(e) {
            const yellowCardNos = document.getElementById('yellowCardNos').value.trim();
            const membershipType = document.getElementById('membershipType').value;
            const expiryDate = document.getElementById('ycExpiryDate').value;

            // Validate Yellow Card format
            const pattern = /^\d{4}-\d{7}$/;
            if (!pattern.test(yellowCardNos)) {
                e.preventDefault();
                alert('Please enter Yellow Card number in format: XXXX-XXXXXXX (e.g., 1234-1234567)');
                document.getElementById('yellowCardNos').focus();
                return false;
            }

            if (!membershipType) {
                e.preventDefault();
                alert('Please select Membership Type');
                document.getElementById('membershipType').focus();
                return false;
            }

            if (!expiryDate) {
                e.preventDefault();
                alert('Please select Expiry Date');
                document.getElementById('ycExpiryDate').focus();
                return false;
            }

            // Show loading state
            const submitBtn = document.getElementById('yellowCardSubmitBtn');
            submitBtn.innerHTML = 'Processing...';
            submitBtn.disabled = true;

            return true;
        });
    </script>


    <!-- Scripts for change detection -->
    <script>
        const last = document.querySelector('input[name="Last_name"]');
        const first = document.querySelector('input[name="First_name"]');
        const middle = document.querySelector('input[name="Middle_name"]');
        const Suffix = document.querySelector('select[name="Suffix"]');
        const Birthday = document.querySelector('input[name="Birthday"]');
        const Contact = document.querySelector('input[name="Contact_nos"]');
        const Barangay = document.querySelector('input[name="Barangay"]');
        const House = document.querySelector('input[name="House_nos_street_name"]');
        const updateBtn = document.getElementById('updateBtn');

        function getSex() {
            const sexRadios = document.querySelectorAll('input[name="Sex"]');
            for (const r of sexRadios) {
                if (r.checked) return r.value;
            }
            return '';
        }

        const originalValues = {
            last: last.value,
            first: first.value,
            middle: middle.value,
            suffix: Suffix.value,
            sex: getSex(),
            birthday: Birthday.value,
            contact: Contact.value,
            barangay: Barangay.value,
            house: House.value
        };

        updateBtn.disabled = true;
        updateBtn.style.opacity = 0.5;

        function checkChanges() {
            if (
                last.value !== originalValues.last ||
                first.value !== originalValues.first ||
                middle.value !== originalValues.middle ||
                Suffix.value !== originalValues.suffix ||
                getSex() !== originalValues.sex ||
                Birthday.value !== originalValues.birthday ||
                Contact.value !== originalValues.contact ||
                Barangay.value !== originalValues.barangay ||
                House.value !== originalValues.house
            ) {
                updateBtn.disabled = false;
                updateBtn.style.opacity = 1;
            } else {
                updateBtn.disabled = true;
                updateBtn.style.opacity = 0.5;
            }
        }

        [last, first, middle, Birthday, Contact, House].forEach(el => el.addEventListener('input', checkChanges));
        [Suffix, Barangay].forEach(el => el.addEventListener('change', checkChanges));
        document.querySelectorAll('input[name="Sex"]').forEach(r => r.addEventListener('change', checkChanges));
    </script>
    </form>
    <style>
        .logo {
            width: 30px;
            height: 30px;
            vertical-align: middle;
            margin-right: 6px;
        }
    </style>

    <!-- Prescription Table -->
    <?php if ($char > 0): ?>
        <?php
        $sql = "SELECT p.*, CONCAT(d.Last_name, ', ', d.First_name, ' ', d.Middle_name) AS DoctorName
    FROM prescription AS p
    LEFT JOIN doctors AS d ON p.License_number = d.License_number
    WHERE p.Patient_id = $char
    ORDER BY p.Date DESC";
        $result = mysqli_query($conn, $sql) or die(mysqli_error($conn));
        $total_rows = mysqli_num_rows($result);
        $needScroll = $total_rows >= 10;

        // Get today's date for comparison
        $today = new DateTime();
        $todayStr = $today->format('Y-m-d');

        // Check if patient has sex/gender
        $hasSex = !empty($ch['Sex']);
        ?>

        <div style='width: 100%; margin: 0 auto;'>
            <!-- Main table with headers -->
            <table align='center' border='1' cellpadding='6' cellspacing='0' width='100%' bgcolor='$tablebg' style='border-collapse:collapse; font-family:Arial, sans-serif; font-size:14px;'>
                <!-- Header row -->
                <tr style='background-color:#e6f0ff;'>
                    <th colspan='5' style='text-align:left; padding:12px;'>
                        <span style='font-weight:bold; color:black; font-size:16px;'>Prescriptions</span>

                        <?php if ($hasBirthday && $hasSex): ?>
                            <a href='#' onclick='openPrescriptionModal()' style='background-color:#3CB371; color:white; border:none; padding:8px 14px; border-radius:6px; text-decoration:none; font-weight:bold; margin-left:10px; font-size:14px;'>
                                Add Prescription</a>
                        <?php else: ?>
                            <button style='background-color:#cccccc; color:#666; border:none; padding:8px 14px; border-radius:6px; font-weight:bold; margin-left:10px; font-size:14px; cursor:not-allowed;'
                                title='Cannot add prescription: <?php
                                                                if (!$hasBirthday && !$hasSex) {
                                                                    echo "Patient birthday and sex are missing";
                                                                } elseif (!$hasBirthday) {
                                                                    echo "Patient birthday is missing";
                                                                } elseif (!$hasSex) {
                                                                    echo "Patient sex/gender is missing";
                                                                }
                                                                ?>'>
                                Add Prescription</button>
                            <span style='color: #dc3545; font-size: 12px; margin-left: 10px;'>
                                ⚠ <?php
                                    if (!$hasBirthday && !$hasSex) {
                                        echo "Add patient birthday and select sex first to create a prescription";
                                    } elseif (!$hasBirthday) {
                                        echo "Add patient birthday first to create a prescription";
                                    } elseif (!$hasSex) {
                                        echo "Select patient sex (MALE/FEMALE) to create a prescription";
                                    }
                                    ?>
                            </span>
                        <?php endif; ?>
                    </th>
                </tr>

                <!-- Column headers -->
                <tr style='background-color:#f8f9fa; text-align:center; font-weight:bold;'>
                    <th style='width:15%;'>Date</th>
                    <th style='width:25%;'>Doctor</th>
                    <th style='width:15%;'>Age</th>
                    <th style='width:15%;'>Refill Day</th>
                    <th style='width:20%;'>Actions</th>
                </tr>
            </table>

            <!-- Scrollable content area (only if 10+ rows) -->
            <?php if ($needScroll): ?>
                <div style='max-height: 400px; overflow-y: auto; border: 1px solid #ccc; border-top: none;'>
                <?php endif; ?>

                <!-- Content table -->
                <table align='center' border='1' cellpadding='6' cellspacing='0' width='100%' bgcolor='$tablebg' style='border-collapse:collapse; font-family:Arial, sans-serif; font-size:14px; <?php if ($needScroll) echo 'border-top: none;'; ?>'>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($result) > 0) {
                            $row_count = 0;
                            while ($row = mysqli_fetch_assoc($result)) {
                                $row_count++;
                                $prescriptionDate = $row['Date'];
                                $isPastDate = (strtotime($prescriptionDate) < strtotime($todayStr));
                                $canEdit = !$isPastDate && ($row['Prescription_id'] == $latestPrescriptionId);
                        ?>
                                <tr style='text-align:center;'>
                                    <td style='width:15%; text-align:left; padding-left:10px;'>
                                        <div style='display:flex; align-items:center; gap:5px;'>
                                            <?php
                                            $creation_type = isset($row['Creation_type']) ? $row['Creation_type'] : 'MANUAL';

                                            if ($creation_type == 'BULK') {
                                                echo '<img src="img/printer_icon.png" 
                                              alt="Bulk Print" 
                                              title="Created via Bulk Print"
                                              style="width:18px; height:18px; margin-right:3px;">';
                                            } else {
                                                echo '<img src="img/prescription_manual_icon.png" 
                                              alt="Manual Entry" 
                                              title="Created via Manual Entry"
                                              style="width:18px; height:20px; margin-right:3px;">';
                                            }

                                            ?>
                                            <?php echo htmlspecialchars($row['Date']); ?>
                                        </div>
                                    </td>
                                    <td style='width:25%;'><?php echo htmlspecialchars($row['DoctorName']); ?></td>
                                    <td style='width:15%;'><?php echo htmlspecialchars($row['Age']); ?></td>
                                    <td style='width:15%;'><?php echo htmlspecialchars($row['Refill_day']); ?></td>
                                    <td style='width:20%; text-align:center;'>
                                        <div style='display:flex; gap:7px; justify-content:left; align-items:center;'>
                                            <a href='Pdfs/generate_pdf.php?prescription_id=<?php echo $row['Prescription_id']; ?>'
                                                target='_blank'
                                                style='background-color:#007bff; color:white; border:none; padding:6px 10px; font-size:13px; border-radius:4px; font-weight:bold; text-decoration:none;'>
                                                <img src="img/pdf_icon.png" alt="PDF Icon" style="vertical-align:middle; margin-right:5px; height:16px; width:16px;">Prescription
                                            </a>

                                            <?php if ($canEdit): ?>
                                                <button onclick='openEditPrescriptionModal(<?php echo $row['Prescription_id']; ?>)'
                                                    style='background-color:#ffc107; color:#000; border:none; padding:6px 10px; font-size:13px; border-radius:4px; font-weight:bold; cursor:pointer;'>
                                                    Edit
                                                </button>
                                            <?php else: ?>
                                                <button style='background-color:#cccccc; color:#666; border:none; padding:6px 10px; font-size:13px; border-radius:4px; font-weight:bold; cursor:not-allowed;'
                                                    title="<?php
                                                            if ($isPastDate) {
                                                                echo 'Cannot edit past prescriptions';
                                                            } elseif ($row['Prescription_id'] != $latestPrescriptionId) {
                                                                echo 'Only latest prescription can be edited';
                                                            } else {
                                                                echo 'Edit not available';
                                                            }
                                                            ?>">
                                                    Edit
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan='5' style='text-align:center; padding:10px;'>No prescriptions found.</td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>

                <?php if ($needScroll): ?>
                </div>
                <div style='text-align:center; padding:8px; color:#666; font-style:italic; background-color: #f8f9fa; border: 1px solid #ccc; border-top: none;'>
                    <?php echo $total_rows; ?> prescriptions
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Add Prescription Modal -->
    <div id="prescriptionModal" style="display:none; position:fixed; top:0px; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; padding:20px; border-radius:8px; width:800px; box-shadow:0 0 10px rgba(0,0,0,0.3); position:relative;">
            <h3 style="margin-top:0;">Add Prescription</h3>
            <form id="addPrescriptionForm" method="post" action="prescriptiontransact.php">
                <input type="hidden" name="Patient_id" value="<?php echo $char; ?>">

                <input type="display" name="PatientName" value="<?php echo $patientName; ?>" readonly style="width:100%; border:none; border-bottom:1px solid #000; background-color:transparent; padding:4px 0; font-size:14px; color:#000;">
                <br>
                <input type="display" name="PatientAddress" value="<?php echo $patientAddress; ?>" readonly style="width:100%; border:none; border-bottom:1px solid #000; background-color:transparent; padding:4px 0; font-size:14px; color:#000;">
                <br>

                <div style="display:flex; align-items:center; margin-bottom:5px; margin-top:5px;">
                    <label for="Date" style="width:60px; white-space:nowrap; font-size:13px;">
                        Date: <span style="color:red;">*</span>
                    </label>
                    <input type="date" id="Date" name="Date"
                        value="<?php echo date('Y-m-d'); ?>"
                        min="<?php echo date('Y-m-d'); ?>"
                        required
                        style="flex:1; padding:6px; border:1px solid #000; border-radius:4px;">

                    <!-- You can remove this hidden input since the visible field will now submit the date -->

                    <label for="refill_day" style="width:100px; margin-left:220px; font-size:13px;">
                        Refill Day: <span style="color:red;">*</span>
                    </label>

                    <select id="refill_day" name="refill_day" required
                        style="flex:1; padding:6px; border:1px solid #000; border-radius:4px;">
                        <option value="">Select day</option>
                        <?php
                        for ($i = 1; $i <= 31; $i++) {
                            $selected = ($latestRefillDay == $i) ? 'selected' : '';
                            echo "<option value='$i' $selected>$i</option>";
                        }
                        ?>
                    </select>
                    <?php if ($latestRefillDay): ?>
                        <span style="margin-left:10px; font-size:12px; color:#3CB371; font-style:italic;">
                            (Auto-filled from latest prescription)
                        </span>
                    <?php endif; ?>
                </div>

                <div style="display:flex; align-items:center; margin-bottom:5px; white-space:nowrap;">
                    <label style="width:50px; margin-right:8px; font-size:13px;">
                        Doctor: <span style="color:red;">*</span>
                    </label>

                    <input list="doctorsList"
                        id="doctorName"
                        name="DoctorName"
                        required
                        style="flex:2; border:1px solid #000; border-radius:4px; padding:6px; font-size:14px;"
                        placeholder="Type doctor name"
                        oninput="this.value = this.value.toUpperCase()">

                    <label style="width:80px; margin-left:12px; font-size:13px;">License No:</label>
                    <input type="text"
                        id="doctorLicense"
                        name="License_number"
                        placeholder="Auto-filled"
                        readonly
                        style="flex:1; border:1px solid #000; border-radius:4px; padding:6px; font-size:14px; background:#f3f3f3;">

                    <!-- ADD THIS PTR NUMBER FIELD -->
                    <label style="width:80px; margin-left:12px; font-size:13px;">PTR No:</label>
                    <input type="text"
                        id="doctorPtr"
                        name="Ptr_number"
                        placeholder="Auto-filled"
                        readonly
                        style="flex:1; border:1px solid #000; border-radius:4px; padding:6px; font-size:14px; background:#f3f3f3;">

                    <datalist id="doctorsList">
                        <?php
                        $docQuery = "SELECT License_number, Ptr_number, Last_name, First_name, Middle_name
                FROM doctors
                WHERE is_active = 1
                ORDER BY Last_name ASC";
                        $docResult = mysqli_query($conn, $docQuery);

                        while ($doc = mysqli_fetch_assoc($docResult)) {
                            $DoctorName = trim($doc['Last_name'] . ', ' . $doc['First_name'] . ' ' . $doc['Middle_name']);
                            $LicenseNo  = htmlspecialchars($doc['License_number']);
                            $PtrNo      = htmlspecialchars($doc['Ptr_number'] ?? '');
                            echo "<option value=\"{$DoctorName}\" data-license=\"{$LicenseNo}\" data-ptr=\"{$PtrNo}\"></option>";
                        }
                        ?>
                    </datalist>
                </div>
                <div id="doctorError" style="color:red; font-size:12px; margin-top:2px; display:none;">Doctor not found</div>

                <h3>Medicines</h3>
                <div style="display:flex; justify-content:flex-start; align-items:center; margin-bottom:10px;">
                    <button type="button" onclick="addMedicine()" style="background:#3CB371; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">+ Add Medicine</button>
                </div>
                <div id="medicineContainerWrapper" style="max-height:320px; overflow-y:auto; padding:8px; border:1px solid #ddd; border-radius:8px; background:#fafafa; margin-bottom:12px;">
                    <div id="medicineContainer"></div>
                </div>

                <input type="hidden" name="Age" value="<?php echo $patientAge; ?>">

                <button type="button" onclick="closePrescriptionModal()" style="background:#ccc; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">Cancel</button>
                <button type="submit" name="action" value="Add Prescription" style="background:#3CB371; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">Save</button>
            </form>
        </div>
    </div>

    <!-- Edit Prescription Modal -->
    <div id="editPrescriptionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; padding:20px; border-radius:8px; width:800px; box-shadow:0 0 10px rgba(0,0,0,0.3); position:relative;">
            <h3 style="margin-top:0;">Edit Prescription</h3>
            <form id="editPrescriptionForm" method="post" action="prescriptiontransact.php">
                <input type="hidden" name="Prescription_id" id="editPrescriptionId">
                <input type="hidden" name="Patient_id" value="<?php echo $char; ?>">

                <input type="display" name="PatientName" value="<?php echo $patientName; ?>" readonly style="width:100%; border:none; border-bottom:1px solid #000; background-color:transparent; padding:4px 0; font-size:14px; color:#000;">
                <br>
                <input type="display" name="PatientAddress" value="<?php echo $patientAddress; ?>" readonly style="width:100%; border:none; border-bottom:1px solid #000; background-color:transparent; padding:4px 0; font-size:14px; color:#000;">
                <br>

                <div style="display:flex; align-items:center; margin-bottom:5px; margin-top:5px;">
                    <label for="editDateDisplay" style="width:60px; white-space:nowrap; font-size:13px;">
                        Date:
                    </label>
                    <input type="text" id="editDateDisplay"
                        readonly
                        style="flex:1; padding:6px; border:1px solid #ccc; border-radius:4px; background-color:#f5f5f5;">

                    <!-- Hidden input for form submission -->
                    <input type="hidden" id="editDate" name="Date">

                    <label for="editRefillDay" style="width:100px; margin-left:220px; font-size:13px;">
                        Refill Day: <span style="color:red;">*</span>
                    </label>

                    <select id="editRefillDay" name="refill_day" required
                        style="flex:1; padding:6px; border:1px solid #000; border-radius:4px;">
                        <option value="">Select day</option>
                        <?php
                        for ($i = 1; $i <= 31; $i++) {
                            echo "<option value='$i'>$i</option>";
                        }
                        ?>
                    </select>
                </div>

                <div style="display:flex; align-items:center; margin-bottom:5px; white-space:nowrap;">
                    <label style="width:50px; margin-right:8px; font-size:13px;">
                        Doctor: <span style="color:red;">*</span>
                    </label>

                    <input list="editDoctorsList"
                        id="editDoctorName"
                        name="DoctorName"
                        required
                        style="flex:2; border:1px solid #000; border-radius:4px; padding:6px; font-size:14px;"
                        placeholder="Type doctor name"
                        oninput="this.value = this.value.toUpperCase()">

                    <label style="width:80px; margin-left:12px; font-size:13px;">License No:</label>
                    <input type="text"
                        id="editDoctorLicense"
                        name="License_number"
                        readonly
                        style="flex:1; border:1px solid #000; border-radius:4px; padding:6px; font-size:14px; background:#f3f3f3;">

                    <!-- ADD THIS PTR NUMBER FIELD -->
                    <label style="width:80px; margin-left:12px; font-size:13px;">PTR No:</label>
                    <input type="text"
                        id="editDoctorPtr"
                        name="Ptr_number"
                        readonly
                        style="flex:1; border:1px solid #000; border-radius:4px; padding:6px; font-size:14px; background:#f3f3f3;">

                    <datalist id="editDoctorsList">
                        <?php
                        $docQuery = "SELECT License_number, Ptr_number, Last_name, First_name, Middle_name
                     FROM doctors
                     WHERE is_active = 1
                     ORDER BY Last_name ASC";
                        $docResult = mysqli_query($conn, $docQuery);

                        while ($doc = mysqli_fetch_assoc($docResult)) {
                            $DoctorName = trim($doc['Last_name'] . ', ' . $doc['First_name'] . ' ' . $doc['Middle_name']);
                            $LicenseNo  = htmlspecialchars($doc['License_number']);
                            $PtrNo      = htmlspecialchars($doc['Ptr_number'] ?? '');
                            echo "<option value=\"{$DoctorName}\" data-license=\"{$LicenseNo}\" data-ptr=\"{$PtrNo}\"></option>";
                        }
                        ?>
                    </datalist>
                </div>
                <div id="editDoctorError" style="color:red; font-size:12px; margin-top:2px; display:none;">Doctor not found</div>

                <h3>Medicines</h3>
                <div style="display:flex; justify-content:flex-start; align-items:center; margin-bottom:10px;">
                    <button type="button" onclick="addEditMedicine()" style="background:#3CB371; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">+ Add Medicine</button>
                </div>
                <div id="editMedicineContainerWrapper" style="max-height:320px; overflow-y:auto; padding:8px; border:1px solid #ddd; border-radius:8px; background:#fafafa; margin-bottom:12px;">
                    <div id="editMedicineContainer"></div>
                </div>

                <input type="hidden" name="Age" value="<?php echo $patientAge; ?>">

                <button type="button" onclick="closeEditPrescriptionModal()" style="background:#ccc; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">Cancel</button>
                <button type="submit" name="action" value="Update Prescription" style="background:#ffc107; color:#000; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;" onclick="return confirm('Are you sure you want to update this prescription?')">Update</button>
            </form>
        </div>
    </div>

    <!-- JavaScript for modals -->
    <script>
        const referenceMedicines = <?php echo json_encode($refMeds); ?>;
        let latestMeds = <?php echo json_encode($latestMeds); ?>;
        let Medicines = [];
        let editMedicines = [];

        const medicineContainer = document.getElementById('medicineContainer');
        const editMedicineContainer = document.getElementById('editMedicineContainer');
        const doctorInput = document.getElementById('doctorName');
        const licenseInput = document.getElementById('doctorLicense');
        const doctorOptions = document.querySelectorAll('#doctorsList option');
        const editDoctorInput = document.getElementById('editDoctorName');
        const editLicenseInput = document.getElementById('editDoctorLicense');

        // ========== ADD PRESCRIPTION MODAL FUNCTIONS ==========
        function openPrescriptionModal() {
            document.getElementById('prescriptionModal').style.display = 'flex';
            Medicines = latestMeds.length > 0 ? JSON.parse(JSON.stringify(latestMeds)) : [{
                Medicine_name: '',
                Dose: '',
                Form: '',
                Frequency: '',
                Quantity: ''
            }];
            renderMedicines();
        }

        function closePrescriptionModal() {
            document.getElementById('prescriptionModal').style.display = 'none';
        }

        function renderMedicines() {
            medicineContainer.innerHTML = '';
            Medicines.forEach((med, i) => {
                const uniqueNames = [...new Set(referenceMedicines.map(m => m.Medicine_name))];
                const options = uniqueNames.map(n => `<option value="${n}"></option>`).join('');
                const doses = med.Medicine_name ? [...new Set(referenceMedicines.filter(r => r.Medicine_name === med.Medicine_name).map(r => r.Dose))] : [];
                const forms = med.Medicine_name ? [...new Set(referenceMedicines.filter(r => r.Medicine_name === med.Medicine_name).map(r => r.Form))] : [];

                const doseOptionsHtml = doses.map(d => {
                    const isSelected = d === med.Dose;
                    return `<option value="${d}" ${isSelected ? 'selected' : ''}>${d}</option>`;
                }).join('');

                const formOptionsHtml = forms.map(f => {
                    const isSelected = f === med.Form;
                    return `<option value="${f}" ${isSelected ? 'selected' : ''}>${f}</option>`;
                }).join('');

                medicineContainer.innerHTML += `
        <div class="medicine-item" style="background:#f3f6ff; border:1px solid #ccd4ff; border-radius:10px; padding:10px 12px; margin-bottom:10px;">
            <div style="font-weight:bold; font-size:16px; color:#2a3f85; margin-bottom:8px;">
                Medicine ${i + 1}.
                <button type="button" onclick="removeMedicine(${i})" style="background:#b32d2e; color:white; border:none; padding:4px; border-radius:4px; font-size:12px; height:25px; width:25px; display:flex; align-items:center; justify-content:center; margin-left:auto; flex-shrink:0; position:relative; top:-5px;">X</button>
                <input list="medicineList${i}" name="Medicine[${i}][Medicine_name]" value="${med.Medicine_name}" placeholder="Type to search..." onchange="checkMedicine(this, ${i})" style="width:92%; padding:8px; font-size:15px;" required>
                <datalist id="medicineList${i}">${options}</datalist>
            </div>
            <div style="display:inline-block; width:15%; margin-right:8px;">
                <label>Dosage</label>
                <select name="Medicine[${i}][Dose]" style="width:100%; padding:8px;" required>
                    <option value="">-- Select Dose --</option>
                    ${doseOptionsHtml}
                </select>
            </div>
            <div style="display:inline-block; width:25%; margin-right:8px;">
                <label>Form</label>
                <select name="Medicine[${i}][Form]" style="width:100%; padding:8px;" required>
                    <option value="">-- Select Form --</option>
                    ${formOptionsHtml}
                </select>
            </div>
      <div style="display:inline-block; width:50%; margin-right:8px;">
    <label>Frequency</label>
    <input type="text" name="Medicine[${i}][Frequency]" value="${med.Frequency || ''}" oninput="this.value = this.value.toUpperCase()" style="width:100%; padding:8px;" required>
</div>
            <div style="display:inline-block; width:10%; margin-right:8px;">
                <label>Qty</label>
                <input type="number" name="Medicine[${i}][Quantity]" value="${med.Quantity || ''}" style="width:100%; padding:8px;" required>
            </div>
        </div>`;
            });
        }

        function checkMedicine(input, index) {
            const name = input.value.trim();
            const medRef = referenceMedicines.find(m => m.Medicine_name.toLowerCase() === name.toLowerCase());
            if (!medRef) {
                alert(`Medicine "${name}" not found!`);
                input.value = '';
                Medicines[index] = {
                    Medicine_name: '',
                    Dose: '',
                    Form: '',
                    Frequency: '',
                    Quantity: ''
                };
                const parentDiv = input.closest('.medicine-item');
                parentDiv.querySelector(`select[name="Medicine[${index}][Dose]"]`).innerHTML = `<option value="">-- Select Dose --</option>`;
                parentDiv.querySelector(`select[name="Medicine[${index}][Form]"]`).innerHTML = `<option value="">-- Select Form --</option>`;
                return;
            }

            Medicines[index].Medicine_name = medRef.Medicine_name;
            const parentDiv = input.closest('.medicine-item');

            const doses = [...new Set(referenceMedicines.filter(r => r.Medicine_name === medRef.Medicine_name).map(r => r.Dose))];
            const doseSelect = parentDiv.querySelector(`select[name="Medicine[${index}][Dose]"]`);
            doseSelect.innerHTML = `<option value="">-- Select Dose --</option>`;
            doses.forEach(d => {
                const isSelected = d === Medicines[index].Dose;
                doseSelect.innerHTML += `<option value="${d}" ${isSelected ? 'selected' : ''}>${d}</option>`;
            });

            const forms = [...new Set(referenceMedicines.filter(r => r.Medicine_name === medRef.Medicine_name).map(r => r.Form))];
            const formSelect = parentDiv.querySelector(`select[name="Medicine[${index}][Form]"]`);
            formSelect.innerHTML = `<option value="">-- Select Form --</option>`;
            forms.forEach(f => {
                const isSelected = f === Medicines[index].Form;
                formSelect.innerHTML += `<option value="${f}" ${isSelected ? 'selected' : ''}>${f}</option>`;
            });
        }

        function saveCurrentInputs() {
            document.querySelectorAll('#medicineContainer .medicine-item').forEach((div, i) => {
                Medicines[i] = {
                    Medicine_name: div.querySelector(`[name="Medicine[${i}][Medicine_name]"]`)?.value || '',
                    Dose: div.querySelector(`[name="Medicine[${i}][Dose]"]`)?.value || '',
                    Form: div.querySelector(`[name="Medicine[${i}][Form]"]`)?.value || '',
                    Frequency: div.querySelector(`[name="Medicine[${i}][Frequency]"]`)?.value || '',
                    Quantity: div.querySelector(`[name="Medicine[${i}][Quantity]"]`)?.value || ''
                };
            });
        }

        function addMedicine() {
            saveCurrentInputs();
            Medicines.push({
                Medicine_name: '',
                Dose: '',
                Form: '',
                Frequency: '',
                Quantity: ''
            });
            renderMedicines();
        }

        function removeMedicine(i) {
            saveCurrentInputs();
            Medicines.splice(i, 1);
            renderMedicines();
        }

        // Doctor search for add modal
        doctorInput.addEventListener('input', function() {
            let found = false;
            licenseInput.value = '';
            document.getElementById('doctorPtr').value = ''; // Clear PTR field

            doctorOptions.forEach(opt => {
                if (opt.value === doctorInput.value) {
                    licenseInput.value = opt.dataset.license;
                    document.getElementById('doctorPtr').value = opt.dataset.ptr || ''; // Set PTR field
                    found = true;
                }
            });

            if (!found && doctorInput.value !== '') {
                licenseInput.value = '';
                document.getElementById('doctorPtr').value = ''; // Clear PTR field
            }
        });

        // Doctor search for edit modal
        editDoctorInput.addEventListener('input', function() {
            let found = false;
            editLicenseInput.value = '';
            document.getElementById('editDoctorPtr').value = ''; // Clear PTR field

            document.querySelectorAll('#editDoctorsList option').forEach(opt => {
                if (opt.value === editDoctorInput.value) {
                    editLicenseInput.value = opt.dataset.license;
                    document.getElementById('editDoctorPtr').value = opt.dataset.ptr || ''; // Set PTR field
                    found = true;
                }
            });

            if (!found && editDoctorInput.value !== '') {
                editLicenseInput.value = '';
                document.getElementById('editDoctorPtr').value = ''; // Clear PTR field
            }
        });
        // ========== EDIT PRESCRIPTION MODAL FUNCTIONS ==========
        function openEditPrescriptionModal(prescriptionId) {
            fetch('fetch_prescription_for_edit.php?prescription_id=' + prescriptionId) // Fixed: using correct file name
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateEditModal(data);
                        document.getElementById('editPrescriptionModal').style.display = 'flex';
                    } else {
                        alert('Error loading prescription data: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading prescription data. Please check console.');
                });
        }

        function populateEditModal(data) {
            document.getElementById('editPrescriptionId').value = data.prescription.Prescription_id;

            // Format date for display
            let dateValue = data.prescription.Date;
            let displayDate = dateValue;

            // Convert YYYY-MM-DD to a more readable format if needed
            if (dateValue) {
                const dateObj = new Date(dateValue);
                if (!isNaN(dateObj.getTime())) {
                    // Format as MM/DD/YYYY for display
                    displayDate = (dateObj.getMonth() + 1).toString().padStart(2, '0') + '/' +
                        dateObj.getDate().toString().padStart(2, '0') + '/' +
                        dateObj.getFullYear();
                }
            }

            // Set the display field (readonly)
            document.getElementById('editDateDisplay').value = displayDate;

            // Set the hidden field for form submission
            document.getElementById('editDate').value = dateValue;

            document.getElementById('editRefillDay').value = data.prescription.Refill_day;
            document.getElementById('editDoctorName').value = data.doctor.DoctorName;
            document.getElementById('editDoctorLicense').value = data.doctor.License_number;
            document.getElementById('editDoctorPtr').value = data.doctor.Ptr_number || ''; // Add this line

            editMedicines = data.medicines.map(med => ({
                Medicine_id: med.Medicine_id || '',
                Medicine_name: med.Medicine_name || '',
                Dose: med.Dose || '',
                Form: med.Form || '',
                Frequency: med.Frequency || '',
                Quantity: med.Quantity || ''
            }));

            renderEditMedicines();
        }

        function closeEditPrescriptionModal() {
            document.getElementById('editPrescriptionModal').style.display = 'none';
            editMedicines = [];
        }

        function renderEditMedicines() {
            editMedicineContainer.innerHTML = '';
            editMedicines.forEach((med, i) => {
                const uniqueNames = [...new Set(referenceMedicines.map(m => m.Medicine_name))];
                const options = uniqueNames.map(n => `<option value="${n}"></option>`).join('');
                const doses = med.Medicine_name ? [...new Set(referenceMedicines.filter(r => r.Medicine_name === med.Medicine_name).map(r => r.Dose))] : [];
                const forms = med.Medicine_name ? [...new Set(referenceMedicines.filter(r => r.Medicine_name === med.Medicine_name).map(r => r.Form))] : [];

                const doseOptionsHtml = doses.map(d => {
                    const isSelected = d === med.Dose;
                    return `<option value="${d}" ${isSelected ? 'selected' : ''}>${d}</option>`;
                }).join('');

                const formOptionsHtml = forms.map(f => {
                    const isSelected = f === med.Form;
                    return `<option value="${f}" ${isSelected ? 'selected' : ''}>${f}</option>`;
                }).join('');

                editMedicineContainer.innerHTML += `
<div class="medicine-item" style="background:#f3f6ff; border:1px solid #ccd4ff; border-radius:10px; padding:10px 12px; margin-bottom:10px;">
    <div style="font-weight:bold; font-size:16px; color:#2a3f85; margin-bottom:8px;">
        Medicine ${i + 1}.
        <button type="button" onclick="removeEditMedicine(${i})" style="background:#b32d2e; color:white; border:none; padding:4px; border-radius:4px; font-size:12px; height:25px; width:25px; display:flex; align-items:center; justify-content:center; margin-left:auto; flex-shrink:0; position:relative; top:-5px;">X</button>
        <input list="editMedicineList${i}" name="Medicine[${i}][Medicine_name]" value="${med.Medicine_name}" placeholder="Type to search..." onchange="checkEditMedicine(this, ${i})" style="width:92%; padding:8px; font-size:15px;" required>
        <input type="hidden" name="Medicine[${i}][Medicine_id]" value="${med.Medicine_id || ''}">
        <datalist id="editMedicineList${i}">${options}</datalist>
    </div>
    <div style="display:inline-block; width:15%; margin-right:8px;">
        <label>Dosage</label>
        <select name="Medicine[${i}][Dose]" style="width:100%; padding:8px;" required>
            <option val1ue="">-- Select Dose --</option>
            ${doseOptionsHtml}
        </select>
    </div>
    <div style="display:inline-block; width:25%; margin-right:8px;">
        <label>Form</label>
        <select name="Medicine[${i}][Form]" style="width:100%; padding:8px;" required>
            <option value="">-- Select Form --</option>
            ${formOptionsHtml}
        </select>
    </div>
    <div style="display:inline-block; width:50%; margin-right:8px;">
    <label>Frequency</label>
    <input type="text" name="Medicine[${i}][Frequency]" value="${med.Frequency || ''}" oninput="this.value = this.value.toUpperCase()" style="width:100%; padding:8px;" required>
</div>
    <div style="display:inline-block; width:10%; margin-right:8px;">
        <label>Qty</label>
        <input type="number" name="Medicine[${i}][Quantity]" value="${med.Quantity || ''}" style="width:100%; padding:8px;" required>
    </div>
</div>`;
            });
        }

        function checkEditMedicine(input, index) {
            const name = input.value.trim();
            const medRef = referenceMedicines.find(m => m.Medicine_name.toLowerCase() === name.toLowerCase());
            if (!medRef) {
                alert(`Medicine "${name}" not found!`);
                input.value = '';
                editMedicines[index] = {
                    Medicine_id: '',
                    Medicine_name: '',
                    Dose: '',
                    Form: '',
                    Frequency: '',
                    Quantity: ''
                };
                const parentDiv = input.closest('.medicine-item');
                parentDiv.querySelector(`select[name="Medicine[${index}][Dose]"]`).innerHTML = `<option value="">-- Select Dose --</option>`;
                parentDiv.querySelector(`select[name="Medicine[${index}][Form]"]`).innerHTML = `<option value="">-- Select Form --</option>`;
                return;
            }

            editMedicines[index].Medicine_name = medRef.Medicine_name;
            const parentDiv = input.closest('.medicine-item');

            const doses = [...new Set(referenceMedicines.filter(r => r.Medicine_name === medRef.Medicine_name).map(r => r.Dose))];
            const doseSelect = parentDiv.querySelector(`select[name="Medicine[${index}][Dose]"]`);
            doseSelect.innerHTML = `<option value="">-- Select Dose --</option>`;
            doses.forEach(d => {
                const isSelected = d === editMedicines[index].Dose;
                doseSelect.innerHTML += `<option value="${d}" ${isSelected ? 'selected' : ''}>${d}</option>`;
            });

            const forms = [...new Set(referenceMedicines.filter(r => r.Medicine_name === medRef.Medicine_name).map(r => r.Form))];
            const formSelect = parentDiv.querySelector(`select[name="Medicine[${index}][Form]"]`);
            formSelect.innerHTML = `<option value="">-- Select Form --</option>`;
            forms.forEach(f => {
                const isSelected = f === editMedicines[index].Form;
                formSelect.innerHTML += `<option value="${f}" ${isSelected ? 'selected' : ''}>${f}</option>`;
            });
        }

        function saveEditCurrentInputs() {
            document.querySelectorAll('#editMedicineContainer .medicine-item').forEach((div, i) => {
                editMedicines[i] = {
                    Medicine_id: div.querySelector(`input[name="Medicine[${i}][Medicine_id]"]`)?.value || '',
                    Medicine_name: div.querySelector(`[name="Medicine[${i}][Medicine_name]"]`)?.value || '',
                    Dose: div.querySelector(`[name="Medicine[${i}][Dose]"]`)?.value || '',
                    Form: div.querySelector(`[name="Medicine[${i}][Form]"]`)?.value || '',
                    Frequency: div.querySelector(`[name="Medicine[${i}][Frequency]"]`)?.value || '',
                    Quantity: div.querySelector(`[name="Medicine[${i}][Quantity]"]`)?.value || ''
                };
            });
        }

        function addEditMedicine() {
            saveEditCurrentInputs();
            editMedicines.push({
                Medicine_id: '',
                Medicine_name: '',
                Dose: '',
                Form: '',
                Frequency: '',
                Quantity: ''
            });
            renderEditMedicines();
        }

        function removeEditMedicine(i) {
            saveEditCurrentInputs();
            editMedicines.splice(i, 1);
            renderEditMedicines();
        }

        // Doctor search for edit modal
        editDoctorInput.addEventListener('input', function() {
            let found = false;
            editLicenseInput.value = '';

            document.querySelectorAll('#editDoctorsList option').forEach(opt => {
                if (opt.value === editDoctorInput.value) {
                    editLicenseInput.value = opt.dataset.license;
                    found = true;
                }
            });

            if (!found && editDoctorInput.value !== '') {
                editLicenseInput.value = '';
            }
        });

        // Form validation for add modal
        document.getElementById('addPrescriptionForm').addEventListener('submit', function(e) {
            if (doctorInput.value.trim() === '' || licenseInput.value.trim() === '') {
                alert('Please select a valid doctor from the list.');
                e.preventDefault();
                doctorInput.focus();
            }
        });

        // Form validation for edit modal
        document.getElementById('editPrescriptionForm').addEventListener('submit', function(e) {
            if (editDoctorInput.value.trim() === '' || editLicenseInput.value.trim() === '') {
                alert('Please select a valid doctor from the list.');
                e.preventDefault();
                editDoctorInput.focus();
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var yellowCardField = document.getElementsByName('Yellow_card_nos')[0];
            var membershipField = document.getElementById('Membership_type');
            var ycExpiryField = document.getElementsByName('Yc_expiry_date')[0];

            if (yellowCardField && membershipField) {
                function toggleMembershipField() {
                    var yellowCardValue = yellowCardField.value.trim();

                    if (yellowCardValue === '') {
                        membershipField.disabled = true;
                        membershipField.placeholder = 'Enter Yellow Card nos.';
                        membershipField.style.backgroundColor = '#f0f0f0';
                        membershipField.style.color = '#888';
                        membershipField.style.cursor = 'not-allowed';
                        membershipField.value = '';
                        membershipField.required = false;

                        ycExpiryField.disabled = true;
                        ycExpiryField.value = '';
                        ycExpiryField.style.backgroundColor = '#f0f0f0';
                        ycExpiryField.style.color = '#888';
                        ycExpiryField.style.cursor = 'not-allowed';
                        ycExpiryField.required = false;
                    } else {
                        membershipField.disabled = false;
                        membershipField.placeholder = 'Enter membership type';
                        membershipField.style.backgroundColor = '';
                        membershipField.style.color = '';
                        membershipField.style.cursor = '';
                        membershipField.required = true;

                        ycExpiryField.disabled = false;
                        ycExpiryField.style.backgroundColor = '';
                        ycExpiryField.style.color = '';
                        ycExpiryField.style.cursor = '';
                        ycExpiryField.required = true;
                    }
                }

                yellowCardField.addEventListener('input', toggleMembershipField);
                yellowCardField.addEventListener('change', toggleMembershipField);
                yellowCardField.addEventListener('blur', toggleMembershipField);

                toggleMembershipField();

                var form = document.querySelector('form[name="theform"]');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        var yellowCardValue = yellowCardField.value.trim();

                        if (yellowCardValue !== '') {
                            if (membershipField.value.trim() === '') {
                                e.preventDefault();
                                alert('Membership Type is required when Yellow Card No. is filled!');
                                membershipField.focus();
                                return false;
                            }

                            if (ycExpiryField.value.trim() === '') {
                                e.preventDefault();
                                alert('YC Expiry Date is required when Yellow Card No. is filled!');
                                ycExpiryField.focus();
                                return false;
                            }
                        }
                    });
                }
            }
        });
    </script>
<!-- Deactivate Patient Modal -->
<div id="deactivateModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10001; justify-content:center; align-items:center;">
    <div style="background:white; padding:25px; border-radius:8px; width:500px; max-height:80vh; overflow-y:auto;">
        <h3 style="margin-top:0; color:#dc3545; border-bottom:1px solid #eee; padding-bottom:10px;">
            Deactivate Patient
        </h3>

        <div id="patientInfo" style="margin-bottom:15px; padding:10px; background:#f8f9fa; border-radius:5px;">
            <strong>Patient:</strong> <span id="patientName"></span>
        </div>

        <form id="deactivateForm" method="post" action="transact/deactivate_transact.php">
            <input type="hidden" id="patientId" name="patient_id">

            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">Date of Deactivation:</label>
                <input type="date" id="deactivationDate" name="deactivation_date"
                    value="<?php echo date('Y-m-d'); ?>"
                    style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;" required>
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">REASON:</label>
                <select id="deactivationReason" name="reason"
                    style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:14px;"
                    required>
                    <option value="">SELECT REASON FOR DEACTIVATION</option>
                    <option value="DECEASED">DECEASED</option>
                    <option value="PATIENT UNLOCATED">PATIENT UNLOCATED</option>
                    <option value="EXPIRED MHP CARD">EXPIRED MHP CARD</option>
                    <option value="REFUSED DELIVERY">REFUSED DELIVERY</option>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:5px; font-weight:bold;">DETAILS:</label>
                <textarea id="deactivationRemarks" name="remarks"
                    style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; min-height:100px;
                    text-transform: uppercase; font-size:14px;"
                    placeholder="ENTER DETAILS FOR DEACTIVATION..."
                    oninput="this.value = this.value.toUpperCase()" required></textarea>
            </div>
            
            <div style="margin-bottom:20px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <label style="font-weight:bold;">SET BY:</label>
                    <div style="padding:6px 12px; background-color:#e9ecef; border-radius:4px; font-weight:bold; color:#263F73;">
                        <?php echo isset($_SESSION['First_name']) ? htmlspecialchars($_SESSION['First_name']) : 'Unknown'; ?>
                    </div>
                </div>
                <input type="hidden" name="is_set_by" value="<?php echo isset($_SESSION['First_name']) ? htmlspecialchars($_SESSION['First_name']) : 'Unknown'; ?>">
            </div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="submit"
                    style="flex:1; padding:10px; background:#dc3545; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">
                    Confirm Deactivation
                </button>
                <button type="button" onclick="hideDeactivateModal()"
                    style="flex:1; padding:10px; background:#6c757d; color:white; border:none; border-radius:4px; cursor:pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>// ========== DEACTIVATE MODAL FUNCTIONS ==========
function showDeactivateModal(patientId, lastName, firstName) {
    // Set patient information
    document.getElementById('patientId').value = patientId;
    document.getElementById('patientName').textContent = lastName.toUpperCase() + ', ' + firstName.toUpperCase();

    // Reset form
    document.getElementById('deactivationDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('deactivationReason').value = '';
    document.getElementById('deactivationRemarks').value = '';

    // Show modal
    document.getElementById('deactivateModal').style.display = 'flex';
}

function hideDeactivateModal() {
    document.getElementById('deactivateModal').style.display = 'none';
}

// Color the select options dynamically
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('deactivationReason');
    if (select) {
        const optionsWithColors = [
            { value: "", text: "SELECT REASON FOR DEACTIVATION", color: "#000" },
            { value: "DECEASED", text: "DECEASED", color: "#dc3545" },
            { value: "PATIENT UNLOCATED", text: "PATIENT UNLOCATED", color: "#fd7e14" },
            { value: "EXPIRED MHP CARD", text: "EXPIRED MHP CARD", color: "#ffc107" },
            { value: "REFUSED DELIVERY", text: "REFUSED DELIVERY", color: "#6c757d" },
        ];

        // Clear and rebuild with colored text
        select.innerHTML = '';
        optionsWithColors.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.value ? option.text : option.text;
            opt.style.color = option.color;
            opt.style.fontWeight = option.value ? 'normal' : 'italic';
            select.appendChild(opt);
        });
    }
});

// AJAX form submission for deactivation
if (document.getElementById('deactivateForm')) {
    document.getElementById('deactivateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Confirm with user
        if (!confirm('Are you sure you want to deactivate this patient?')) {
            return false;
        }
        
        const formData = new FormData(this);
        
        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Processing...';
        submitBtn.disabled = true;
        
        // Send request
        fetch('transact/deactivate_transact.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Response:', data);
            
            if (data.success) {
                // Show success message
                alert(data.message || 'Patient deactivated successfully!');
                
                // Close the modal
                hideDeactivateModal();
                
                // Redirect to patiententry.php
                window.location.href = 'patiententry.php';
            } else {
                alert('Error: ' + (data.message || 'Failed to deactivate patient'));
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDeactivateModal();
    }
});

// Close modal when clicking outside
if (document.getElementById('deactivateModal')) {
    document.getElementById('deactivateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideDeactivateModal();
        }
    });
}</script>
</body>

</html>