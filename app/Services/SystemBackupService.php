<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SystemBackupService
{
    public function create(): string
    {
        $directory = storage_path('app/private/backups/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Unable to create backup archive.');
        }
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() === 'mysql' && $connection->transactionLevel() === 0) {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $manifest = $connection->transaction(function () use ($connection, $directory, $zip) {
                $manifest = ['format_version' => 2, 'exported_at' => now()->toISOString(), 'driver' => $connection->getDriverName(), 'tables' => []];
                foreach ($connection->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
                    if (in_array($table, ['cache', 'cache_locks', 'sessions', 'password_reset_tokens', 'jobs', 'failed_jobs', 'job_batches'])) continue;
                    $columns = $connection->getSchemaBuilder()->getColumnListing($table);
                    $file = $directory.'/'.hash('sha256', $table).'.jsonl';
                    $handle = fopen($file, 'wb');
                    if ($handle === false) throw new RuntimeException('Unable to write backup.');
                    $count = 0;
                    try {
                        foreach ($connection->table($table)->orderBy($columns[0])->cursor() as $row) {
                            $line = json_encode((array) $row, JSON_THROW_ON_ERROR)."\n";
                            if (fwrite($handle, $line) !== strlen($line)) throw new RuntimeException('Backup storage is full.');
                            $count++;
                        }
                    } finally {
                        fclose($handle);
                    }
                    $zip->addFile($file, basename($file));
                    $schema = $connection->getDriverName() === 'mysql'
                        ? array_values((array) $connection->selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`'))[1]
                        : $connection->selectOne('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?', ['table', $table])->sql;
                    $manifest['tables'][$table] = ['file' => basename($file), 'columns' => $columns, 'schema' => $schema, 'rows' => $count, 'sha256' => hash_file('sha256', $file)];
                }
                return $manifest;
            });
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            if (! $zip->close()) throw new RuntimeException('Unable to finalize backup.');
            return $path;
        } catch (\Throwable $error) {
            try { $zip->close(); } catch (\Throwable) { }
            @unlink($path);
            throw $error;
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /** Restore drill only: accepts an empty in-memory SQLite database. */
    public function restoreForVerification(string $path, Connection $target): array
    {
        if ($target->getDriverName() !== 'sqlite' || $target->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Restore verification is restricted to an in-memory database.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Cannot open backup.');
        try {
            $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['format_version'] ?? null) !== 2) throw new RuntimeException('Unsupported backup format.');
            if (($manifest['driver'] ?? null) !== 'sqlite') throw new RuntimeException('MySQL backups require a separate MySQL restore drill; do not restore them into SQLite.');
            foreach ($manifest['tables'] as $table => $entry) {
                if ($target->getSchemaBuilder()->hasTable($table) && $target->table($table)->exists()) throw new RuntimeException('Restore target must be empty.');
                $contents = $zip->getFromName($entry['file']);
                if ($contents === false || !hash_equals($entry['sha256'], hash('sha256', $contents))) throw new RuntimeException('Backup checksum mismatch.');
            }
            $target->transaction(function () use ($target, $manifest, $zip) {
                $target->statement('PRAGMA defer_foreign_keys = ON');
                foreach ($manifest['tables'] as $table => $entry) {
                    if (!$target->getSchemaBuilder()->hasTable($table)) $target->statement($entry['schema']);
                }
                foreach ($manifest['tables'] as $table => $entry) {
                    $count = 0;
                    foreach (explode("\n", trim($zip->getFromName($entry['file']))) as $line) {
                        if ($line === '') continue;
                        $target->table($table)->insert(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
                        $count++;
                    }
                    if ($count !== $entry['rows']) throw new RuntimeException('Backup row count mismatch.');
                }
                if ($target->select('PRAGMA foreign_key_check')) throw new RuntimeException('Backup relationships are inconsistent.');
            });
            return $manifest;
        } finally {
            $zip->close();
        }
    }
}
