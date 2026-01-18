<?php
session_start();
require('../Config/Config.php');

// Connect to MySQL
$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect to MySQL database.');

// Get parameters
$bulk_ids = isset($_GET['bulk_ids']) ? trim($_GET['bulk_ids']) : '';
$dosearch = isset($_GET['dosearch']) ? trim($_GET['dosearch']) : '';

// Validate parameters
if (empty($bulk_ids)) {
    die('No prescription IDs provided.');
}

if (empty($dosearch)) {
    die('No refill day specified.');
}

// Sanitize input
$dosearch = htmlspecialchars($dosearch);

// Convert comma-separated IDs to array and validate
$id_array = explode(',', $bulk_ids);
$id_array = array_filter($id_array, function($id) {
    return is_numeric(trim($id)) && intval($id) > 0;
});

if (empty($id_array)) {
    die('Invalid prescription IDs provided.');
}

// Create placeholders for prepared statement
$id_placeholders = implode(',', array_fill(0, count($id_array), '?'));
$id_types = str_repeat('i', count($id_array));

// Get prescription details with patient information
$query = "SELECT 
    CONCAT(COALESCE(pat.Last_name, ''), ', ', COALESCE(pat.First_name, ''), ' ', COALESCE(pat.Middle_name, '')) AS Patient_name,
    COALESCE(pat.Barangay, '') AS Barangay,
    COALESCE(pat.House_nos_street_name, '') AS House_nos_street_name
FROM prescription p
LEFT JOIN patient_details pat ON p.Patient_id = pat.Patient_id
WHERE p.Prescription_id IN ($id_placeholders)
ORDER BY pat.Last_name, pat.First_name";

$patients = [];
try {
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('Failed to prepare SQL statement: ' . mysqli_error($conn));
    }
    
    // Bind parameters dynamically
    mysqli_stmt_bind_param($stmt, $id_types, ...$id_array);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to execute SQL statement: ' . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $patients[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

if (empty($patients)) {
    die('No patient records found for the provided prescription IDs.');
}

// Include FPDF library
require(__DIR__ . '/../fpdf186/fpdf.php');
// Create PDF class extending FPDF
class PDF extends FPDF
{
    private $refillDay;
    
    function setRefillDay($day) {
        $this->refillDay = $day;
    }
    
    // Page header
    function Header()
    {
        // Logo - adjust path as needed
        $logoPath = '../img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 6, 30);
        }
        
        // Move to the right
        $this->Cell(80);
        
        // Title
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(120, 10, 'HOSPICE PATIENT TRANSMITTAL LIST', 0, 0, 'C');
        
        // Line break
        $this->Ln(15);
        
        // Subtitle
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Refill Day: ' . $this->' | Generated: ' . date('F d, Y'), 0, 0, 'C');
        $this->Ln(12);
        
        // Line
        $this->Line(10, 40, 287, 40);
        $this->Ln(5);
    }
    
    // Page footer
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Create PDF instance in Landscape
$pdf = new PDF('P', 'mm', 'A4');
$pdf->setRefillDay($dosearch);
$pdf->AliasNbPages();
$pdf->AddPage();

// Set font for content
$pdf->SetFont('Arial', '', 10);

// Add spacing after header
$pdf->Ln(10);

// Table header
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(38, 63, 115); // Blue color: #263F73
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(15, 10, '#', 1, 0, 'C', true);
$pdf->Cell(100, 10, 'PATIENT NAME', 1, 0, 'C', true);
$pdf->Cell(150, 10, 'COMPLETE ADDRESS', 1, 1, 'C', true);

// Table data
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$counter = 1;
$fill = false;

foreach ($patients as $patient) {
    // Alternate row colors for better readability
    if ($fill) {
        $pdf->SetFillColor(245, 245, 245);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    // Build address
    $address_parts = [];
    if (!empty(trim($patient['House_nos_street_name'] ?? ''))) {
        $address_parts[] = trim($patient['House_nos_street_name']);
    }
    if (!empty(trim($patient['Barangay'] ?? ''))) {
        $address_parts[] = trim($patient['Barangay']);
    }
    $address = strtoupper(implode(', ', $address_parts));
    
    // Add row - note: FPDF doesn't support cell fill with borders in the same way as TCPDF
    // We'll use fill without borders for the cell, then add borders manually
    $pdf->Cell(15, 8, $counter, 'LR', 0, 'C', $fill);
    $pdf->Cell(100, 8, strtoupper($patient['Patient_name']), 'LR', 0, 'L', $fill);
    $pdf->Cell(150, 8, $address, 'LR', 1, 'L', $fill);
    
    // Add horizontal lines between rows
    if ($counter < count($patients)) {
        $y = $pdf->GetY();
        $pdf->Line(10, $y, 287, $y);
    }
    
    $counter++;
    $fill = !$fill;
}

// Close the table with bottom border
$pdf->Cell(265, 0, '', 'T');

// Add summary
$pdf->Ln(15);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'TOTAL PATIENTS: ' . count($patients), 0, 1, 'R');

// Add generation timestamp
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 5, 'Generated on: ' . date('F d, Y h:i A'), 0, 1, 'L');

// Output PDF
$filename = 'Transmittal_Day_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dosearch) . '_' . date('Y-m-d_H-i') . '.pdf';
$pdf->Output('I', $filename); // 'I' sends to browser inline

// Close database connection
mysqli_close($conn);
?>