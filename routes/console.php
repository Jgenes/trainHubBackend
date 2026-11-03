<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Safety net for lost PesaPal IPNs: re-read the gateway for every order that is
// still open so a learner who really paid is never left locked out. Needs the
// scheduler cron entry (`php artisan schedule:run` every minute).
Schedule::command('payments:reconcile --days=7')
    ->hourly()
    ->withoutOverlapping();
