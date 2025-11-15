<?php
namespace App\Services;

use App\Models\Product;
use App\Models\CsvDataLog;
use App\Traits\Products_csv_fields;
use App\Traits\LeagueCsvAdapter;
use App\Models\SystemLog;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SplFileObject;

class ProcessCsvService
{
    use LeagueCsvAdapter;

    private $fileHandle = null;
    public function __construct(readonly public String $filePath)
    {}

    public function processCsvData()
    {
        try {
            $handle = fopen($this->filePath, "r");

            if ($handle === false) {
                throw new Exception('invalid csv file');
            }

            set_time_limit(0); // Remove PHP execution time limit
            
            $dataGenerator = $this->readCsvWithLeagueCsv($this->filePath);
            
            $chunk = [];
            $processedRows = 0;
            $chunkSize = 100;
            
            Log::info("Starting CSV processing");
            
            foreach($dataGenerator as $row)
            {
                $chunk[] = $row;
                
                // Process chunk when it reaches the size limit
                if (count($chunk) >= $chunkSize) {
                    DB::transaction(function() use ($chunk) {
                        foreach($chunk as $chunkRow) {
                            $this->saveDataToDB($chunkRow);
                        }
                    });
                    
                    $processedRows += count($chunk);
                    if ($processedRows % 1000 === 0) {
                        Log::info("CSV Processing progress: {$processedRows} rows processed");
                    }
                    
                    $chunk = []; // Reset chunk
                }
            }
            
            // Process remaining rows in the last chunk
            if (!empty($chunk)) {
                DB::transaction(function() use ($chunk) {
                    foreach($chunk as $chunkRow) {
                        $this->saveDataToDB($chunkRow);
                    }
                });
                $processedRows += count($chunk);
            }
            
            Log::info("CSV Processing completed: {$processedRows} rows processed"); 
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
            // Transaction is now handled at chunk level, not per row
            $product = Product::firstOrNew(['unique_key' => (int) $uniqueKey]);
            $isNew = !$product->exists;
            
            // Update fields only if CSV has non-empty values
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
            
            SystemLog::create([
                'level' => 'info',
                'category' => 'csv_processing',
                'message' => $isNew ? 'Product created' : 'Product updated',
                'context' => [
                    'unique_key' => $uniqueKey,
                    'file_path' => $this->filePath,
                    'product_data' => $product->toArray()
                ]
            ]);

            return true;
            
        } catch (Exception $exception) {
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

}

