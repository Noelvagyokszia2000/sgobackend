<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('images:test-storage', function () {
    $diskName = config('filesystems.image_disk', 'public');
    $fileName = 'storage-test-' . now()->format('Ymd-His') . '.txt';
    $path = 'storage-tests/' . $fileName;

    $this->info('IMAGE_STORAGE_DISK=' . $diskName);
    $this->line('Disk URL: ' . (config("filesystems.disks.{$diskName}.url") ?: 'nincs beallitva'));

    try {
        Storage::disk($diskName)->put($path, 'SGO storage test ' . now()->toDateTimeString());
    } catch (Throwable $exception) {
        $this->error('Feltoltes sikertelen: ' . $exception->getMessage());
        return 1;
    }

    if (!Storage::disk($diskName)->exists($path)) {
        $this->error('A feltoltes utan a fajl nem talalhato a disken.');
        return 1;
    }

    $url = config("filesystems.disks.{$diskName}.url")
        ? rtrim(config("filesystems.disks.{$diskName}.url"), '/') . '/' . $path
        : Storage::disk($diskName)->url($path);

    $this->info('Feltoltes sikeres.');
    $this->line('Path: ' . $path);
    $this->line('URL: ' . $url);

    return 0;
})->purpose('Test the configured image storage disk');
