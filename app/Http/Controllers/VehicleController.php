<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleKey;
use App\Models\VehicleWarning;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VehicleController extends Controller
{
    public function index()
    {
        $this->removeExpiredWarnings();

        return Vehicle::with([
            'keyholders:id,username,IgName',
            'warnings' => function ($query) {
                $query->with('issuer:id,username,IgName')
                    ->orderBy('created_at', 'desc');
            }
        ])
            ->orderBy('plate', 'asc')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vin' => 'required|string|max:64|unique:vehicles,vin',
            'plate' => 'required|string|max:32|unique:vehicles,plate',
            'max_keys' => 'nullable|integer|min:1|max:99',
            'all_members' => 'nullable|boolean',
            'image' => 'nullable|string',
            'imageFile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096'
        ]);

        $imageUrl = $request->hasFile('imageFile')
            ? $this->storeVehicleImage($request)
            : $this->normalizeImageUrl($validated['image'] ?? null);

        $vehicle = Vehicle::create([
            'name' => trim($validated['name']),
            'vin' => trim($validated['vin']),
            'plate' => trim($validated['plate']),
            'image' => $imageUrl,
            'warns' => 0,
            'max_keys' => $validated['max_keys'] ?? 5,
            'all_members' => $validated['all_members'] ?? false
        ]);

        return response()->json([
            'message' => 'Jármű sikeresen létrehozva.',
            'vehicle' => $vehicle->load([
                'keyholders:id,username,IgName',
                'warnings.issuer:id,username,IgName'
            ])
        ], 201);
    }

    public function pendingKeyRequests()
    {
        $items = VehicleKey::with([
            'vehicle:id,name,vin,plate,image,warns,max_keys,all_members',
            'user:id,username,IgName'
        ])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($items, 200);
    }

    public function requestKey(Request $request, $id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $vehicle = Vehicle::with('keyholders:id,username,IgName')
            ->find($id);

        if (!$vehicle) {
            return response()->json([
                'message' => 'Jármű nem található.'
            ], 404);
        }

        $existingRequest = VehicleKey::where('vehicle_id', $vehicle->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existingRequest?->status === 'approved') {
            return response()->json([
                'message' => 'Ehhez a járműhöz már van kulcsod.'
            ], 400);
        }

        if ($existingRequest?->status === 'pending') {
            return response()->json([
                'message' => 'Ehhez a járműhöz már van függőben lévő kulcsigénylésed.'
            ], 400);
        }

        if ($vehicle->all_members) {
            return response()->json([
                'message' => 'Ehhez a járműhöz minden tag automatikusan kap kulcsot.'
            ], 400);
        }

        if ($vehicle->keyholders()->count() >= $vehicle->max_keys) {
            return response()->json([
                'message' => 'Ehhez a járműhöz már elérte a kulcsok száma a maximumot.'
            ], 400);
        }

        if ($existingRequest?->status === 'rejected') {
            $existingRequest->status = 'pending';
            $existingRequest->save();
        } else {
            VehicleKey::create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $validated['user_id'],
                'status' => 'pending'
            ]);
        }

        return response()->json([
            'message' => 'Kulcsigénylés elküldve. Admin jóváhagyásra vár.'
        ], 200);
    }

    public function acceptKeyRequest($id)
    {
        $requestItem = VehicleKey::with('vehicle.keyholders')
            ->find($id);

        if (!$requestItem) {
            return response()->json([
                'message' => 'Kulcsigénylés nem található.'
            ], 404);
        }

        if ($requestItem->status === 'approved') {
            return response()->json([
                'message' => 'Ez a kulcsigénylés már el lett fogadva.'
            ], 400);
        }

        if ($requestItem->vehicle->keyholders()->count() >= $requestItem->vehicle->max_keys) {
            return response()->json([
                'message' => 'Ehhez a járműhöz már elérte a kulcsok száma a maximumot.'
            ], 400);
        }

        $requestItem->status = 'approved';
        $requestItem->save();

        return response()->json([
            'message' => 'Kulcsigénylés elfogadva.',
            'item' => $requestItem->load([
                'vehicle:id,name,vin,plate,image,warns,max_keys,all_members',
                'user:id,username,IgName'
            ])
        ], 200);
    }

    public function rejectKeyRequest($id)
    {
        $requestItem = VehicleKey::find($id);

        if (!$requestItem) {
            return response()->json([
                'message' => 'Kulcsigénylés nem található.'
            ], 404);
        }

        if ($requestItem->status !== 'pending') {
            return response()->json([
                'message' => 'Csak függőben lévő kulcsigénylést lehet elutasítani.'
            ], 400);
        }

        $requestItem->status = 'rejected';
        $requestItem->save();

        return response()->json([
            'message' => 'Kulcsigénylés elutasítva.'
        ], 200);
    }

    public function removeKey($vehicleId, $userId)
    {
        $key = VehicleKey::where('vehicle_id', $vehicleId)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->first();

        if (!$key) {
            return response()->json([
                'message' => 'A megadott kulcs nem található.'
            ], 404);
        }

        $key->delete();

        return response()->json([
            'message' => 'Kulcs sikeresen elvéve.'
        ], 200);
    }

    public function addWarning(Request $request, $id)
    {
        $this->removeExpiredWarnings();

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'issued_by' => 'nullable|exists:users,id'
        ]);

        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'message' => 'Jármű nem található.'
            ], 404);
        }

        if ($vehicle->warns >= 5) {
            return response()->json([
                'message' => 'Ez a jármű már elérte az 5 figyelmeztetést.'
            ], 400);
        }

        $warning = VehicleWarning::create([
            'vehicle_id' => $vehicle->id,
            'vehicle_name' => $vehicle->name,
            'reason' => trim($validated['reason']),
            'issued_by' => $validated['issued_by'] ?? null
        ]);

        $vehicle->warns = min(5, $vehicle->warns + 1);
        $vehicle->save();

        return response()->json([
            'message' => 'Figyelmeztetés sikeresen hozzáadva.',
            'warning' => $warning,
            'vehicle' => $vehicle->load([
                'keyholders:id,username,IgName',
                'warnings.issuer:id,username,IgName'
            ])
        ], 201);
    }

    public function deleteWarning($id)
    {
        $warning = VehicleWarning::find($id);

        if (!$warning) {
            return response()->json([
                'message' => 'Figyelmeztetés nem található.'
            ], 404);
        }

        $vehicle = Vehicle::find($warning->vehicle_id);

        $warning->delete();

        if ($vehicle) {
            $this->syncVehicleWarningCount($vehicle);
        }

        return response()->json([
            'message' => 'Figyelmeztetés sikeresen törölve.'
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vin' => 'required|string|max:64|unique:vehicles,vin,' . $vehicle->id,
            'plate' => 'required|string|max:32|unique:vehicles,plate,' . $vehicle->id,
            'image' => 'nullable|string',
            'warns' => 'required|integer|min:0|max:5',
            'max_keys' => 'required|integer|min:1|max:99',
            'all_members' => 'nullable|boolean'
        ]);

        $issuedKeys = $vehicle->keyholders()->count();

        if ((int) $validated['max_keys'] < $issuedKeys) {
            return response()->json([
                'message' => 'A kulcsok maximuma nem lehet kisebb, mint a mĂˇr kiadott kulcsok szĂˇma.'
            ], 422);
        }

        $vehicle->update([
            'name' => trim($validated['name']),
            'vin' => trim($validated['vin']),
            'plate' => trim($validated['plate']),
            'image' => $this->normalizeImageUrl($validated['image'] ?? null),
            'warns' => $validated['warns'],
            'max_keys' => $validated['max_keys'],
            'all_members' => $validated['all_members'] ?? false
        ]);

        return response()->json([
            'message' => 'Jármű sikeresen frissítve.',
            'vehicle' => $vehicle->load('keyholders:id,username,IgName')
        ], 200);
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::findOrFail($id);

        $this->deletePublicStorageFile($vehicle->image);

        $vehicle->delete();

        return response()->json([
            'message' => 'Jármű sikeresen törölve.'
        ], 200);
    }

    private function normalizeImageUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');

        if ($host === 'i.imgur.com') {
            return $url;
        }

        if ($host === 'imgur.com' || $host === 'www.imgur.com') {
            $segments = explode('/', $path);
            $imageId = end($segments);

            if ($imageId && preg_match('/^[a-zA-Z0-9]+$/', $imageId) && !in_array($segments[0] ?? '', ['a', 'gallery'], true)) {
                return 'https://i.imgur.com/' . $imageId . '.jpg';
            }

            $directImage = $this->resolveImgurPageImage($url);

            if ($directImage) {
                return $directImage;
            }

            throw ValidationException::withMessages([
                'image' => 'Az Imgur album link nem közvetlen képlink. Kérlek a képet külön nyisd meg, majd az i.imgur.com/...jpg vagy i.imgur.com/...png linket add meg.'
            ]);
        }

        return $this->normalizeStoredImageUrl($url);
    }

    private function resolveImgurPageImage(string $url): ?string
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0'
                ])
                ->get($url);
        } catch (\Throwable $exception) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $html = $response->body();

        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }

        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }

        return null;
    }

    private function removeExpiredWarnings(): void
    {
        $expiredWarnings = VehicleWarning::where(
            'created_at',
            '<',
            Carbon::now()->subDays(30)
        )->get();

        if ($expiredWarnings->isEmpty()) {
            return;
        }

        $vehicleIds = $expiredWarnings
            ->pluck('vehicle_id')
            ->unique()
            ->values();

        VehicleWarning::whereIn('id', $expiredWarnings->pluck('id'))->delete();

        Vehicle::whereIn('id', $vehicleIds)
            ->get()
            ->each(function (Vehicle $vehicle) {
                $this->syncVehicleWarningCount($vehicle);
            });
    }

    private function syncVehicleWarningCount(Vehicle $vehicle): void
    {
        $vehicle->warns = min(5, $vehicle->warnings()->count());
        $vehicle->save();
    }

    private function storeVehicleImage(Request $request): string
    {
        $disk = $this->imageStorageDisk();
        $path = $this->putVehicleImageFile($disk, $request);

        if (!$path) {
            throw ValidationException::withMessages([
                'imageFile' => 'A jármű képének feltöltése nem sikerült.'
            ]);
        }

        return $this->imageStorageUrl($path);
    }

    private function deletePublicStorageFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        $disk = $this->imageStorageDisk();
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
        } catch (\Throwable $exception) {
            Log::warning('Vehicle image delete failed', [
                'disk' => $disk,
                'url' => $url,
                'path' => ltrim($path, '/'),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeStoredImageUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $url = trim($url);
        $diskUrl = config('filesystems.disks.' . $this->imageStorageDisk() . '.url');

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

    private function putVehicleImageFile(string $disk, Request $request): string|false
    {
        return Storage::disk($disk)->putFile('vehicle-images', $request->file('imageFile'));
    }
}
