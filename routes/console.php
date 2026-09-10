<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:expire')->daily();

Schedule::command('marketplace:housekeeping')->daily();

Schedule::command('ads:expire')->daily();

Schedule::command('maintenance:escalate')->dailyAt('06:00');