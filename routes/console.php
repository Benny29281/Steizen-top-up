<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('catalog:sync')->everySixHours()->withoutOverlapping()
    ->when(fn () => config('store.catalog_source') === 'digiflazz');

Schedule::command('payments:reconcile --limit=20')->everyFiveMinutes()->withoutOverlapping(60)
    ->when(fn () => config('store.mode') === 'sandbox');
