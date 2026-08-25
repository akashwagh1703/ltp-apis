#!/usr/bin/env bash
# Crontab wrapper. Prefer Laravel's scheduler:
#   * * * * * cd /var/www/playltp/api && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
# Direct call:
#   30 2 * * * cd /var/www/playltp/api && php artisan backup:database >> storage/logs/backup.log 2>&1
set -euo pipefail
cd "$(dirname "$0")/.."
php artisan backup:database
