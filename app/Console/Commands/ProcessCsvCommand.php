<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ProcessCsvCommand extends Command
{
    protected $signature = 'csv:process {file?}';
    protected $description = 'Process CSV file with terminal logging';

    public function handle()
    {
        $filePath = $this->argument('file') ?? 'storage/app/uploads/yoprint_test_import.csv';
        
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting CSV processing: {$filePath}");
        $this->info(str_repeat('=', 60));
        
        $startTime = microtime(true);
        $rowCount = 0;
        $errorCount = 0;
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

dd($rowData);

        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = fgetcsv($handle);
            
            $this->line("Headers found: " . count($headers) . " columns");
            $this->table(['Column #', 'Header Name'], collect($headers)->map(fn($h, $i) => [$i + 1, $h ?: '(empty)'])->toArray());
            $this->newLine();
            while (($data = fgets($handle)) !== false) {
                $rowCount++;
                $row = explode(',',$data);
                try {
                    $this->info("Processing row #{$rowCount}");
                    
                    dd(print_r($row));
                                        $row = array_combine($headers, $data);

                    // Log key fields
                    $this->line("  Product: " . ($row[1] ?? 'N/A'));
                    $this->line("  Size: " . ($data[17] ?? 'N/A'));
                    $this->line("  Color: " . ($data[32] ?? 'N/A'));
                    $this->line("  Price: " . ($data[11] ?? 'N/A'));
                    
                    $this->comment("  ✓ Row processed successfully");
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("  ✗ Error: " . $e->getMessage());
                }
                
                $this->newLine();
            }
            
            fclose($handle);
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->info(str_repeat('=', 60));
        $this->info("Processing complete!");
        $this->table(['Metric', 'Value'], [
            ['Total rows processed', $rowCount],
            ['Successful', $rowCount - $errorCount],
            ['Errors', $errorCount],
            ['Duration', $duration . 's'],
            ['Rows/second', $duration > 0 ? round($rowCount / $duration, 2) : 'N/A'],
        ]);

        return 0;
    }
}
