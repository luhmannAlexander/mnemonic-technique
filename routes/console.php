<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-purge trash + expired upload stagings daily (BackendSchema §8.3).
Schedule::command('trash:purge')->dailyAt('03:00');
