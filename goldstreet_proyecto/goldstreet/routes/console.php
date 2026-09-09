<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo diario al cierre del restaurante (requiere `php artisan schedule:work`
// o una tarea programada de Windows que ejecute `php artisan schedule:run` cada minuto)
Schedule::command('backup:db --keep=7')->dailyAt('23:30');
