<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('realtime:presence:prune')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('esign:cleanup-prepared-renditions --delete')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();
