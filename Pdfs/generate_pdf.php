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
                   CONCAT(pat.House_nos_street_name, ', ', pat.Barangay) AS Address,
                   pat.Sex,
                   pat.Birthday,
                   CONCAT(d.Last_name, ', ', d.First_name, ' ', COALESCE(d.Middle_name, '')) as Doctor_name,
                   d.License_number as Doctor_license,
                   d.PTR_number as Doctor_PTR,  -- Added PTR number
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
    $required_fields = ['Patient_name', 'Address', 'Sex', 'Date', 'Doctor_name', 'Doctor_license'];
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
    $meds_sql = "SELECT m.Medicine_name, m.Dose, m.Form, r.Quantity, r.Frequency
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
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY(15);
        $pdf->SetX($width - 25);
        $pdf->Cell(20, 5, 'Page ' . $page_num . ' / ' . $total_pages, 0, 0, 'R');

        // Patient Information on EVERY PAGE 
        $pdf->Ln(15);
        $pdf->SetX(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(11, 10, 'Name:');
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(100, 10, $prescription['Patient_name'], 0, 0);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(-21, 10, 'Age:', 0, 0, 'R');
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(5, 10, $prescription['Age'], 0, 0, 'R');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(30, 10, 'Sex:');
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(-11, 10, $prescription['Sex'], 0, 1, 'R');

        // Address section - working like frequency
        $pdf->SetX(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(11, 5, 'Address:');

        // Get address text
        $address = $prescription['Address'];
        $maxAddrWidth = 65; // Reduced from 100 to leave space for date at X:115

        // Save starting position
        $addrStartX = $pdf->GetX(); // Should be 21
        $addrStartY = $pdf->GetY(); // Should be 40

        // Set font for address
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);

        // Check if address fits
        $addrWidth = $pdf->GetStringWidth($address);

        if ($addrWidth <= $maxAddrWidth) {
            // Write address
            $pdf->Cell($maxAddrWidth, 5, $address, 0, 0, 'L');

            // Write date on same line
            $pdf->SetX(93);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(8, 5, 'Date:', 0, 0, 'R');
            $pdf->SetFont('courier', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(18, 5, $prescription['Date'], 0, 1, 'R');

            $pdf->Ln(-5);
        } else {
            // Find where to break the address (like frequency)
            $charPos = 0;
            $testString = '';

            for ($j = 0; $j < strlen($address); $j++) {
                $testString .= $address[$j];
                if ($pdf->GetStringWidth($testString) > $maxAddrWidth) {
                    $charPos = $j;
                    break;
                }
            }

            $firstLine = $charPos > 0 ? substr($address, 0, $charPos) : $address;
            $remaining = $charPos > 0 ? substr($address, $charPos) : '';

            // Save Y position
            $yPos = $pdf->GetY();

            // First line of address
            $pdf->Cell($maxAddrWidth, 1, $firstLine, 0, 0, 'L');

            // Date on same line
            $pdf->SetX(93);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(8, 5, 'Date:', 0, 0, 'R');
            $pdf->SetFont('courier', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(18, 5, $prescription['Date'], 0, 1, 'R');

            // Second line if needed (like frequency's second line)
            if (!empty($remaining)) {
                $pdf->SetXY($addrStartX, $yPos + 1); // Next line, same X
                $pdf->Cell($maxAddrWidth, 5, $remaining, 0, 0, 'L');
            }

            // Adjust spacing
            $pdf->Ln(1);
        }

        // Medicines section - UPDATED to match bulk exactly
        $start_index = ($page_num - 1) * $medicines_per_page;
        $end_index = min($start_index + $medicines_per_page, $total_medicines);

        // Medicines section
        $pdf->Ln(12); // Reduced from 8
        $pdf->SetAutoPageBreak(false);

        for ($i = $start_index; $i < $end_index; $i++) {
            $med = $medicines[$i];
            $number = $i + 1 - $start_index; // Number 1-5 for current page

            // Get medicine form
            $medicineForm = $med['Form'] ?? '';

            // Medicine name and dose line - with character limits and wrapping
            $pdf->SetX(2);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(8, 5, $number . '.', 0, 0); // Reduced height from 6 to 5

            // Set position for medicine name
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('courier', 'B', 8);

            // Get medicine name and dose
            $medicineName = $med['Medicine_name'] ?? '';
            $dose = $med['Dose'] ?? '';

            // Determine max width for medicine name (allow space for dose)
            $maxMedicineWidth = 100; // Original width for medicine name
            $doseWidth = 10; // Width for dose

            // Check if medicine name fits in one line
            $nameWidth = $pdf->GetStringWidth($medicineName);

            if ($nameWidth <= $maxMedicineWidth) {
                // Fits in one line - use Cell
                $pdf->Cell($maxMedicineWidth, 5, $medicineName, 0, 0, '', false); // Reduced height from 6 to 5
                $pdf->Cell($doseWidth, 5, $dose, 0, 0, '', false); // Reduced height from 6 to 5
                $pdf->SetTextColor(200, 200, 200);
                $pdf->Cell(5, 5, '', 0, 1); // Reduced height from 6 to 5
            } else {
                // Doesn't fit - use MultiCell for medicine name
                $currentX = $pdf->GetX();
                $currentY = $pdf->GetY();

                // First line of medicine name (with dose on same line if space allows)
                $firstLine = $medicineName;
                $doseString = $dose;

                // Try to fit as much as possible on first line with dose
                $charPos = 0;
                $testString = '';
                $maxFirstLineWidth = $maxMedicineWidth + $doseWidth; // Combined width

                // Find where to break the medicine name
                for ($j = 0; $j < strlen($medicineName); $j++) {
                    $testString .= $medicineName[$j];
                    if ($pdf->GetStringWidth($testString . $dose) > $maxFirstLineWidth) {
                        $charPos = $j;
                        break;
                    }
                }

                if ($charPos > 0) {
                    $firstLine = substr($medicineName, 0, $charPos);
                    $remaining = substr($medicineName, $charPos);
                } else {
                    $firstLine = $medicineName;
                    $remaining = '';
                }

                // Save starting Y
                $y = $pdf->GetY();

                // First line (medicine)
                $pdf->Cell($maxMedicineWidth, 2, $firstLine, 0, 0, '', false);

                // Move left for dose
                $pdf->SetXY($pdf->GetX() - 21, $y);

                // Dose aligned to TOP
                $pdf->Cell($doseWidth, 2, $dose, 0, 0, '', false);

                // Unit
                $pdf->SetTextColor(200, 200, 200);
                $pdf->Cell(2, 2, 'Mg', 0, 1);

                // Output remaining medicine name on second line with smaller font
                if (!empty($remaining)) {
                    $pdf->SetX($currentX + 8); // Align with the number position
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('courier', 'B', 7); // Smaller font size for second line
                    $pdf->Cell($maxMedicineWidth, 3, $remaining, 0, 0, '', false); // Smaller height 3
                    $pdf->Cell($doseWidth, 3, '', 0, 0, '', false); // Empty dose cell
                    $pdf->SetTextColor(200, 200, 200);
                    $pdf->Cell(5, 3, '', 0, 1);
                    $pdf->SetFont('courier', 'B', 8); // Reset font size
                }
            }

            // Checkboxes for form - SIMPLIFIED: Show form next to Others
            $pdf->SetX(2);
            $pdf->Cell(8, 4, '', 0, 0); // Reduced height from 6 to 4
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(18, 4, '[  ] Tablet', 0, 0); // Reduced height from 6 to 4
            $pdf->Cell(18, 4, '[  ] Capsule', 0, 0); // Reduced height from 6 to 4
            $pdf->Cell(18, 4, '[  ] Syrup', 0, 0); // Reduced height from 6 to 4
            $pdf->Cell(18, 4, '[  ] Drops', 0, 0); // Reduced height from 6 to 40
            $pdf->Cell(16, 4, '[  ] Others:', 0, 0); // Reduced height from 6 to 4

            // Show the medicine form next to Others (UPDATED font size to 6)
            if (!empty($medicineForm)) {
                $pdf->SetFont('courier', 'B', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetX($pdf->GetX() + 5); // move 5 units to the right
                $pdf->Cell(15, 4, $medicineForm, 0, 1); // Reduced height from 6 to 4
            } else {
                $pdf->SetTextColor(200, 200, 200);
                $pdf->Cell(15, 4, '___________', 0, 1); // Reduced height from 6 to 4
            }
            // Signa line - frequency only in signa field (UPDATED to match bulk with wrapping logic)
            $pdf->SetX(0.5);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(8, 4, '', 0, 0); // Reduced height from 6 to 4
            $pdf->Cell(11, 4, 'Signa:', 0, 0); // Reduced height from 6 to 4
            $pdf->SetFont('courier', 'B', 7);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetX(17); // Added to match bulk positioning

            // Get frequency text
            $frequency = $med['Frequency'] ?? '';
            $maxFrequencyWidth = 68; // Max width for frequency text

            // Calculate frequency width for checking
            $freqWidth = $pdf->GetStringWidth($frequency); // ADD THIS LINE

            if ($freqWidth <= $maxFrequencyWidth) {
                // Fits in one line
                $pdf->Cell($maxFrequencyWidth, 4, $frequency, 0, 0, '', false);;
            } else {
                // Doesn't fit - need to wrap
                $currentXFreq = $pdf->GetX();
                $currentYFreq = $pdf->GetY();

                // Find where to break the frequency text
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

                // Add "Per day For" and "Days" on same line as first part
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetTextColor(200, 200, 200);
                $pdf->Cell(18, 2, 'Per day For', 0, 0);
                $pdf->Cell(12, 2, '', 0, 0);
                $pdf->Cell(8, 2, 'Days', 0, 1);
                // Output remaining frequency on second line (indented)
                if (!empty($remainingFreq)) {
                    $pdf->SetXY(51, $yFreq + 2); // Use SetXY instead of separate SetX/SetY
                    $pdf->SetFont('courier', 'B', 6);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->Cell($maxFrequencyWidth, 2, $remainingFreq, 0, 0, '', false);

                    // Don't reset Y position
                    $pdf->SetFont('courier', 'B', 8);
                }
            }

            // "Per day For" and "Days" labels
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(200, 200, 200);

            // Position the labels properly
            if (empty($frequency) || $freqWidth <= $maxFrequencyWidth) {
                // If frequency fits in one line, continue on same line
                $pdf->Cell(18, 4, 'Per day For', 0, 0); // Reduced height from 6 to 4
                $pdf->Cell(12, 4, '', 0, 0); // Reduced height from 6 to 4
                $pdf->Cell(8, 4, 'Days', 0, 1); // Reduced height from 6 to 4
            } else {
                // If frequency wrapped, move to next line for labels
                $pdf->Ln(3); // Small line break for wrapped frequency
                $pdf->SetX(17); // Align with Signa label
            }

            // Notes line (UPDATED font size to 7)
            $pdf->SetX(2);
            $pdf->Cell(8, 4, '', 0, 0); // Reduced height from 6 to 4
            $pdf->Cell(50, 4, 'Note:Total quantity to be dispensed #', 0, 0); // Reduced height from 6 to 4
            $pdf->Cell(15, 4, '____', 0, 0, 'R'); // Reduced height from 6 to 4
            $pdf->Cell(33, 4, 'Quantity to consume #', 0, 0); // Reduced height from 6 to 4
            $pdf->SetFont('courier', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(15, 4, $med['Quantity'] ?? '', 0, 0, '', false); // Reduced height from 6 to 4
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 4, '', 0, 1); // Reduced height from 6 to 4

            $pdf->Ln(7); // Reduced from 3 to 2 - Space between medicines
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
                $pdf->SetFont('courier', 'BI', 8);
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
        $pdf->SetFont('courier', 'B', 9);
        $pdf->Cell(10, 20, $prescription['Refill_day'] ?? '', 0, 0, 'L');

        // RIGHT SIDE COLUMN (UPDATED positioning to match bulk)
        $rightColumnX = $width - 55;

        // Line 1: MD with Doctor's name (UPDATED font to regular, not underline)
        $pdf->SetY($footerY);
        $pdf->SetX($rightColumnX);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(5, 10, 'M.D.', 0, 0, 'R');
        $pdf->SetFont('courier', 'B', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(30, 10, $prescription['Doctor_name'], 0, 1, 'R');

        // Line 2: License #
        $pdf->SetY($footerY + 5);
        $pdf->SetX($rightColumnX);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(9, 10, 'License #:', 0, 0, 'R');
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(15, 10, $prescription['Doctor_license'], 0, 1, 'R');

        // Line 3: PTR #
        if (!empty($prescription['Doctor_PTR'])) {
            $pdf->SetY($footerY + 10);
            $pdf->SetX($rightColumnX);
            $pdf->SetFont('Arial', 'B', 6);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(6, 10, 'PTR #:', 0, 0, 'R');
            $pdf->SetFont('courier', 'B', 8);
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
