<?php

return [
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    'restore_drill_db' => env('BACKUP_RESTORE_DRILL_DB', 'ltp_restore_drill'),
    'restore_admin_db' => env('BACKUP_RESTORE_ADMIN_DB', 'postgres'),
];
