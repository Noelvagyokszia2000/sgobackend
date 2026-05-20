<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Services\DiscordNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class NewsController extends Controller
{
    public function index()
    {
        return News::query()
            ->with('author:id,username,IgName,profileImage')
            ->whereNull('deleted_at')
            ->where('published_at', '>=', Carbon::now(config('app.timezone'))->subDays(2))
            ->orderBy('published_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function adminIndex()
    {
        return News::query()
            ->with('author:id,username,IgName,profileImage')
            ->whereNull('deleted_at')
            ->orderBy('published_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'created_by' => 'required|exists:users,id',
            'text' => 'required|string|max:4000',
            'imageFile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $news = News::create([
            'created_by' => $validated['created_by'],
            'text' => trim($validated['text']),
            'image' => $request->hasFile('imageFile')
                ? $this->storeImageFile($request, 'imageFile', 'news-images')
                : null,
            'published_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);

        $news->load('author:id,username,IgName,profileImage');
        app(DiscordNotifier::class)->sendNews($news);

        return response()->json([
            'message' => 'Hír sikeresen kiírva.',
            'news' => $news,
        ], 201);
    }

    public function destroy($id)
    {
        $news = News::query()
            ->whereNull('deleted_at')
            ->find($id);

        if (!$news) {
            return response()->json([
                'message' => 'Hír nem található.'
            ], 404);
        }

        $news->deleted_at = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $news->save();

        return response()->json([
            'message' => 'Hír törölve.'
        ]);
    }

    private function storeImageFile(Request $request, string $field, string $directory): string
    {
        $disk = $this->imageStorageDisk();

        try {
            $path = Storage::disk($disk)->putFile($directory, $request->file($field));
        } catch (\Throwable $exception) {
            Log::warning('News image upload failed', [
                'field' => $field,
                'directory' => $directory,
                'disk' => $disk,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $field => 'A kép feltöltése nem sikerült.'
            ]);
        }

        if (!$path) {
            throw ValidationException::withMessages([
                $field => 'A kép feltöltése nem sikerült.'
            ]);
        }

        return $this->imageStorageUrl($path);
    }

    private function imageStorageUrl(string $path): string
    {
        $disk = $this->imageStorageDisk();
        $diskUrl = config("filesystems.disks.{$disk}.url");

        if ($diskUrl) {
            return rtrim($diskUrl, '/') . '/' . ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }

    private function imageStorageDisk(): string
    {
        return config('filesystems.image_disk', 'public');
    }
}
