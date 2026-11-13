<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evening nudge so clients get workout/check-in/renewal pings after the day.
Schedule::command('reminders:send')->dailyAt('18:00');
