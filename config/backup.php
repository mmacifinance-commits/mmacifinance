<?php

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'prefix' => 'scheduled-backups',
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
];
