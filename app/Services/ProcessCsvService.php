<?php
namespace App\Services;

use App\Models\Product;
use App\Models\CsvDataLog;
use App\Traits\Products_csv_fields;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SplFileObject;

class ProcessCsvService
{
    use Products_csv_fields;
    private array $fieldsIndexed = [];
    private $fileHandle = null;
    public array $jsonCsvData = [];
    public function __construct(public String $filePath)
    {}

    public function processCsvData()
    {
        try {
            $handle = fopen($this->filePath, "r");

            if ($handle === false) {
                throw new Exception('invalid csv file');
            }

           $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');

            // Optional: Set reader properties if your CSV has specific characteristics
            // $reader->setDelimiter(','); // Default is comma
            // $reader->setEnclosure('"'); // Default is double quote
            // $reader->setSheetIndex(0); // Default is 0

            // 2. Load the CSV file into a Spreadsheet object
            $spreadsheet = $reader->load($this->filePath);

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

            $chunks = array_chunk($data, 100);
            foreach($chunks as $chunk100Rows)
            {
               foreach($chunk100Rows as $chunkRow)
               {
                   $this->saveDataToDB($chunkRow);
                  //$this->logToJson($chunkRow);
               }
                
            } 
        } catch (Exception $e) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            Log::error("CSV Processing Error: " . $e->getMessage(), [
                'file' => $this->filePath,
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    private function processBatch(array $batch, int $currentRow): array
    {
        $saved = 0;
        $failed = 0;
        
        foreach ($batch as $record) {
            $result = $this->saveDataToDB($record);
            if ($result) {
                $saved++;
            } else {
                $failed++;
            }
        }
        
        return ['saved' => $saved, 'failed' => $failed];
    }

    public function parse($row): array
    {
        $processed = [];
        foreach($row as $index => $value) {
            // only process those columns which are required
            if(isset($this->fieldsIndexed[$index])) {
                $key = $this->fieldsIndexed[$index];
                $processed[$key] = $this->cleanCell($key, $value);
            }
        }

        // Fix: Split PRODUCT_TITLE if it contains the full product info
        if (isset($processed['PRODUCT_TITLE']) && strpos($processed['PRODUCT_TITLE'], '&#174;') !== false) {
            $this->splitProductTitle($processed);
        }

        return $processed;
    }


    public function mapCsvFields(array $headers): void
    {
        $hc = count($headers);
        for($i=0;$i<$hc;$i++)
        {
            $header = $headers[$i];
            $header = strtoupper($header);
            // only process those columns which are required
            if(array_key_exists($header,$this->fieldsArray)) {
                $this->fieldsArray["$header"] = $i;
            }
        }
        // Only flip fields that were actually mapped (not null)
        // Use callback to filter out null but keep 0 (which is a valid column index)
        $this->fieldsIndexed = array_flip(array_filter($this->fieldsArray, fn($val) => $val !== null));
   
    }

    public function cleanCell($key, $value)
    {
        if(isset($this->fieldsArray[$key])) {
            if ($value === null) return null;
            // Convert to UTF-8, replacing invalid bytes
            $d = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            $d = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $d);
            $d  = is_string($d) ? trim($d) : $d;

            if($key == 'PIECE_PRICE') {
                return floatval($d);
            }
            
            return $d;
        }

        return $value;
    }
    
    public function saveDataToDB(array $csvData): bool
    {
        $uniqueKey = $csvData['UNIQUE_KEY'] ?? null;

        if (blank($uniqueKey)) {
            $this->logInvalidRow('Missing UNIQUE_KEY', $csvData);
            return false;
        }

        if (!ctype_digit((string) $uniqueKey)) {
            $this->logInvalidRow('UNIQUE_KEY must be numeric', $csvData);
            return false;
        }

        try {
            DB::beginTransaction();

            $product = Product::firstOrNew(['unique_key' => (int) $uniqueKey]);

            // Only update fields if they have non-empty values from CSV
            if (!empty($csvData['PRODUCT_TITLE'])) {
                $product->title = $csvData['PRODUCT_TITLE'];
            }
            
            if (!empty($csvData['PRODUCT_DESCRIPTION'])) {
                $product->description = $csvData['PRODUCT_DESCRIPTION'];
            }
            
            $normalizedPrice = $this->normalizePrice($csvData['PIECE_PRICE'] ?? null);
            if ($normalizedPrice !== null) {
                $product->piece_price = $normalizedPrice;
            }
            
            if (!empty($csvData['SIZE'])) {
                $product->size = $csvData['SIZE'];
            }
            
            if (!empty($csvData['STYLE#'])) {
                $product->style = $csvData['STYLE#'];
            }
            
            if (!empty($csvData['COLOR_NAME'])) {
                $product->color_name = $csvData['COLOR_NAME'];
            }
            
            if (!empty($csvData['SANMAR_MAINFRAME_COLOR'])) {
                $product->sanmar_mainframe_color = $csvData['SANMAR_MAINFRAME_COLOR'];
            }
            
            $product->save();

            DB::commit();
            return true;
            
        } catch (Exception $exception) {
            DB::rollBack();
            $this->logInvalidRow($exception->getMessage(), $csvData);
            Log::error("Failed to save product", [
                'unique_key' => $uniqueKey,
                'error' => $exception->getMessage()
            ]);
            return false;
        }
    }

    protected function normalizePrice($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $filtered = preg_replace('/[^0-9\.\-]/', '', (string) $value);

        return is_numeric($filtered) ? (float) $filtered : null;
    }

    protected function logInvalidRow(string $message, array $data): void
    {
        try {
            CsvDataLog::create([
                'filename' => basename($this->filePath),
                'data' => [
                    'message' => $message,
                    'row' => $data,
                ],
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to log CSV row.', [
                'error' => $e->getMessage(),
                'row' => $data,
            ]);
        }
    }

    public function logToJson($csvRow)
    {
        $this->jsonCsvData[]= $csvRow;
    }
}

