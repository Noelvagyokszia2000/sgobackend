<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ImageStorageService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class WeeklySubmissionController extends Controller
{
    public function __construct(private ImageStorageService $images)
    {
    }

    public function index($userId)
    {
        try {
            $cutoffDate = Carbon::now(config('app.timezone'))
                ->subDays(30)
                ->format('Y-m-d H:i:s');

            $items = DB::table('weekly_submissions')
                ->select('id', 'user_id', 'imageLink', 'amount', 'accepted', 'created_at')
                ->where('user_id', (int) $userId)
                ->where(function ($query) use ($cutoffDate) {
                    $query
                        ->where('accepted', 0)
                        ->orWhere('created_at', '>=', $cutoffDate);
                })
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (QueryException $exception) {
            Log::error('Weekly submissions user list query failed', [
                'user_id' => $userId,
                'sql' => $exception->getSql(),
                'bindings' => $exception->getBindings(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A heti leadando adatbazis lekerdezese hibara futott.',
                'error' => $exception->getMessage(),
            ], 500);
        } catch (TooManyRequestsHttpException $exception) {
            return response()->json([
                'message' => 'Tul sok keres erkezett rovid idon belul. Varj egy kicsit, majd probald ujra.',
            ], 429);
        } catch (\Throwable $exception) {
            Log::error('Weekly submissions user list failed', [
                'user_id' => $userId,
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A leadott elemek betoltese kozben backend hiba tortent.',
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json($items, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'imageLink' => 'required|url',
            'amount' => 'required|integer|min:1',
        ]);

        try {
            $createdAt = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');

            $id = DB::table('weekly_submissions')->insertGetId([
                'user_id' => (int) $validated['user_id'],
                'imageLink' => $validated['imageLink'],
                'amount' => (int) $validated['amount'],
                'accepted' => 0,
                'created_at' => $createdAt,
            ]);

            $item = DB::table('weekly_submissions')
                ->select('id', 'user_id', 'imageLink', 'amount', 'accepted', 'created_at')
                ->where('id', $id)
                ->first();
        } catch (QueryException $exception) {
            Log::error('Weekly submission store query failed', [
                'user_id' => $validated['user_id'] ?? null,
                'sql' => $exception->getSql(),
                'bindings' => $exception->getBindings(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A heti leadando mentese adatbazis hibara futott.',
                'error' => $exception->getMessage(),
            ], 500);
        } catch (\Throwable $exception) {
            Log::error('Weekly submission store failed', [
                'user_id' => $validated['user_id'] ?? null,
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A heti leadando mentese kozben backend hiba tortent.',
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Heti leadando elmentve',
            'item' => $item,
        ], 201);
    }

    public function accept($id)
    {
        try {
            $item = DB::table('weekly_submissions')
                ->select('id', 'user_id', 'imageLink', 'amount', 'accepted', 'created_at')
                ->where('id', (int) $id)
                ->first();

            if (!$item) {
                return response()->json([
                    'message' => 'Leadando nem talalhato',
                ], 404);
            }

            if ((bool) $item->accepted) {
                return response()->json([
                    'message' => 'Ez a leadando mar el lett fogadva',
                ], 400);
            }

            $user = User::find($item->user_id);

            if (!$user) {
                return response()->json([
                    'message' => 'Felhasznalo nem talalhato',
                ], 404);
            }

            $weeksToAdd = intdiv((int) $item->amount, 200000);

            $currentWeeklyPay = $user->weeklyPay
                ? Carbon::parse($user->weeklyPay)
                : Carbon::now(config('app.timezone'));

            if ($weeksToAdd > 0) {
                $currentWeeklyPay->addWeeks($weeksToAdd);
                $user->weeklyPay = $currentWeeklyPay->toDateString();
            }

            $user->save();

            DB::table('weekly_submissions')
                ->where('id', (int) $id)
                ->update(['accepted' => 1]);

            $item = DB::table('weekly_submissions')
                ->select('id', 'user_id', 'imageLink', 'amount', 'accepted', 'created_at')
                ->where('id', (int) $id)
                ->first();
        } catch (QueryException $exception) {
            Log::error('Weekly submission accept query failed', [
                'id' => $id,
                'sql' => $exception->getSql(),
                'bindings' => $exception->getBindings(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A heti leadando elfogadasa adatbazis hibara futott.',
                'error' => $exception->getMessage(),
            ], 500);
        } catch (\Throwable $exception) {
            Log::error('Weekly submission accept failed', [
                'id' => $id,
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A heti leadando elfogadasa kozben backend hiba tortent.',
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Leadando elfogadva',
            'weeks_added' => $weeksToAdd,
            'weeklyPay' => $user->weeklyPay,
            'item' => $item,
        ], 200);
    }

    public function pending()
    {
        try {
            $hasProfileImage = Schema::hasColumn('users', 'profileImage');
            $userProfileSelect = $hasProfileImage
                ? 'users.profileImage as user_profileImage'
                : DB::raw('null as user_profileImage');

            $items = DB::table('weekly_submissions')
                ->leftJoin('users', 'weekly_submissions.user_id', '=', 'users.id')
                ->select(
                    'weekly_submissions.id',
                    'weekly_submissions.user_id',
                    'weekly_submissions.imageLink',
                    'weekly_submissions.amount',
                    'weekly_submissions.accepted',
                    'weekly_submissions.created_at',
                    'users.username as user_username',
                    'users.IgName as user_IgName',
                    $userProfileSelect
                )
                ->where('weekly_submissions.accepted', 0)
                ->orderBy('weekly_submissions.created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'user_id' => $item->user_id,
                        'imageLink' => $item->imageLink,
                        'amount' => $item->amount,
                        'accepted' => (bool) $item->accepted,
                        'created_at' => $item->created_at,
                        'user' => $item->user_username || $item->user_IgName
                            ? [
                                'username' => $item->user_username,
                                'IgName' => $item->user_IgName,
                                'profileImage' => $this->normalizeImageUrl($item->user_profileImage),
                            ]
                            : null,
                    ];
                });
        } catch (QueryException $exception) {
            Log::error('Weekly submissions pending query failed', [
                'sql' => $exception->getSql(),
                'bindings' => $exception->getBindings(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A fuggoben levo leadandok adatbazis lekerdezese hibara futott.',
                'error' => $exception->getMessage(),
            ], 500);
        } catch (\Throwable $exception) {
            Log::error('Weekly submissions pending failed', [
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A fuggoben levo leadandok betoltese kozben backend hiba tortent.',
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json($items, 200);
    }

    private function normalizeImageUrl(?string $url): ?string
    {
        return $this->images->normalizeUrl($url);
    }
}
