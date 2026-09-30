<?php

namespace App\Console\Commands;

use App\Services\SystemBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateSystemBackup extends Command
{
    protected $signature = 'system:backup';

    protected $description = 'Create and verify a database backup, store it privately, and apply retention';

    public function handle(SystemBackupService $service): int
    {
        $diskName = config('backup.disk');
        if (app()->environment('production') && config("filesystems.disks.{$diskName}.driver") !== 's3') {
            $this->error('Production backups require a private S3-compatible disk. Set BACKUP_DISK to the attached Cloud object storage disk.');

            return self::FAILURE;
        }
        $path = $service->create();
        try {
            $service->verifyArchive($path);
            $disk = Storage::disk($diskName);
            $key = config('backup.prefix').'/'.now()->format('Y-m-d-His').'-'.basename($path);
            $stream = fopen($path, 'rb');
            try {
                if (! $disk->put($key, $stream, ['visibility' => 'private'])) {
                    throw new \RuntimeException('Backup upload failed.');
                }
            } finally {
                fclose($stream);
            }
            // Read back the stored copy before removing any older backup.
            $remote = $disk->readStream($key);
            if (! is_resource($remote)) {
                throw new \RuntimeException('Cannot verify uploaded backup.');
            }
            try {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $remote);
                if (! hash_equals(hash_file('sha256', $path), hash_final($hash))) {
                    throw new \RuntimeException('Uploaded backup checksum mismatch.');
                }
            } finally {
                fclose($remote);
            }
            $cutoff = now()->subDays(max(1, config('backup.retention_days')))->timestamp;
            foreach ($disk->files(config('backup.prefix')) as $old) {
                if ($old !== $key && str_ends_with($old, '.zip') && $disk->lastModified($old) < $cutoff) {
                    if (! $disk->delete($old)) {
                        throw new \RuntimeException('Backup retention cleanup failed.');
                    }
                }
            }
            $this->info('Verified private backup saved: '.$key);

            return self::SUCCESS;
        } finally {
            @unlink($path);
        }
    }
}
