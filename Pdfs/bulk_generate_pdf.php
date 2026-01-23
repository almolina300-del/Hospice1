<?php
require(__DIR__ . '/../fpdf186/fpdf.php');
require(__DIR__ . '/../Config/Config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get parameters
$Refill_day = isset($_GET['dosearch']) ? $_GET['dosearch'] : "";
$bulk_ids = isset($_GET['bulk_ids']) ? explode(',', $_GET['bulk_ids']) : [];

$conn = mysqli_connect(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB)
    or die('Could not connect: ' . mysqli_connect_error());

// Build SQL query to get prescriptions
if (!empty($bulk_ids) && is_array($bulk_ids)) {
    $valid_ids = array_filter($bulk_ids, 'is_numeric');
    if (!empty($valid_ids)) {
        $id_list = implode(',', $valid_ids);

        // Get prescriptions by the provided IDs
        $sql = "SELECT p.Prescription_id 
                FROM prescription p
                LEFT JOIN patient_details pat ON p.Patient_id = pat.Patient_id
                WHERE pat.is_active = 1
                AND p.Prescription_id IN ($id_list)";

        if ($Refill_day != "") {
            $sql .= " AND p.Refill_day = '" . mysqli_real_escape_string($conn, $Refill_day) . "'";
        }

        $sql .= " ORDER BY p.Refill_day ASC";
    } else {
        die("Invalid prescription IDs provided.");
    }
} else {
    // Fallback: Get bulk prescriptions if no specific IDs provided
    $sql = "SELECT p.Prescription_id 
            FROM prescription p
            INNER JOIN (
                SELECT Patient_id, MAX(Date) as latest_date
                FROM prescription
                WHERE creation_type = 'bulk'
                GROUP BY Patient_id
            ) latest ON p.Patient_id = latest.Patient_id AND p.Date = latest.latest_date
            LEFT JOIN patient_details pat ON p.Patient_id = pat.Patient_id
            WHERE pat.is_active = 1
            AND p.creation_type = 'bulk'";

    if ($Refill_day != "") {
        $sql .= " AND p.Refill_day = '" . mysqli_real_escape_string($conn, $Refill_day) . "'";
    }

    $sql .= " ORDER BY p.Refill_day ASC";
}

$list_result = mysqli_query($conn, $sql);
if (!$list_result || mysqli_num_rows($list_result) == 0) {
    die("No prescriptions found for printing.");
}

// Use the same dimensions as generatepdf script
$width = 5.25 * 25.4;  // 5.25 inches in mm
$height = 8 * 25.4;  // 8 inches in mm - SAME AS GENERATEPDF

$pdf = new FPDF('P', 'mm', array($width, $height));
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetFont('Arial', '', 10);

$prescription_count = 0;

while ($row = mysqli_fetch_assoc($list_result)) {
    $pid = intval($row['Prescription_id']);
    $prescription_count++;

    $sql2 = "SELECT p.*, 
                    CONCAT(pat.Last_name, ', ', pat.First_name, ' ', COALESCE(pat.Middle_name, '')) AS Patient_name,
                    CONCAT(pat.House_nos_street_name, ', ', Barangay) AS Address,
                    pat.Sex,
                    pat.Birthday,
                    CONCAT(d.Last_name, ', ', d.First_name, ' ', COALESCE(d.Middle_name, '')) AS Doctor_name,
                    d.License_number,
                    d.PTR_number,
                    TIMESTAMPDIFF(YEAR, pat.Birthday, CURDATE()) AS Age,
                    p.creation_type
             FROM prescription p
             LEFT JOIN patient_details pat ON p.Patient_id = pat.Patient_id
             LEFT JOIN doctors d ON p.License_number = d.License_number
             WHERE p.Prescription_id = $pid";

    $rx = mysqli_query($conn, $sql2);
    if (!$rx) {
        die("Error fetching prescription: " . mysqli_error($conn));
    }

    $prescription = mysqli_fetch_assoc($rx);

    // Check if prescription data was found
    if (!$prescription) {
        continue; // Skip if no prescription found
    }

    $sql3 = "SELECT m.Medicine_name, m.Dose, m.Form, r.Quantity, r.Frequency
             FROM rx r
             JOIN medicine m ON r.Medicine_id = m.Medicine_id
             WHERE r.Prescription_id = $pid";

    $meds_result = mysqli_query($conn, $sql3);
    if (!$meds_result) {
        die("Error fetching medicines: " . mysqli_error($conn));
    }

    $medicines = [];
    while ($m = mysqli_fetch_assoc($meds_result)) {
        $m['Medicine_name'] = $m['Medicine_name'] ?: '__________________';
        $m['Dose']          = $m['Dose']          ?: '__';
        $m['Frequency']     = $m['Frequency']     ?: '__________________';
        $m['Quantity']      = $m['Quantity']      ?: '__';
        $medicines[] = $m;
    }

    $total_meds = count($medicines);
    $meds_per_page = 5;
    $pages = ceil($total_meds / $meds_per_page);

    for ($page = 1; $page <= $pages; $page++) {
        $pdf->AddPage();

        // Add Page X / Y format at top-right - EXACTLY LIKE GENERATEPDF
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY(15);  // SAME AS GENERATEPDF
        $pdf->SetX($width - 25);
        $pdf->Cell(20, 5, 'Page ' . $page . ' / ' . $pages, 0, 0, 'R');

        // Patient Information on EVERY PAGE 
        $pdf->Ln(12);
        $pdf->SetX(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(11, 10, 'Name:');
        $pdf->SetFont('Arial', 'B', 12);
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
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(5, 10, $prescription['Age'], 0, 0, 'R');

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

            $pdf->Ln(5);
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

        $start = ($page - 1) * $meds_per_page;
        $end = min($start + $meds_per_page, $total_meds);

        // Medicines section - UPDATED with wrapping logic
        $pdf->Ln(8);
        $pdf->SetAutoPageBreak(false);

        for ($i = $start; $i < $end; $i++) {
            $med = $medicines[$i];
            $number = $i + 1 - $start;

            // Get medicine form
            $medicineForm = $med['Form'] ?? '';

            // Medicine name and dose line - WITH WRAPPING LOGIC
            $pdf->SetX(2);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(8, 5, $number . '.', 0, 0);

            // Set position for medicine name
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('courier', 'B', 8);

            // Get medicine name and dose
            $medicineName = $med['Medicine_name'] ?? '';
            $dose = $med['Dose'] ?? '';

            // Determine max width for medicine name (allow space for dose)
            $maxMedicineWidth = 100;
            $doseWidth = 10;

            // Check if medicine name fits in one line
            $nameWidth = $pdf->GetStringWidth($medicineName);

            if ($nameWidth <= $maxMedicineWidth) {
                // Fits in one line - use Cell
                $pdf->Cell($maxMedicineWidth, 5, $medicineName, 0, 0, '', false);
                $pdf->Cell($doseWidth, 5, $dose, 0, 0, '', false);
                $pdf->SetTextColor(200, 200, 200);
                $pdf->Cell(5, 5, '', 0, 1);
            } else {
                // Doesn't fit - handle wrapping
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
                $pdf->Cell(2, 2, '', 0, 1);

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
            $pdf->Cell(8, 4, '', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(18, 4, '[  ] Tablet', 0, 0);
            $pdf->Cell(18, 4, '[  ] Capsule', 0, 0);
            $pdf->Cell(18, 4, '[  ] Syrup', 0, 0);
            $pdf->Cell(18, 4, '[  ] Drops', 0, 0);
            $pdf->Cell(16, 4, '[  ] Others:', 0, 0);

            // Show the medicine form next to Others
            if (!empty($medicineForm)) {
                $pdf->SetFont('courier', 'B', 8);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetX($pdf->GetX() + 5);
                $pdf->Cell(15, 4, $medicineForm, 0, 1);
            } else {
                $pdf->SetTextColor(200, 200, 200);
                $pdf->Cell(15, 4, '___________', 0, 1);
            }

            // Signa line - APPLYING THE FREQUENCY WRAPPING LOGIC FROM GENERATE_PDF
            $pdf->SetX(0.5);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(8, 4, '', 0, 0);
            $pdf->Cell(11, 4, 'Signa:', 0, 0);
            $pdf->SetFont('courier', 'B', 7); // Font size 7 like generate_pdf
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetX(17);

            // Get frequency text
            $frequency = $med['Frequency'] ?? '';
            $maxFrequencyWidth = 68; // Max width for frequency text

            // Calculate frequency width for checking
            $freqWidth = $pdf->GetStringWidth($frequency);

            if ($freqWidth <= $maxFrequencyWidth) {
                // Fits in one line
                $pdf->Cell($maxFrequencyWidth, 4, $frequency, 0, 0, '', false);

                // "Per day For" and "Days" labels on same line
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetTextColor(200, 200, 200);
                $pdf->Cell(18, 4, 'Per day For', 0, 0);
                $pdf->Cell(12, 4, '', 0, 0);
                $pdf->Cell(8, 4, 'Days', 0, 1);
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
                $pdf->Cell(18, 4, 'Per day For', 0, 0);
                $pdf->Cell(12, 4, '', 0, 0);
                $pdf->Cell(8, 4, 'Days', 0, 1);
            } else {
                // If frequency wrapped, move to next line for labels
                $pdf->Ln(3); // Small line break for wrapped frequency
                $pdf->SetX(17); // Align with Signa label
            }

            // Notes line
            $pdf->SetX(2);
            $pdf->Cell(8, 4, '', 0, 0);
            $pdf->Cell(50, 4, 'Note:Total quantity to be dispensed #', 0, 0);
            $pdf->Cell(15, 4, '____', 0, 0, 'R');
            $pdf->Cell(33, 4, 'Quantity to consume #', 0, 0);
            $pdf->SetFont('courier', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(15, 4, $med['Quantity'] ?? '', 0, 0, '', false);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 4, '', 0, 1);

            $pdf->Ln(5);
        }

        // Fill remaining empty medicine forms
        $current_page_meds = $end - $start;
        if ($current_page_meds < $meds_per_page) {
            $remaining_rows = $meds_per_page - $current_page_meds;
            for ($i = 0; $i < $remaining_rows; $i++) {
                $number = $current_page_meds + $i + 1;

                // Empty medicine line
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetX(2);
                $pdf->Cell(8, 6, $number . '.', 0, 0);
                $pdf->SetFont('courier', 'BI', 8);
                $pdf->Cell(95, 6, '-- No Added Prescription --', 0, 0);
                $pdf->SetTextColor(200, 200, 200);
                $pdf->Cell(12, 6, '', 0, 0);
                $pdf->Cell(5, 6, 'Mg', 0, 1);

                $pdf->Cell(8, 6, '', 0, 0);
                $pdf->SetX(10);
                $pdf->SetFont('Arial', '', 10);
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

        // Disable auto page break for fixed footer
        $pdf->SetAutoPageBreak(false);

        // DOCTOR INFORMATION BOTTOM PART
        $footerHeight = 20;
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

        // RIGHT SIDE COLUMN
        $rightColumnX = $width - 55;

        // Line 1: MD with Doctor's name
        $pdf->SetY($footerY);
        $pdf->SetX($rightColumnX);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(5, 10, 'M.D.', 0, 0, 'R');
        $pdf->SetFont('courier', 'B', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(35, 10, $prescription['Doctor_name'] ?? '', 0, 1, 'R');

        // Line 2: License #
        $pdf->SetY($footerY + 5);
        $pdf->SetX($rightColumnX);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Cell(9, 10, 'License #:', 0, 0, 'R');
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(20, 10, $prescription['License_number'] ?? '', 0, 1, 'R');

        // Line 3: PTR #
        if (!empty($prescription['PTR_number'])) {
            $pdf->SetY($footerY + 10);
            $pdf->SetX($rightColumnX);
            $pdf->SetFont('Arial', 'B', 6);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->Cell(6, 10, 'PTR #:', 0, 0, 'R');
            $pdf->SetFont('courier', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(20, 10, $prescription['PTR_number'], 0, 1, 'R');
        }

        // Re-enable auto page break for next page
        $pdf->SetAutoPageBreak(true, 15);

        // Add a line break to properly end the page
        $pdf->Ln();
    }
}

$filename = "Bulk_Prescriptions_" . ($Refill_day ? "Day{$Refill_day}_" : "") . date('Ymd_His') . ".pdf";
$pdf->Output('I', $filename);
