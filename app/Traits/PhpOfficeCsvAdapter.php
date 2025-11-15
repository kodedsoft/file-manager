<?php
namespace App\Traits;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

trait PhpOfficeCsvAdapter{
    public function readCsvWithPhpOffice(string $filePath): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');

        // Optional: Set reader properties if your CSV has specific characteristics
        // $reader->setDelimiter(','); // Default is comma
        // $reader->setEnclosure('"'); // Default is double quote
        // $reader->setSheetIndex(0); // Default is 0

        // 2. Load the CSV file into a Spreadsheet object
        
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');

        // Optional: Set reader properties if your CSV has specific characteristics
        // $reader->setDelimiter(','); // Default is comma
        // $reader->setEnclosure('"'); // Default is double quote
        // $reader->setSheetIndex(0); // Default is 0

        // 2. Load the CSV file into a Spreadsheet object
        $spreadsheet = $reader->load($filePath);

        // 3. Get the active worksheet (the first sheet by default)
        $worksheet = $spreadsheet->getActiveSheet();

        $data = [];
        $headers = [];
        $headerRowNum = 1; // Assuming the first row contains headers

        // 4. Iterate through rows
        // getHighestRow() gets the number of the last row that contains data
        foreach ($worksheet->getRowIterator() as $row) { // $row is a Row object
            $rowIndex = $row->getRowIndex();

            // Get the cell iterator for the current row
            $cellIterator = $row->getCellIterator();
            // Optional: If you want to skip empty cells at the end of the row
            $cellIterator->setIterateOnlyExistingCells(true);

            $rowData = [];
            $cellIndex = 0;

            foreach ($cellIterator as $cell) { // $cell is a Cell object
                $value = $cell->getValue();

                // Decode HTML entities (like &#174;) in the cell value
                // Note: This happens *after* reading the value from the cell
                $decodedValue = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $decodedValue = trim($decodedValue); // Trim whitespace

                if ($rowIndex === $headerRowNum) {
                    // Store headers for the first row
                    $headers[$cellIndex] = $decodedValue;
                } else {
                    // Store data for subsequent rows
                    $rowData[$headers[$cellIndex]] = $decodedValue; // Use header name as key
                }
                $cellIndex++;
            }

            // After processing all cells in a data row (not the header row)
            if ($rowIndex > $headerRowNum && !empty($rowData)) {
                $data[] = $rowData;
            }
        }

        return $data;
    }
}
