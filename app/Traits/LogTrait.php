<?php
namespace App\Traits;

trait LogTrait {
    public $jsonCsvData = [];
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