<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImageStorageService
{
    public function disk(): string
    {
        return config('filesystems.image_disk', 'public');
    }

    public function url(string $path): string
    {
        $disk = $this->disk();
        $diskUrl = config("filesystems.disks.{$disk}.url");

        if ($diskUrl) {
            return rtrim($diskUrl, '/') . '/' . ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }

    public function normalizeUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $url = trim($url);
        $diskUrl = config('filesystems.disks.' . $this->disk() . '.url');

        if (!$diskUrl) {
            return $url;
        }

        if (!preg_match('#^https?://#i', $url)) {
            return rtrim($diskUrl, '/') . '/' . ltrim(preg_replace('#^storage/#', '', $url), '/');
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (!$path || !str_starts_with($path, '/storage/')) {
            return $url;
        }

        return rtrim($diskUrl, '/') . '/' . ltrim(substr($path, strlen('/storage/')), '/');
    }

    public function delete(?string $url): void
    {
        if (!$url) {
            return;
        }

        $startedAt = microtime(true);
        $disk = $this->disk();
        $diskUrl = config("filesystems.disks.{$disk}.url");
        $path = parse_url($url, PHP_URL_PATH);

        if (!$path) {
            return;
        }

        $urlHost = parse_url($url, PHP_URL_HOST);
        $diskHost = $diskUrl ? parse_url($diskUrl, PHP_URL_HOST) : null;

        if ($diskHost && $urlHost && $urlHost !== $diskHost) {
            return;
        }

        $diskPathPrefix = $diskUrl ? (parse_url($diskUrl, PHP_URL_PATH) ?: '') : '';

        if ($diskPathPrefix && str_starts_with($path, $diskPathPrefix)) {
            $path = substr($path, strlen($diskPathPrefix));
        } elseif (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        try {
            Storage::disk($disk)->delete(ltrim($path, '/'));

            Log::info('Image delete finished', [
                'disk' => $disk,
                'path' => ltrim($path, '/'),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Image delete failed', [
                'disk' => $disk,
                'url' => $url,
                'path' => ltrim($path, '/'),
                'error' => $exception->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
        }
    }

    public function storeUploadedFile(
        Request $request,
        string $field,
        string $directory,
        string $uploadPrefix = 'image'
    ): string {
        $disk = $this->disk();
        $uploadId = uniqid($uploadPrefix . '_', true);
        $startedAt = microtime(true);
        $file = $request->file($field);

        Log::info('Image upload started', [
            'upload_id' => $uploadId,
            'field' => $field,
            'directory' => $directory,
            'disk' => $disk,
            'file_size_bytes' => $file?->getSize(),
            'file_size_kb' => $file ? round($file->getSize() / 1024, 2) : null,
            'client_mime' => $file?->getClientMimeType(),
            'detected_mime' => $file?->getMimeType(),
            'disk_url' => config("filesystems.disks.{$disk}.url"),
        ]);

        try {
            $path = $this->putFile($disk, $request, $field, $directory, $uploadId);
        } catch (\Throwable $exception) {
            Log::error('Image upload failed', [
                'upload_id' => $uploadId,
                'field' => $field,
                'directory' => $directory,
                'disk' => $disk,
                'error' => $exception->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            throw ValidationException::withMessages([
                $field => 'A kep feltoltese nem sikerult a kulso tarhelyre. Ellenorizd a tarhely beallitasait.'
            ]);
        }

        if (!$path) {
            Log::error('Image upload returned empty path', [
                'upload_id' => $uploadId,
                'field' => $field,
                'directory' => $directory,
                'disk' => $disk,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            throw ValidationException::withMessages([
                $field => 'A kep feltoltese nem sikerult a kulso tarhelyre.'
            ]);
        }

        $url = $this->url($path);

        Log::info('Image upload finished', [
            'upload_id' => $uploadId,
            'field' => $field,
            'directory' => $directory,
            'disk' => $disk,
            'path' => $path,
            'url' => $url,
            'duration_ms' => $this->elapsedMs($startedAt),
        ]);

        return $url;
    }

    private function putFile(
        string $disk,
        Request $request,
        string $field,
        string $directory,
        string $uploadId
    ): string|false {
        $attempt = 0;

        return retry(3, function () use ($disk, $request, $field, $directory, $uploadId, &$attempt) {
            $attempt++;
            $attemptStartedAt = microtime(true);

            try {
                $path = $disk !== 'cpanel_images'
                    ? Storage::disk($disk)->putFile($directory, $request->file($field))
                    : Storage::disk($disk)->putFileAs(
                        '',
                        $request->file($field),
                        trim($directory, '/') . '-' . $request->file($field)->hashName()
                    );

                Log::info('Image upload attempt finished', [
                    'upload_id' => $uploadId,
                    'attempt' => $attempt,
                    'disk' => $disk,
                    'path' => $path,
                    'duration_ms' => $this->elapsedMs($attemptStartedAt),
                ]);

                return $path;
            } catch (\Throwable $exception) {
                Log::warning('Image upload attempt failed', [
                    'upload_id' => $uploadId,
                    'attempt' => $attempt,
                    'disk' => $disk,
                    'error' => $exception->getMessage(),
                    'duration_ms' => $this->elapsedMs($attemptStartedAt),
                ]);

                throw $exception;
            }
        }, 750);
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
