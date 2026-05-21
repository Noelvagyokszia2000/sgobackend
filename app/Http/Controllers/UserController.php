<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index($id)
    {
        $user = User::with('rank:id,name,available')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        return response()->json($this->prepareUserResponse($user), 200);
    }

    public function addUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:4',
            'IgName' => 'required|string|max:255',
            'rank_id' => 'required|exists:ranks,id'
        ]);

        $user = User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'IgName' => $request->IgName,
            'createdAt' => now()->toDateString(),
            'warn' => 0,
            'weeklyPay' => now()->addWeek()->toDateString(),
            'weeklyPaymentRequired' => true,
            'isAdmin' => false,
            'rank_id' => $request->rank_id,
            'successfulCassettes' => 0
        ]);

        return response()->json([
            'message' => 'Felhasználó létrehozva',
            'user' => $this->prepareUserResponse($user->load('rank:id,name,available'))
        ], 201);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $this->deleteImageStorageFile($user->profileImage);

        $user->delete();

        return response()->json([
            'message' => 'Felhasználó törölve'
        ], 200);
    }

    public function isAdmin($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        return response()->json([
            'isAdmin' => (bool) $user->isAdmin
        ], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'username' => 'required|string',
                'password' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::with('rank:id,name,available')
            ->where('username', $request->username)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Wrong password'
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $this->prepareUserResponse($user)
        ], 200);
    }

    public function getAllUsers()
{
    $users = User::with('rank:id,name,available')
        ->select('id', 'username', 'discord_id', 'IgName', 'warn', 'isAdmin', 'rank_id', 'lastRankup', 'createdAt', 'weeklyPay', 'weeklyPaymentRequired', 'profileImage', 'successfulCassettes')
        ->orderByDesc('rank_id')
        ->orderBy('IgName', 'asc')
        ->get();

    return response()->json($users->map(fn (User $user) => $this->prepareUserResponse($user))->values(), 200);
}

    public function changeUsername(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'username' => 'required|string|max:255|unique:users,username'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $user->username = $request->username;
        $user->save();

        return response()->json([
            'message' => 'Felhasználónév frissítve',
            'user' => $this->prepareUserResponse($user->load('rank:id,name,available'))
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'oldPassword' => 'required|string',
            'newPassword' => 'required|string|min:4'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        if (!Hash::check($request->oldPassword, $user->password)) {
            return response()->json([
                'message' => 'A régi jelszó hibás'
            ], 401);
        }

        $user->password = Hash::make($request->newPassword);
        $user->save();

        return response()->json([
            'message' => 'Jelszó sikeresen frissítve',
            'user' => $this->prepareUserResponse($user->load('rank:id,name,available'))
        ], 200);
    }

    public function uploadProfileImage(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'profileImage' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $previousProfileImage = $user->profileImage;
        $user->profileImage = $this->storeImageFile($request, 'profileImage', 'profile-images');

        $user->save();

        app()->terminating(function () use ($previousProfileImage) {
            $this->deleteImageStorageFile($previousProfileImage);
        });

        return response()->json([
            'message' => 'Profilkép feltöltve',
            'user' => $this->prepareUserResponse($user->load('rank:id,name,available'))
        ], 201);
    }

    public function changeName(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'IgName' => 'required|string|max:255'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $user->IgName = $request->IgName;
        $user->save();

        return response()->json([
            'message' => 'Név frissítve',
            'user' => $this->prepareUserResponse($user->load('rank:id,name,available'))
        ], 200);
    }

    public function changeRank(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'rank_id' => 'required|exists:ranks,id'
        ]);

        $user = User::find($request->userId);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $user->rank_id = $request->rank_id;
        $user->save();

        return response()->json([
            'message' => 'Rang frissítve',
            'user' => $this->prepareUserResponse($user->load('rank:id,name,available'))
        ], 200);
    }

    public function updateWeeklyPaymentRequired(Request $request, $id)
    {
        $validated = $request->validate([
            'weeklyPaymentRequired' => 'required|boolean'
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'FelhasznĂˇlĂł nem talĂˇlhatĂł'
            ], 404);
        }

        $isRequired = (bool) $validated['weeklyPaymentRequired'];

        $user->weeklyPaymentRequired = $isRequired;

        if ($isRequired) {
            $user->weeklyPay = now()->toDateString();
        }

        $user->save();

        return response()->json([
            'message' => $isRequired
                ? 'A heti leadandĂł fizetĂ©se visszakapcsolva.'
                : 'A heti leadandĂł fizetĂ©se kikapcsolva.',
            'user' => $this->prepareUserResponse($user->load('rank:id,name,available'))
        ], 200);
    }

    public function incrementSuccessfulCassettes(Request $request, $id)
    {
        $request->validate([
            'amount' => 'nullable|integer|min:1|max:10'
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $amount = (int) ($request->input('amount') ?? 1);

        $user->increment('successfulCassettes', $amount);
        $user->refresh();

        return response()->json([
            'message' => 'Kazetta mentve.',
            'successfulCassettes' => $user->successfulCassettes,
            'user' => $this->prepareUserResponse($user->load('rank:id,name,available'))
        ], 200);
    }

    public function cassetteLeaderboard()
    {
        $users = User::query()
            ->select('id', 'username', 'IgName', 'profileImage', 'successfulCassettes')
            ->where('successfulCassettes', '>', 0)
            ->orderByDesc('successfulCassettes')
            ->orderBy('IgName', 'asc')
            ->get();

        return response()->json($users->map(fn (User $user) => $this->prepareUserResponse($user))->values(), 200);
    }

    private function prepareUserResponse(User $user): User
    {
        $user->profileImage = $this->normalizeImageUrl($user->profileImage);

        return $user;
    }

    private function normalizeImageUrl(?string $url): ?string
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

    private function deleteImageStorageFile(?string $url): void
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
            Log::warning('Profile image delete failed', [
                'disk' => $disk,
                'url' => $url,
                'path' => ltrim($path, '/'),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function storeImageFile(Request $request, string $field, string $directory): string
    {
        $disk = $this->imageStorageDisk();
        $path = $this->putImageFile($disk, $request, $field, $directory);

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

    private function putImageFile(string $disk, Request $request, string $field, string $directory): string|false
    {
        return Storage::disk($disk)->putFile($directory, $request->file($field));
    }
}
