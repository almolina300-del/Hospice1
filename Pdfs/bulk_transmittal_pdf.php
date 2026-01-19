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
$id_array = array_filter($id_array, function ($id) {
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
    private $patientCount;
    private $currentPagePatients = 0;

    function setRefillDay($day)
    {
        $this->refillDay = $day;
    }

    function setPatientCount($count)
    {
        $this->patientCount = $count;
    }

    function resetPageCounter()
    {
        $this->currentPagePatients = 0;
    }

    function incrementPageCounter()
    {
        $this->currentPagePatients++;
    }

    function needsNewPage()
    {
        return $this->currentPagePatients >= 20;
    }

    // Draw table header (reusable method)
    function drawTableHeader()
    {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(38, 63, 115); // Blue color: #263F73
        $this->SetTextColor(255, 255, 255);
        
        // Calculate widths for A4 (210mm width with 10mm margins on each side = 190mm usable width)
        $this->Cell(15, 10, '#', 1, 0, 'C', true);
        $this->Cell(85, 10, 'PATIENT NAME', 1, 0, 'C', true);
        $this->Cell(90, 10, 'ADDRESS', 1, 1, 'C', true);
        
        // Reset text color for data rows
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 7);
    }

    // Page header
    function Header()
    {
        // Logo - adjust path as needed
        $logoPath = '../img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 6, 30);
        }

        // Title
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 15, 'PATIENT TRANSMITTAL LIST', 0, 1, 'C');

        // Total Patients and Date (one below the other)
        $this->SetFont('Arial', '', 12);
        
        // Total Patients - centered
        $this->Cell(0, 8, 'Total Patients: ' . $this->patientCount, 0, 1, 'L');
        
        // Date - centered (below total patients)
        $this->Cell(0, 8, 'Date: ' . date('F d, Y'), 0, 1, 'L');

        // Line break
        $this->Ln(3);

        // Line
        $this->Line(10, $this->GetY(), 200, $this->GetY());
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

// Create PDF instance in Portrait (A4)
$pdf = new PDF('P', 'mm', 'A4');
$pdf->setRefillDay($dosearch);
$pdf->setPatientCount(count($patients));
$pdf->AliasNbPages();

// Add first page
$pdf->AddPage();
$pdf->resetPageCounter();

// Draw table header for first page
$pdf->drawTableHeader();

// Process patients with pagination
$counter = 1;
$fill = false;

foreach ($patients as $patient) {
    // Check if we need a new page
    if ($pdf->needsNewPage()) {
        $pdf->AddPage();
        $pdf->resetPageCounter();
        $pdf->drawTableHeader();
        $fill = false; // Reset fill for new page
    }

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

    // Add row with adjusted widths
    $pdf->Cell(15, 8, $counter, 'LR', 0, 'C', $fill);
    $pdf->Cell(85, 8, strtoupper($patient['Patient_name']), 'LR', 0, 'L', $fill);
    $pdf->Cell(90, 8, $address, 'LR', 1, 'L', $fill);

    // Add horizontal lines between rows
    if ($counter < count($patients)) {
        $y = $pdf->GetY();
        $pdf->Line(10, $y, 200, $y);
    }

    $counter++;
    $fill = !$fill;
    $pdf->incrementPageCounter();
}

// Close the table with bottom border
$pdf->Cell(190, 0, '', 'T');

// Output PDF
$filename = 'Transmittal_Day_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dosearch) . '_' . date('Y-m-d_H-i') . '.pdf';
$pdf->Output('I', $filename); // 'I' sends to browser inline

// Close database connection
mysqli_close($conn);