<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\ProcessCsvService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFileUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /**
     * Maximum number of attempts before failing permanently.
     */
    public int $tries = 3;

    /**
     * Maximum number of exceptions before the job fails.
     */
    public int $maxExceptions = 3;
    
    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour

    private ProcessCsvService $processCsvService;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $fileId, public string $filePath)
    {
        $this->processCsvService = new ProcessCsvService($this->filePath);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Increase memory limit for large CSV files
        ini_set('memory_limit', '512M');
        
        $file = File::find($this->fileId);

        if (!$file) {
            Log::warning("File record {$this->fileId} not found for processing.");
            return;
        }

        $file->update(['status' => 'processing']);
        
        Log::info("Starting CSV processing for file {$file->id}", [
            'file_name' => $file->name,
            'file_path' => $this->filePath
        ]);

        try {
            $this->processCsvService->processCsvData();
            $file->update(['status' => 'completed']);
            Log::info("Successfully completed processing file {$file->id}");
        } catch (\Throwable $e) {
            $file->update(['status' => 'failed']);
            Log::error("Error processing file {$file->id}: ".$e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
