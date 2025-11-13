<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckQueueStatus extends Command
{
    protected $signature = 'queue:check';
    protected $description = 'Check queue status and pending jobs';

    public function handle()
    {
        $this->info('Queue Status Check');
        $this->info(str_repeat('=', 60));
        
        // Check jobs table
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        
        $this->table(
            ['Status', 'Count'],
            [
                ['Pending Jobs', $pendingJobs],
                ['Failed Jobs', $failedJobs],
            ]
        );
        
        if ($pendingJobs > 0) {
            $this->newLine();
            $this->info('Pending Jobs:');
            $jobs = DB::table('jobs')
                ->select('id', 'queue', 'payload', 'attempts', 'created_at')
                ->orderBy('id')
                ->limit(10)
                ->get();
            
            foreach ($jobs as $job) {
                $payload = json_decode($job->payload, true);
                $displayName = $payload['displayName'] ?? 'Unknown';
                
                $this->line("  Job #{$job->id}: {$displayName}");
                $this->line("    Queue: {$job->queue}");
                $this->line("    Attempts: {$job->attempts}");
                $this->line("    Created: {$job->created_at}");
                $this->newLine();
            }
        }
        
        if ($failedJobs > 0) {
            $this->newLine();
            $this->warn('Failed Jobs:');
            $failed = DB::table('failed_jobs')
                ->select('id', 'connection', 'queue', 'exception', 'failed_at')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get();
            
            foreach ($failed as $job) {
                $this->line("  Failed Job #{$job->id}");
                $this->line("    Queue: {$job->queue}");
                $this->line("    Failed: {$job->failed_at}");
                $this->line("    Error: " . substr($job->exception, 0, 100) . "...");
                $this->newLine();
            }
        }
        
        $this->newLine();
        $this->info('To process the queue, run:');
        $this->comment('  php artisan queue:work');
        $this->comment('  php artisan queue:work --once  (process one job)');
        
        return 0;
    }
}
