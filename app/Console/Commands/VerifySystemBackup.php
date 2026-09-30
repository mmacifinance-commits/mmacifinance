<?php

namespace App\Console\Commands;

use App\Services\SystemBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifySystemBackup extends Command
{
    protected $signature = 'system:backup-verify {path : Local trusted backup archive} {--restore-connection= : Disposable restore connection (optional)}';

    protected $description = 'Verify archive checksums and optionally restore into a disposable empty database';

    public function handle(SystemBackupService $service): int
    {
        $path = $this->argument('path');
        $manifest = $service->verifyArchive($path);
        if ($name = $this->option('restore-connection')) {
            $target = DB::connection($name);
            $manifest['driver'] === 'sqlite'
                ? $service->restoreForVerification($path, $target)
                : $service->restoreMysqlDrill($path, $target);
            $this->info('Restore drill passed: rows and relationships verified.');
        } else {
            $this->info('Archive checksums and row counts verified. No restore was performed.');
        }

        return self::SUCCESS;
    }
}
