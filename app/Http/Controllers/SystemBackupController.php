<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SystemBackupController extends Controller
{
    public function export(): BinaryFileResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $timestamp = now()->format('Ymd-His');
        $directory = storage_path("app/backups/system-{$timestamp}");
        $zipPath = storage_path("app/backups/system-backup-{$timestamp}.zip");

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach ($this->tables() as $table) {
            $path = "{$directory}/{$table}.csv";
            $handle = fopen($path, 'w');
            $columns = Schema::getColumnListing($table);
            fputcsv($handle, $columns);

            DB::table($table)->orderBy($columns[0] ?? 'id')->chunk(500, function ($rows) use ($handle, $columns) {
                foreach ($rows as $row) {
                    $row = (array) $row;
                    fputcsv($handle, array_map(fn ($column) => $row[$column] ?? null, $columns));
                }
            });

            fclose($handle);
        }

        $manifestPath = "{$directory}/manifest.json";
        file_put_contents($manifestPath, json_encode([
            'exported_at' => now()->toISOString(),
            'exported_by' => auth()->user()?->only(['id', 'name', 'role']),
            'tables' => $this->tables(),
        ], JSON_PRETTY_PRINT));

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (glob("{$directory}/*") ?: [] as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        AuditTrail::log(auth()->user(), 'exported', auth()->user(), 'Full system backup exported.', [
            'file' => basename($zipPath),
            'tables' => $this->tables(),
        ]);

        return response()->download($zipPath)->deleteFileAfterSend(false);
    }

    private function tables(): array
    {
        return collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0] ?? null)
            ->filter()
            ->reject(fn ($table) => $table === 'cache' || str_starts_with((string) $table, 'password_reset'))
            ->values()
            ->all();
    }
}
