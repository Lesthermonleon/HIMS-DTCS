<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled database backup (checks settings table dynamically)
Schedule::command('hims:backup-run --trigger=scheduled')
    ->everyMinute()
    ->when(function () {
        /** @var \App\Services\Settings\SettingsService $settings */
        $settings = app(\App\Services\Settings\SettingsService::class);
        if (! (bool) $settings->get('backup.enabled', false)) {
            return false;
        }
        $backupTime = (string) $settings->get('backup.time', '02:00');
        return now()->format('H:i') === $backupTime;
    })
    ->withoutOverlapping();

// Daily pruning of old backups
Schedule::command('hims:backup-prune')
    ->dailyAt('03:30');

