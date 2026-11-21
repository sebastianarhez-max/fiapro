<?php

use Illuminate\Support\Facades\Schedule;

// Genera los listados de pago cada quincena (días 15 y último de cada mes).
// Para que corra solo: php artisan schedule:work (desarrollo) o el cron / Programador de tareas de Windows.
Schedule::command('fiapro:generar-listados')->dailyAt('06:00');
