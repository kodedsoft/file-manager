<?php
namespace App\Traits;

use League\Csv\Reader;

trait LeagueCsvAdapter
{
    public function readCsvWithLeagueCsv(string $filePath): \Generator
    {
        $reader = Reader::createFromPath($filePath, 'r');
        
        // Read header row manually
        $headers = [];
        $rowIndex = 0;
        
        foreach ($reader->getRecords() as $offset => $record) {
            // First row is headers
            if ($rowIndex === 0) {
                // Make headers unique by appending counter to duplicates
                $headerCounts = [];
                foreach ($record as $header) {
                    if (isset($headerCounts[$header])) {
                        $headerCounts[$header]++;
                        $header = $header . '_' . $headerCounts[$header];
                    } else {
                        $headerCounts[$header] = 0;
                    }
                    $headers[] = $header;
                }
                $rowIndex++;
                continue;
            }
            
            // Process data rows
            $rowData = [];
            $index = 0;
            foreach ($record as $value) {
                $header = $headers[$index] ?? "column_$index";
                $decodedValue = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $decodedValue = trim($decodedValue);
                $hkey = strtoupper($header);
                $rowData[$hkey] = $decodedValue;
                $index++;
            }
            
            yield $rowData;
            $rowIndex++;
        }
    }
}