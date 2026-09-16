<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Services\SystemBackupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemBackupController extends Controller
{
    public function export(SystemBackupService $backups): BinaryFileResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $path = $backups->create();
        AuditTrail::log(auth()->user(), 'exported', auth()->user(), 'Full system backup exported.', [
            'file' => basename($path), 'format_version' => 2,
        ]);

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
