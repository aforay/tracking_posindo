<?php

namespace App\Jobs;

use App\Imports\ShipmentsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessExcelImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;
    protected string $defaultSeller;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $defaultSeller = 'Aliqa')
    {
        $this->filePath = $filePath;
        $this->defaultSeller = $defaultSeller;
    }

    /**
     * Execute the job asynchronously in background queue worker.
     */
    public function handle(): void
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $startMsg = "ProcessExcelImportJob started processing stored file: {$this->filePath}";
        dump($startMsg);
        echo $startMsg . "\n";
        Log::info($startMsg);

        try {
            $importer = new ShipmentsImport($this->defaultSeller);
            $importer->importFile($this->filePath, $this->defaultSeller);

            $doneMsg = "ProcessExcelImportJob successfully completed streaming import for: {$this->filePath}";
            dump($doneMsg);
            echo $doneMsg . "\n";
            Log::info($doneMsg);
        } catch (Throwable $e) {
            dump("ERROR IMPORT: " . $e->getMessage());
            echo "ERROR IMPORT: " . $e->getMessage() . "\n";
            Log::error("ProcessExcelImportJob error processing file {$this->filePath}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            // Delete raw uploaded temp file after import to free disk space
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
                Log::info("ProcessExcelImportJob deleted temp file: {$this->filePath}");
            }
        }
    }
}
