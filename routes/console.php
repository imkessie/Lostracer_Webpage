<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FR-13: Pemeliharaan harian masa simpan 3/6 bulan dan klaim tidak diambil > 7 hari
Schedule::command('lostracer:daily-maintenance')->daily();
