<?php
session_start();
require('Config/Config.php');

// Check if user is logged in
if (!isset($_SESSION['Username'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if prescription_id is provided
if (!isset($_GET['prescription_id']) || !is_numeric($_GET['prescription_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid prescription ID']);
    exit();
}

$prescription_id = intval($_GET['prescription_id']);

// Database connection
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS);
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}
mysqli_select_db($conn, SQL_DB);

// Fetch prescription data - ADD PTR_number TO SELECT
$sql = "SELECT p.*, 
               CONCAT(d.Last_name, ', ', d.First_name, ' ', d.Middle_name) AS DoctorName,
               d.License_number,
               d.Ptr_number
        FROM prescription p
        LEFT JOIN doctors d ON p.License_number = d.License_number
        WHERE p.Prescription_id = $prescription_id";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Prescription not found']);
    exit();
}

$prescription = mysqli_fetch_assoc($result);

// Fetch medicines for this prescription
$sql = "SELECT m.Medicine_id, m.Medicine_name, m.Dose, m.Form, r.Frequency, r.Quantity
        FROM rx r
        INNER JOIN medicine m ON r.Medicine_id = m.Medicine_id
        WHERE r.Prescription_id = $prescription_id";
$result = mysqli_query($conn, $sql);

$medicines = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $medicines[] = $row;
    }
}

// Prepare doctor data - ADD PTR_number
$doctor = [
    'DoctorName' => $prescription['DoctorName'],
    'License_number' => $prescription['License_number'],
    'Ptr_number' => $prescription['Ptr_number'] ?? '' // Add PTR number here
];

// Remove doctor info from prescription array
unset($prescription['DoctorName']);
unset($prescription['License_number']);
unset($prescription['Ptr_number']); // Remove Ptr_number from prescription array

echo json_encode([
    'success' => true,
    'prescription' => $prescription,
    'doctor' => $doctor,
    'medicines' => $medicines
]);

mysqli_close($conn);
?>