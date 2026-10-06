<?php

use App\Services\Alertes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('finance:alertes-charges', function () {
    $this->info(Alertes::charges().' notification(s) envoyée(s).');
})->purpose('Notifie les charges fixes en retard (et, le 1er du mois, celles à régler)');

Schedule::command('finance:alertes-charges')->dailyAt('08:00');
