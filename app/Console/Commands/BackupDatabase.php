<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup the SQLite database to the storage/backups directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $databasePath = database_path('database.sqlite');
        
        if (!File::exists($databasePath)) {
            $this->error("Database file not found at: $databasePath");
            return 1;
        }

        $backupDir = storage_path('backups');
        
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupPath = $backupDir . "/database_backup_$timestamp.sqlite";

        try {
            File::copy($databasePath, $backupPath);
            $this->info("Database successfully backed up to:");
            $this->line($backupPath);
            
            // Clean up old backups (keep last 5)
            $files = File::files($backupDir);
            if (count($files) > 5) {
                usort($files, function($a, $b) {
                    return $b->getMTime() - $a->getMTime();
                });
                
                $filesToDelete = array_slice($files, 5);
                foreach ($filesToDelete as $file) {
                    File::delete($file);
                }
                $this->comment("Cleaned up " . count($filesToDelete) . " old backups.");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
            return 1;
        }
    }
}
