<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TestImageStorage extends Command
{
    protected $signature = 'images:test-storage';

    protected $description = 'Test the configured image storage disk';

    public function handle(): int
    {
        $diskName = config('filesystems.image_disk', 'public');
        $fileName = 'storage-test-' . now()->format('Ymd-His') . '.txt';
        $path = $diskName === 'cpanel_images'
            ? $fileName
            : 'storage-tests/' . $fileName;

        $this->info('IMAGE_STORAGE_DISK=' . $diskName);
        $this->line('Disk URL: ' . (config("filesystems.disks.{$diskName}.url") ?: 'nincs beallitva'));
        $this->line('Path: ' . $path);

        try {
            Storage::disk($diskName)->put($path, 'SGO storage test ' . now()->toDateTimeString());
        } catch (Throwable $exception) {
            $this->error('Feltoltes sikertelen: ' . $exception->getMessage());

            return self::FAILURE;
        }

        try {
            if (!Storage::disk($diskName)->exists($path)) {
                $this->error('A feltoltes utan a fajl nem talalhato a disken.');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error('Ellenorzes sikertelen: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $url = config("filesystems.disks.{$diskName}.url")
            ? rtrim(config("filesystems.disks.{$diskName}.url"), '/') . '/' . $path
            : Storage::disk($diskName)->url($path);

        $this->info('Feltoltes sikeres.');
        $this->line('URL: ' . $url);

        return self::SUCCESS;
    }
}
