<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Xserverのスケジュールタスクから毎分 `php artisan schedule:run` を呼び出す想定。
Schedule::command('app:archive-completed-cards')->dailyAt('02:00');
Schedule::command('app:purge-archived-cards')->dailyAt('02:15');
