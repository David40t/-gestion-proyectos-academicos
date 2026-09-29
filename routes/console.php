<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tareas programadas. En el servidor debe existir el cron:
|   * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('tasks:check-deadlines')->dailyAt('00:05')->withoutOverlapping();
