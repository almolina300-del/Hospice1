<?php
require(__DIR__ . '/../fpdf186/fpdf.php');
require(__DIR__ . '/../Config/Config.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if prescription_id is provided
if (!isset($_GET['prescription_id'])) {
    die("No prescription ID provided");
}

$prescription_id = intval($_GET['prescription_id']);

if ($prescription_id <= 0) {
    die("Invalid prescription ID");
}

try {
    // Fetch prescription data with patient info - WITH AGE CALCULATION
  $sql = "SELECT p.*, 
                   CONCAT(pat.Last_name, ', ', pat.First_name, ' ', COALESCE(pat.Middle_name, '')) as Patient_name,
                   pat.Department as Department,
                   pat.Status_of_appointment as Status_of_appointment,
                   pat.Sex,
                   pat.Birthday,
                   CONCAT(d.First_name, ', ', COALESCE(d.Middle_name, ''), ' ', d.Last_name, ', MD') as Doctor_name,
                   d.License_number as Doctor_license,
                   d.PTR_number as Doctor_PTR,
                   -- Calculate age from birthday
                   TIMESTAMPDIFF(YEAR, pat.Birthday, CURDATE()) as Age
            FROM prescription p 
            LEFT JOIN patient_details pat ON p.Patient_id = pat.Patient_id
            LEFT JOIN doctors d ON p.License_number = d.License_number
            WHERE p.Prescription_id = $prescription_id";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        throw new Exception("Database query failed: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) === 0) {
        throw new Exception("No prescription found with ID: $prescription_id");
    }

    $prescription = mysqli_fetch_assoc($result);

    // Validate required fields (remove Age from required since we calculate it)
    $required_fields = ['Patient_name', 'Department', 'Sex', 'Date', 'Doctor_name', 'Doctor_license'];
    foreach ($required_fields as $field) {
        if (empty($prescription[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Additional validation for birthday
    if (empty($prescription['Birthday'])) {
        throw new Exception("Patient birthday is required to calculate age");
    }

    // Fetch medicines for this prescription - INCLUDING FORM
    $meds_sql = "SELECT m.Medicine_name, m.Dose, m.Form, r.Quantity, r.Frequency, r.Days
                 FROM rx r 
                 JOIN medicine m ON r.Medicine_id = m.Medicine_id 
                 WHERE r.Prescription_id = $prescription_id";
    $meds_result = mysqli_query($conn, $meds_sql);

    if (!$meds_result) {
        throw new Exception("Medicine query failed: " . mysqli_error($conn));
    }

    // Convert medicines to array for pagination
    $medicines = [];
    while ($med = mysqli_fetch_assoc($meds_result)) {
        $medicines[] = $med;
    }

    // Create PDF
    $width = 5.25 * 25.4;
    $height = 8 * 25.4;

    $pdf = new FPDF('P', 'mm', array($width, $height));
    $pdf->SetAutoPageBreak(true, 15);

    // Add a font declaration to ensure proper loading
    $pdf->SetFont('Arial', '', 10);

    // Calculate how many pages we need for medicines
    $medicines_per_page = 5;
    $total_medicines = count($medicines);
    $total_pages = ceil($total_medicines / $medicines_per_page);

    for ($page_num = 1; $page_num <= $total_pages; $page_num++) {

        $pdf->AddPage();

        // Add Page X / Y at top-right
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY(15);
        $pdf->SetX($width - 25);
        $pdf->Cell(20, 5, 'Page ' . $page_num . ' / ' . $total_pages, 0, 0, 'R');

        // Patient Information on EVERY PAGE 
        $pdf->Ln(16);
        $pdf->SetX(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(11, 10, 'Name:');
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(0, 0, 0);

        $patientName = $prescription['Patient_name'];
        $maxNameWidth = 59; // Adjust based on available space

        // Check if name fits
        $nameWidth = $pdf->GetStringWidth($patientName);
        $currentY = $pdf->GetY();

        if ($nameWidth <= $maxNameWidth) {
            // Fits in one line
            $pdf->Cell($maxNameWidth, 10, $patientName, 0, 0, 'L');
        } else {
            // Doesn't fit - need to wrap
            // Find where to break
            $charPos = 0;
            $testString = '';

            for ($j = 0; $j < strlen($patientName); $j++) {
                $testString .= $patientName[$j];
                if ($pdf->GetStringWidth($testString) > $maxNameWidth) {
                    $charPos = $j;
                    break;
                }
            }

            if ($charPos > 0) {
                $firstLineName = substr($patientName, 0, $charPos);
                $remainingName = substr($patientName, $charPos);
            } else {
                $firstLineName = $patientName;
                $remainingName = '';
            }

            // First line of name
            $pdf->Cell($maxNameWidth, 1, $firstLineName, 0, 0, 'L');
        }

        // Age and Sex stay inline with first line
        $pdf->SetX($width - 12); // Move to right side
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(-21, 10, 'Age:', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(8, 10, $prescription['Age'], 0, 0, 'R');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(30, 10, 'Sex:');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(-11, 10, $prescription['Sex'], 0, 1, 'R');

        // Second line of name if needed - MOVE UP to same line as Age/Sex
        if ($nameWidth > $maxNameWidth && !empty($remainingName)) {
            // Get current Y position
            $currentY = $pdf->GetY();

            // Move UP to the same line as Age/Sex (go back to where we were)
            $pdf->SetY($currentY - 7); // Move up 5mm (or whatever the line height is)
            $pdf->SetX(22); // 10 (margin) + 11 (Name: label width) + 1 for adjustment
            $pdf->SetFont('Arial', 'B', 10); // Slightly smaller for second line
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($maxNameWidth, 5, $remainingName, 0, 1, 'L');

            // Reset font for address section and move Y back down
            $pdf->SetY($currentY); // Restore original Y position
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(200, 200, 200);
        }

        // Department and Status section
        $pdf->SetX(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(18, 5, 'Dept/Status:');

        // Get department and status text
        $department = $prescription['Department'] ?? '';
        $status = $prescription['Status_of_appointment'] ?? '';
        $deptStatus = $department . ' / ' . $status;
        $maxDeptWidth = 65; // Width for department/status text

        // Save starting position
        $deptStartX = $pdf->GetX();
        $deptStartY = $pdf->GetY();

        // Set font for department/status
        $pdf->SetFont('ARIAL', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);

        // Check if text fits
        $deptWidth = $pdf->GetStringWidth($deptStatus);

        if ($deptWidth <= $maxDeptWidth) {
            // Write department/status
            $pdf->Cell($maxDeptWidth, 5, $deptStatus, 0, 0, 'L');

            // Write date on same line
            $pdf->SetX(93);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(8, 5, 'Date:', 0, 0, 'R');
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(22, 5, $prescription['Date'], 0, 1, 'R');

            $pdf->Ln(-5);
        } else {
            // Find where to break the text
            $charPos = 0;
            $testString = '';

            for ($j = 0; $j < strlen($deptStatus); $j++) {
                $testString .= $deptStatus[$j];
                if ($pdf->GetStringWidth($testString) > $maxDeptWidth) {
                    $charPos = $j;
                    break;
                }
            }

            $firstLine = $charPos > 0 ? substr($deptStatus, 0, $charPos) : $deptStatus;
            $remaining = $charPos > 0 ? substr($deptStatus, $charPos) : '';

            // Save Y position
            $yPos = $pdf->GetY();

            // First line of department/status
            $pdf->Cell($maxDeptWidth, 1, $firstLine, 0, 0, 'L');

            // Date on same line
            $pdf->SetX(93);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(8, 5, 'Date:', 0, 0, 'R');
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(18, 5, $prescription['Date'], 0, 1, 'R');

            // Second line if needed
            if (!empty($remaining)) {
                $pdf->SetXY($deptStartX, $yPos + 1);
                $pdf->Cell($maxDeptWidth, 5, $remaining, 0, 0, 'L');
            }

            // Adjust spacing
            $pdf->Ln(2);
        }

        // Medicines section - UPDATED to match bulk exactly
        $start_index = ($page_num - 1) * $medicines_per_page;
        $end_index = min($start_index + $medicines_per_page, $total_medicines);

 // Medicines section - FIXED ALIGNMENT
$pdf->Ln(15);
$pdf->SetAutoPageBreak(false);

for ($i = $start_index; $i < $end_index; $i++) {
    $med = $medicines[$i];
    $number = $i + 1 - $start_index; // Number 1-5 for current page

    // Get medicine form
    $medicineForm = $med['Form'] ?? '';

    // Save starting position for this medicine
    $startX = 2;
    $startY = $pdf->GetY();
    $lineHeight = 5; // Height of each line
    $linesCount = 1; // Track how many lines this medicine takes

    // Medicine number
    $pdf->SetX($startX);
    $pdf->SetTextColor(200, 200, 200);
    $pdf->Cell(8, $lineHeight, $number . '.', 0, 0);

    // Set position for medicine name
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 11);

    // Get medicine name and dose
    $medicineName = $med['Medicine_name'] ?? '';
    $dose = $med['Dose'] ?? '';

    // Determine max widths
    $maxMedicineWidth = 85; // Width for medicine name
    $maxDoseWidth = 35; // Width for dose

    // Check if medicine name fits
    $nameWidth = $pdf->GetStringWidth($medicineName);
    $doseWidthActual = $pdf->GetStringWidth($dose);

    // MEDICINE NAME WRAPPING
    if ($nameWidth <= $maxMedicineWidth) {
        // Medicine name fits in one line
        $pdf->SetY($startY);
        $pdf->SetX($startX + 8);
        $pdf->Cell($maxMedicineWidth, $lineHeight, $medicineName, 0, 0, '', false);
    } else {
        // Medicine name doesn't fit - wrap
        $charPos = 0;
        $testString = '';

        for ($j = 0; $j < strlen($medicineName); $j++) {
            $testString .= $medicineName[$j];
            if ($pdf->GetStringWidth($testString) > $maxMedicineWidth) {
                $charPos = $j;
                break;
            }
        }

        if ($charPos > 0) {
            $firstLineName = substr($medicineName, 0, $charPos);
            $remainingName = substr($medicineName, $charPos);
        } else {
            $firstLineName = $medicineName;
            $remainingName = '';
        }

        // First line of medicine name
        $pdf->SetY($startY);
        $pdf->SetX($startX + 8);
        $pdf->Cell($maxMedicineWidth, 1, $firstLineName, 0, 0, '', false);
        
        // Track that we'll need more lines
        if (!empty($remainingName)) {
            $linesCount = 2; // At least 2 lines
        }
    }

    // DOSE - Always position at the starting Y (first line)
    $pdf->SetY($startY);
    $pdf->SetX($startX + 8 + $maxMedicineWidth + 5); // Position after medicine name with gap
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($maxDoseWidth, $lineHeight, $dose, 0, 0, '', false);
    $pdf->SetTextColor(200, 200, 200);
    $pdf->Cell(5, $lineHeight, 'Mg', 0, 1);

    // SECOND LINE of medicine name if needed
    if ($nameWidth > $maxMedicineWidth && !empty($remainingName)) {
        $linesCount = 2;
        $pdf->SetY($startY + $lineHeight);
        $pdf->SetX($startX + 8);
        $pdf->SetFont('Arial', 'B', 9); // Slightly smaller for second line
        $pdf->SetTextColor(0, 0, 0);
        
        // Check if second line also needs wrapping
        $remainingWidth = $pdf->GetStringWidth($remainingName);
        if ($remainingWidth > $maxMedicineWidth) {
            // Need to wrap again
            $charPos2 = 0;
            $testString2 = '';
            for ($j = 0; $j < strlen($remainingName); $j++) {
                $testString2 .= $remainingName[$j];
                if ($pdf->GetStringWidth($testString2) > $maxMedicineWidth) {
                    $charPos2 = $j;
                    break;
                }
            }
            
            if ($charPos2 > 0) {
                $secondLine = substr($remainingName, 0, $charPos2);
                $remainingName = substr($remainingName, $charPos2);
            } else {
                $secondLine = $remainingName;
                $remainingName = '';
            }
            
            $pdf->Cell($maxMedicineWidth, 1, $secondLine, 0, 0, '', false);
            
            // Third line if needed
            if (!empty($remainingName)) {
                $linesCount = 3;
                $pdf->SetY($startY + ($lineHeight * 2));
                $pdf->SetX($startX + 8);
                $pdf->SetFont('Arial', 'B', 8); // Even smaller for third line
                $pdf->Cell($maxMedicineWidth, 1, $remainingName, 0, 0, '', false);
            }
        } else {
            // Second line fits
            $pdf->Cell($maxMedicineWidth, 1, $remainingName, 0, 0, '', false);
        }
    }

    // Checkboxes for form - Position based on number of lines taken
    $checkboxY = $startY + ($linesCount * $lineHeight) + 2; // Small gap after medicine lines
    
    $pdf->SetY($checkboxY);
    $pdf->SetX($startX);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(200, 200, 200);
    $pdf->Cell(8, 4, '', 0, 0);
    $pdf->Cell(18, 4, '[  ] Tablet', 0, 0);
    $pdf->Cell(18, 4, '[  ] Capsule', 0, 0);
    $pdf->Cell(18, 4, '[  ] Syrup', 0, 0);
    $pdf->Cell(18, 4, '[  ] Drops', 0, 0);
    $pdf->Cell(16, 4, '[  ] Others:', 0, 0);

    // Show the medicine form next to Others
    if (!empty($medicineForm)) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetX($pdf->GetX() + 5);
        $pdf->Cell(15, 4, $medicineForm, 0, 1);
    } else {
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(15, 4, '___________', 0, 1);
    }

    // Signa line - Position after checkboxes
    $signaY = $checkboxY + 4;
    
    $pdf->SetY($signaY);
    $pdf->SetX(0.5);
    $pdf->SetTextColor(200, 200, 200);
    $pdf->Cell(10, 4, '', 0, 0);
    $pdf->Cell(13, 4, 'Signa:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetX(23);

    // Get frequency text
    $frequency = $med['Frequency'] ?? '';
    $maxFrequencyWidth = 68;

    // Calculate frequency width for checking
    $freqWidth = $pdf->GetStringWidth($frequency);

    if ($freqWidth <= $maxFrequencyWidth) {
        // Fits in one line
        $pdf->Cell($maxFrequencyWidth, 4, $frequency, 0, 0, '', false);

        // Add "Per day For" and Days on the same line
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(18, 4, 'Per day For', 0, 0);
        $pdf->Cell(-1, 4, '', 0, 0);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(8, 5, ($med['Days'] ?? ''), 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(200, 200, 200);
    } else {
        // Doesn't fit - need to wrap
        $charPosFreq = 0;
        $testStringFreq = '';

        for ($j = 0; $j < strlen($frequency); $j++) {
            $testStringFreq .= $frequency[$j];
            if ($pdf->GetStringWidth($testStringFreq) > $maxFrequencyWidth) {
                $charPosFreq = $j;
                break;
            }
        }

        if ($charPosFreq > 0) {
            $firstLineFreq = substr($frequency, 0, $charPosFreq);
            $remainingFreq = substr($frequency, $charPosFreq);
        } else {
            $firstLineFreq = $frequency;
            $remainingFreq = '';
        }

        // Save starting Y for frequency
        $yFreq = $pdf->GetY();

        // First line of frequency
        $pdf->Cell($maxFrequencyWidth, 1.8, $firstLineFreq, 0, 0, '', false);

        // Add "Per day For" and Days on same line as first part
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(8, 2, 'Per day For', 0, 0);
        $pdf->Cell(12, 2, '', 0, 0);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(8, 4, ($med['Days'] ?? '') . ' days', 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(200, 200, 200);

        // Output remaining frequency on second line (indented)
        if (!empty($remainingFreq)) {
            $pdf->SetXY(18, $yFreq + 4);
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($maxFrequencyWidth, 0, $remainingFreq, 0, 0, '', false);
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(200, 200, 200);
        }
    }

    // Notes line - Position after signa
    $notesY = $signaY + 6;
    
    $pdf->SetY($notesY);
    $pdf->SetX($startX);
    $pdf->Cell(8, 4, '', 0, 0);
    $pdf->Cell(50, 4, 'Note:Total quantity to be dispensed #', 0, 0);
    $pdf->Cell(15, 4, '____', 0, 0, 'R');
    $pdf->Cell(33, 4, 'Quantity to consume #', 0, 0);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(15, 4, ($med['Quantity'] ?? '') . ' Qty', 0, 0, '', false);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 4, '', 0, 1);

    // Set position for next medicine (add gap)
    $nextMedicineY = $notesY + 8;
    $pdf->SetY($nextMedicineY);
}

        // Fill remaining empty medicine forms if less than 5 medicines on the page
        $current_page_medicines = $end_index - $start_index;
        if ($current_page_medicines < $medicines_per_page) {
            $remaining_rows = $medicines_per_page - $current_page_medicines;
            for ($i = 0; $i < $remaining_rows; $i++) {
                $number = $current_page_medicines + $i + 1;

                // Empty medicine form (UPDATED to match bulk)
                $pdf->SetTextColor(200, 200, 200);
                $pdf->SetX(1);
                $pdf->Cell(8, 6, $number . '.', 0, 0);

                // Replace the blank medicine name with the message
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Arial', 'BI', 8);
                $pdf->Cell(95, 6, '-- No Added Prescription --', 0, 0);

                // Reset to original styling
                $pdf->SetTextColor(200, 200, 200);
                $pdf->SetFont('Arial', '', 12);

                $pdf->Cell(12, 6, '', 0, 0);
                $pdf->Cell(5, 6, 'Mg', 0, 1);


                $pdf->Cell(8, 6, '', 0, 0);
                $pdf->SetX(10);
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell(15, 6, '[ ]Tablet', 0, 0);
                $pdf->Cell(18, 6, '[ ]Capsule', 0, 0);
                $pdf->Cell(15, 6, '[ ]Syrup', 0, 0);
                $pdf->Cell(15, 6, '[ ]Drop', 0, 0);
                $pdf->Cell(16, 6, '[ ]Others:', 0, 0);
                $pdf->Cell(15, 6, '___________', 0, 1);

                $pdf->Cell(8, 6, '', 0, 0);
                $pdf->SetX(10);
                $pdf->Cell(15, 6, 'Signa:', 0, 0);
                $pdf->Cell(60, 6, '___________________', 0, 0);
                $pdf->Cell(18, 6, 'Per day', 0, 0);
                $pdf->Cell(4, 6, 'For', 0, 0);
                $pdf->Cell(12, 6, '', 0, 0);
                $pdf->Cell(8, 6, 'Days', 0, 1);

                $pdf->Cell(8, 6, '', 0, 0);
                $pdf->SetX(10);
                $pdf->Cell(42, 6, 'Note:Total quantity dispensed #', 0, 0);
                $pdf->Cell(15, 6, '', 0, 0);
                $pdf->Cell(32, 6, 'Qty to consume #', 0, 0);
                $pdf->Cell(15, 6, '____', 0, 1);

                $pdf->Ln(1);
            }
        }
        $pdf->Ln(5);

        // Disable auto page break for fixed footer (UPDATED to match bulk)
        $pdf->SetAutoPageBreak(false);

        // DOCTOR INFORMATION BOTTOM PART - FIXED POSITION (UPDATED to match bulk)
        $footerHeight = 20; // Height needed for footer section
        $footerY = $height - $footerHeight;

        // Move to footer position
        $pdf->SetY($footerY);

        // LEFT SIDE: REFILL DAY
        $pdf->SetX(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(18, 20, 'Refill day:', 0, 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 15);
        $pdf->Cell(10, 20, $prescription['Refill_day'] ?? '', 0, 0, 'L');

        // RIGHT SIDE COLUMN (UPDATED positioning to match bulk)
        $rightColumnX = $width - 55;

        // Line 1: MD with Doctor's name (UPDATED font to regular, not underline)
        $pdf->SetY($footerY);
        $pdf->SetX($rightColumnX);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(5, 10, 'M.D.', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(30, 10, $prescription['Doctor_name'], 0, 1, 'R');

        // Line 2: License #
        $pdf->SetY($footerY + 5);
        $pdf->SetX($rightColumnX);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(9, 10, 'License #:', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(15, 10, $prescription['Doctor_license'], 0, 1, 'R');

        // Line 3: PTR #
        if (!empty($prescription['Doctor_PTR'])) {
            $pdf->SetY($footerY + 10);
            $pdf->SetX($rightColumnX);
            $pdf->SetFont('Arial', 'B', 6);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(6, 10, 'PTR #:', 0, 0, 'R');
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(15, 10, $prescription['Doctor_PTR'], 0, 1, 'R');
        }

        // Re-enable auto page break for next page
        $pdf->SetAutoPageBreak(true, 15);

        // Add a line break to properly end the page
        $pdf->Ln();
    }

    $lastname_parts = explode(',', $prescription['Patient_name']);
    $lastname = isset($lastname_parts[0]) ? preg_replace("/[^A-Za-z0-9]/", "", $lastname_parts[0]) : 'Patient';
    $firstname = '';
    if (isset($lastname_parts[1])) {
        $firstname_parts = explode(' ', trim($lastname_parts[1]));
        $firstname = preg_replace("/[^A-Za-z0-9]/", "", $firstname_parts[0] ?? '');
    }
    $date = date('Ymd');

    $filename = "Prescription_{$prescription_id}_{$lastname}_{$firstname}_{$date}.pdf";

    $pdf->Output('I', $filename);
} catch (Exception $e) {
    // Display error message
    echo "Error generating PDF: " . $e->getMessage();
    echo "<br>Prescription ID: " . $prescription_id;

    // You can also log the error
    error_log("PDF Generation Error: " . $e->getMessage());
}
