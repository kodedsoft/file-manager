<?php
namespace App\Traits;

use App\Traits\Products_csv_fields;

trait GenericCsvAdapter {
    use Products_csv_fields;

    public $fieldsIndexed = [];
    public $fieldsArray = [];

    public function CsvReader(string $filePath)
    {

        try {
            $handle = fopen($this->filePath, "r");

            if ($handle === false) {
                throw new Exception('invalid csv file');
            }

            // Use 0 for unlimited line length to handle long lines and embedded newlines
            $headers = fgetcsv($handle, 0, ",");
            if ($headers === false || count($headers) < 8) {
                fclose($handle);
                throw new Exception('incomplete csv file');
            }

            $expectedColumns = count($headers);
            Log::info("CSV Processing Started", [
                'file' => basename($this->filePath),
                'columns' => $expectedColumns
            ]);
            
            $this->mapCsvFields($headers);

            Log::info("CSV Fields Mapped", [
                'mapped_fields' => array_values($this->fieldsIndexed)
            ]);
            
            // Process in batches to save memory
            $batchSize = 100;
            $batch = [];
            $rowNum = 0;
            $totalSaved = 0;
            $totalFailed = 0;
            
            while (($line = fgets($handle)) !== FALSE) {
                $rowNum++;
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue; // skip empty lines
                }

                // Split into columns
                $csvRow = array_map('trim', explode(',', $line));
                $columnCount = count($csvRow);
                
                // Validate column count
                if ($columnCount !== $expectedColumns) {
                    $csvRow = array_pad($csvRow, $expectedColumns, null);
                }
                
                $parsed = $this->parse($csvRow);
                $batch[] = $parsed;

                // Process batch when it reaches the batch size
                if (count($batch) >= $batchSize) {
                    $result = $this->processBatch($batch, $rowNum);
                    $totalSaved += $result['saved'];
                    $totalFailed += $result['failed'];
                    
                    // Clear batch to free memory
                    $batch = [];
                    
                    // Force garbage collection every 1000 rows
                    if ($rowNum % 1000 === 0) {
                        gc_collect_cycles();
                        Log::info("Progress: {$rowNum} rows processed", [
                            'saved' => $totalSaved,
                            'failed' => $totalFailed
                        ]);
                    }
                }
            }
            
            // Process remaining rows in the last batch
            if (!empty($batch)) {
                $result = $this->processBatch($batch, $rowNum);
                $totalSaved += $result['saved'];
                $totalFailed += $result['failed'];
            }

            fclose($handle);
            
            Log::info("CSV Processing Complete", [
                'file' => basename($this->filePath),
                'total_rows' => $rowNum,
                'saved' => $totalSaved,
                'failed' => $totalFailed
            ]);

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

    private function processBatch(array $batch, int $currentRow): array
    {
        $saved = 0;
        $failed = 0;
        
        foreach ($batch as $record) {
            if(method_exists($this, 'saveDataToDB'))
            {
                $result = $this->saveDataToDB($record);
                if ($result) {
                    $saved++;
                } else {
                    $failed++;
                }
            }
        }
        
        return ['saved' => $saved, 'failed' => $failed];
    }

}