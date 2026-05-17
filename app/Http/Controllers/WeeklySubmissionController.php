<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WeeklySubmission;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class WeeklySubmissionController extends Controller
{
    public function index($userId)
    {
        try {
            $items = WeeklySubmission::where('user_id', $userId)
                ->where(function ($query) {
                    $query
                        ->where('accepted', false)
                        ->orWhere('created_at', '>=', Carbon::now()->subDays(30));
                })
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (QueryException $exception) {
            return $this->weeklySubmissionTableError();
        }

        return response()->json($items, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'imageLink' => 'required|url',
            'amount' => 'required|integer|min:1'
        ]);

        $item = WeeklySubmission::create([
            'user_id' => $request->user_id,
            'imageLink' => $request->imageLink,
            'amount' => $request->amount,
            'accepted' => false,
            'created_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s')
        ]);

        return response()->json([
            'message' => 'Heti leadandó elmentve',
            'item' => $item
        ], 201);
    }

    public function destroy($id)
    {
        $item = WeeklySubmission::find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Elem nem található'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'message' => 'Elem törölve'
        ], 200);
    }

    public function accept($id)
    {
        $item = WeeklySubmission::find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Leadandó nem található'
            ], 404);
        }

        if ($item->accepted) {
            return response()->json([
                'message' => 'Ez a leadandó már el lett fogadva'
            ], 400);
        }

        $user = User::find($item->user_id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található'
            ], 404);
        }

        $weeksToAdd = intdiv($item->amount, 200000);

        $currentWeeklyPay = $user->weeklyPay
            ? Carbon::parse($user->weeklyPay)
            : Carbon::now();

        if ($weeksToAdd > 0) {
            $currentWeeklyPay->addWeeks($weeksToAdd);
            $user->weeklyPay = $currentWeeklyPay->toDateString();
        }

        $user->save();

        $item->accepted = true;
        $item->save();

        return response()->json([
            'message' => 'Leadandó elfogadva',
            'weeks_added' => $weeksToAdd,
            'weeklyPay' => $user->weeklyPay,
            'item' => $item
        ], 200);
    }

    public function pending()
{
    try {
        $items = WeeklySubmission::with('user:id,username,IgName,profileImage')
            ->where('accepted', false)
            ->orderBy('created_at', 'desc')
            ->get();

        $items->each(function (WeeklySubmission $item) {
            if ($item->user) {
                $item->user->profileImage = $this->normalizeImageUrl($item->user->profileImage);
            }
        });
    } catch (QueryException $exception) {
        return $this->weeklySubmissionTableError();
    }

    return response()->json($items, 200);
}

private function normalizeImageUrl(?string $url): ?string
{
    if (!$url) {
        return null;
    }

    $url = trim($url);
    $disk = config('filesystems.image_disk', 'public');
    $diskUrl = config("filesystems.disks.{$disk}.url");

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

private function weeklySubmissionTableError()
{
    return response()->json([
        'message' => 'A heti leadandĂł adatbĂˇzis tĂˇbla hiĂˇnyzik vagy nincs frissĂ­tve. Futtasd a backend migrĂˇciĂłkat: php artisan migrate --force'
    ], 500);
}
}
