<?php

namespace App\Http\Controllers;

use App\Models\InactivityRequest;
use App\Models\News;
use App\Models\Robbery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscordBotController extends Controller
{
    public function poll(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        return response()->json([
            'robberies' => $this->pendingRobberyAnnouncements(),
            'robbery_message_updates' => $this->activeRobberyMessageUpdates(),
            'news' => $this->pendingNewsAnnouncements(),
            'weekly_payment_reminders' => $this->pendingWeeklyReminderUsers(),
            'inactivity_reminders' => $this->pendingInactivityEndingReminders(),
            'discord_reaction_removals' => $this->pendingDiscordReactionRemovalJobs(),
        ]);
    }

    public function pendingRobberies(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        return response()->json([
            'robberies' => $this->pendingRobberyAnnouncements(),
        ]);
    }

    public function recordDiscordMessage(Request $request, $id): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $validated = $request->validate([
            'discord_channel_id' => 'required|string|max:32',
            'discord_message_id' => 'required|string|max:32',
        ]);

        $robbery = Robbery::find($id);

        if (!$robbery) {
            return response()->json([
                'message' => 'Rablás nem található.',
            ], 404);
        }

        $robbery->discord_channel_id = $validated['discord_channel_id'];
        $robbery->discord_message_id = $validated['discord_message_id'];
        $robbery->discord_announced_at = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $robbery->save();

        return response()->json([
            'message' => 'Discord üzenet rögzítve.',
        ]);
    }

    public function createRobbery(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $validated = $request->validate([
            'discord_user_id' => 'required|string|max:32',
            'name' => 'required|string|max:120',
            'type' => 'required|string|in:ATM,BANK,OTHER',
            'with_allies' => 'nullable|boolean',
        ]);

        $user = User::query()
            ->where('discord_id', $validated['discord_user_id'])
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Nincs webes felhasználó összekötve ezzel a Discord fiókkal. Használd előbb a /link parancsot.',
            ], 404);
        }

        $robbery = Robbery::create([
            'created_by' => $user->id,
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'with_allies' => (bool) ($validated['with_allies'] ?? false),
            'participants_count' => 0,
            'applicants_count' => 0,
            'finished' => false,
            'activity_excluded' => false,
        ]);

        return response()->json([
            'message' => 'Rablás sikeresen létrehozva.',
            'robbery' => [
                'id' => $robbery->id,
                'name' => $robbery->name,
                'type' => $robbery->type,
                'with_allies' => (bool) $robbery->with_allies,
                'author_name' => $user->IgName ?: $user->username,
                'participants_count' => 0,
                'applicants_count' => 0,
                'activity_only' => $this->isActivityOnlyRobbery($robbery),
            ],
        ], 201);
    }

    public function linkedUser(Request $request, string $discordId): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $user = User::query()
            ->where('discord_id', $discordId)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Nincs webes felhasználó összekötve ezzel a Discord fiókkal. Használd előbb a /link parancsot.',
            ], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'IgName' => $user->IgName,
            ],
        ]);
    }

    public function pendingNews(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        return response()->json([
            'news' => $this->pendingNewsAnnouncements(),
        ]);
    }

    public function recordNewsDiscordMessage(Request $request, $id): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $validated = $request->validate([
            'discord_channel_id' => 'required|string|max:32',
            'discord_message_id' => 'required|string|max:32',
        ]);

        $news = News::query()
            ->whereNull('deleted_at')
            ->find($id);

        if (!$news) {
            return response()->json([
                'message' => 'Hír nem található.',
            ], 404);
        }

        $news->discord_channel_id = $validated['discord_channel_id'];
        $news->discord_message_id = $validated['discord_message_id'];
        $news->discord_announced_at = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $news->save();

        return response()->json([
            'message' => 'Discord hír üzenet rögzítve.',
        ]);
    }

    public function pendingWeeklyPaymentReminders(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        return response()->json([
            'reminders' => $this->pendingWeeklyReminderUsers(),
        ]);
    }

    public function markWeeklyPaymentReminderSent(Request $request, $id): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $user = User::query()
            ->whereNotNull('weeklyPay')
            ->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Felhasználó nem található.',
            ], 404);
        }

        $user->weeklyPayReminderSentFor = $user->weeklyPay;
        $user->save();

        return response()->json([
            'message' => 'Leadandó emlékeztető rögzítve.',
        ]);
    }

    public function pendingInactivityReminders(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        return response()->json([
            'reminders' => $this->pendingInactivityEndingReminders(),
        ]);
    }

    public function markInactivityReminderSent(Request $request, $id): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $item = InactivityRequest::query()
            ->where('status', 'approved')
            ->find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Inaktivitas nem talalhato.',
            ], 404);
        }

        $item->endingReminderSentFor = $item->end_date;
        $item->save();

        return response()->json([
            'message' => 'Inaktivitas emlekezteto rogzitve.',
        ]);
    }

    public function handleReaction(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $validated = $request->validate([
            'discord_user_id' => 'required|string|max:32',
            'discord_channel_id' => 'required|string|max:32',
            'discord_message_id' => 'required|string|max:32',
            'action' => 'required|string|in:join,payout',
        ]);

        $robbery = Robbery::query()
            ->where('discord_message_id', $validated['discord_message_id'])
            ->where('discord_channel_id', $validated['discord_channel_id'])
            ->first();

        if (!$robbery) {
            return response()->json([
                'message' => 'Discord üzenethez nem található rablás.',
            ], 404);
        }

        if ($robbery->finished) {
            return response()->json([
                'message' => 'Ez a rablás már le van zárva.',
            ], 422);
        }

        if ($validated['action'] === 'payout' && $this->isActivityOnlyRobbery($robbery)) {
            return response()->json([
                'message' => 'Ehhez a rablás típushoz nincs pénzosztás.',
            ], 422);
        }

        $user = User::query()
            ->where('discord_id', $validated['discord_user_id'])
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Nincs webes felhasználó összekötve ezzel a Discord fiókkal.',
            ], 404);
        }

        DB::transaction(function () use ($robbery, $user, $validated): void {
            $now = Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');

            $participantExists = DB::table('robbery_participants')
                ->where('robbery_id', $robbery->id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$participantExists) {
                DB::table('robbery_participants')->insert([
                    'robbery_id' => $robbery->id,
                    'user_id' => $user->id,
                    'created_at' => $now,
                ]);
            }

            if ($validated['action'] === 'payout') {
                $payoutExists = DB::table('robbery_payout_requests')
                    ->where('robbery_id', $robbery->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if (!$payoutExists) {
                    DB::table('robbery_payout_requests')->insert([
                        'robbery_id' => $robbery->id,
                        'user_id' => $user->id,
                        'created_at' => $now,
                    ]);
                }
            }

            $this->syncCounts($robbery);
        });

        return response()->json([
            'message' => $validated['action'] === 'payout'
                ? 'Pénzosztásra jelentkezés rögzítve.'
                : 'Rablásra jelentkezés rögzítve.',
            'robbery' => $this->formatDiscordRobbery($this->loadDiscordRobbery($robbery->id)),
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'IgName' => $user->IgName,
            ],
        ]);
    }

    public function removeReaction(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $validated = $request->validate([
            'discord_user_id' => 'required|string|max:32',
            'discord_channel_id' => 'required|string|max:32',
            'discord_message_id' => 'required|string|max:32',
            'action' => 'required|string|in:join,payout',
        ]);

        $robbery = Robbery::query()
            ->where('discord_message_id', $validated['discord_message_id'])
            ->where('discord_channel_id', $validated['discord_channel_id'])
            ->first();

        if (!$robbery) {
            return response()->json([
                'message' => 'Discord ĂĽzenethez nem talĂˇlhatĂł rablĂˇs.',
            ], 404);
        }

        if ($robbery->finished) {
            return response()->json([
                'message' => 'A rablas mar le van zarva, az elozmeny nem modosult.',
            ]);
        }

        $user = User::query()
            ->where('discord_id', $validated['discord_user_id'])
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Nincs webes felhasznĂˇlĂł Ă¶sszekĂ¶tve ezzel a Discord fiĂłkkal.',
            ], 404);
        }

        DB::transaction(function () use ($robbery, $user, $validated): void {
            if ($validated['action'] === 'payout') {
                DB::table('robbery_payout_requests')
                    ->where('robbery_id', $robbery->id)
                    ->where('user_id', $user->id)
                    ->delete();
            }

            if ($validated['action'] === 'join') {
                DB::table('robbery_payout_requests')
                    ->where('robbery_id', $robbery->id)
                    ->where('user_id', $user->id)
                    ->delete();

                DB::table('robbery_participants')
                    ->where('robbery_id', $robbery->id)
                    ->where('user_id', $user->id)
                    ->delete();
            }

            $this->syncCounts($robbery);
        });

        return response()->json([
            'message' => $validated['action'] === 'payout'
                ? 'PĂ©nzosztĂˇsra jelentkezĂ©s tĂ¶rĂ¶lve.'
                : 'RablĂˇsra jelentkezĂ©s tĂ¶rĂ¶lve.',
            'robbery' => $this->formatDiscordRobbery($this->loadDiscordRobbery($robbery->id)),
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'IgName' => $user->IgName,
            ],
        ]);
    }

    public function pendingDiscordReactionRemovals(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        return response()->json([
            'jobs' => $this->pendingDiscordReactionRemovalJobs(),
        ]);
    }

    public function markDiscordReactionRemovalProcessed(Request $request, $id): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $validated = $request->validate([
            'last_error' => 'nullable|string|max:1000',
        ]);

        $updated = DB::table('discord_reaction_removal_jobs')
            ->where('id', $id)
            ->whereNull('processed_at')
            ->update([
                'processed_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
                'last_error' => $validated['last_error'] ?? null,
                'updated_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
            ]);

        if (!$updated) {
            return response()->json([
                'message' => 'Discord reaction eltavolitasi feladat nem talalhato.',
            ], 404);
        }

        return response()->json([
            'message' => 'Discord reaction eltavolitasi feladat feldolgozva.',
        ]);
    }

    public function unlinkUser(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $validated = $request->validate([
            'discord_user_id' => 'required|string|max:32',
        ]);

        $user = User::query()
            ->where('discord_id', $validated['discord_user_id'])
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Ehhez a Discord fiókhoz nincs webes felhasználó kötve.',
            ], 404);
        }

        $userData = [
            'id' => $user->id,
            'username' => $user->username,
            'IgName' => $user->IgName,
        ];

        $user->discord_id = null;
        $user->save();

        return response()->json([
            'message' => 'Discord fiók leválasztva.',
            'user' => $userData,
        ]);
    }

    public function linkUser(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $validated = $request->validate([
            'discord_user_id' => 'required|string|max:32',
            'username' => 'required|string|max:255',
        ]);

        $user = User::query()
            ->where('username', $validated['username'])
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Nem található ilyen webes felhasználónév.',
            ], 404);
        }

        $linkedUser = User::query()
            ->where('discord_id', $validated['discord_user_id'])
            ->where('id', '!=', $user->id)
            ->first();

        if ($linkedUser) {
            return response()->json([
                'message' => 'Ez a Discord fiók már másik webes felhasználóhoz van kötve.',
            ], 409);
        }

        if ($user->discord_id && $user->discord_id !== $validated['discord_user_id']) {
            return response()->json([
                'message' => 'Ehhez a webes felhasználóhoz már másik Discord fiók van kötve.',
            ], 409);
        }

        $user->discord_id = $validated['discord_user_id'];
        $user->save();

        return response()->json([
            'message' => 'Discord fiók összekötve.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'IgName' => $user->IgName,
            ],
        ]);
    }

    private function pendingRobberyAnnouncements()
    {
        return Robbery::query()
            ->with('author:id,username,IgName,profileImage')
            ->whereNull('discord_announced_at')
            ->where('finished', false)
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (Robbery $robbery) => $this->formatDiscordRobbery($robbery))
            ->values();
    }

    private function activeRobberyMessageUpdates()
    {
        return Robbery::query()
            ->with('author:id,username,IgName,profileImage')
            ->where('finished', false)
            ->whereNotNull('discord_channel_id')
            ->whereNotNull('discord_message_id')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (Robbery $robbery) => $this->formatDiscordRobbery($robbery))
            ->values();
    }

    private function loadDiscordRobbery(int $id): Robbery
    {
        return Robbery::query()
            ->with('author:id,username,IgName,profileImage')
            ->findOrFail($id);
    }

    private function formatDiscordRobbery(Robbery $robbery): array
    {
        $activityOnly = $this->isActivityOnlyRobbery($robbery);
        $payoutApplicants = $activityOnly
            ? collect()
            : $this->getDiscordApplicationUsers('robbery_payout_requests', $robbery->id);

        return [
            'id' => $robbery->id,
            'name' => $robbery->name,
            'type' => $robbery->type,
            'with_allies' => (bool) $robbery->with_allies,
            'discord_channel_id' => $robbery->discord_channel_id,
            'discord_message_id' => $robbery->discord_message_id,
            'author_name' => $robbery->author?->IgName
                ?: $robbery->author?->username
                ?: 'Ismeretlen',
            'participants_count' => (int) $robbery->participants_count,
            'applicants_count' => $activityOnly ? 0 : (int) $robbery->applicants_count,
            'activity_only' => $activityOnly,
            'participants' => $this->getDiscordApplicationUsers('robbery_participants', $robbery->id),
            'payout_applicants' => $payoutApplicants,
        ];
    }

    private function getDiscordApplicationUsers(string $table, int $robberyId)
    {
        return DB::table($table)
            ->join('users', "{$table}.user_id", '=', 'users.id')
            ->where("{$table}.robbery_id", $robberyId)
            ->orderBy("{$table}.created_at")
            ->get([
                'users.id',
                'users.username',
                'users.IgName',
                'users.discord_id',
                "{$table}.created_at as joined_at",
            ]);
    }

    private function pendingNewsAnnouncements()
    {
        return News::query()
            ->with('author:id,username,IgName,profileImage')
            ->whereNull('deleted_at')
            ->whereNull('discord_announced_at')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (News $item) => [
                'id' => $item->id,
                'text' => $item->text,
                'image' => $item->image,
                'published_at' => (string) $item->published_at,
                'author_name' => $item->author?->IgName
                    ?: $item->author?->username
                    ?: 'Ismeretlen',
            ])
            ->values();
    }

    private function pendingWeeklyReminderUsers()
    {
        $targetDate = Carbon::now(config('app.timezone'))->addDay()->toDateString();

        return User::query()
            ->select('id', 'username', 'IgName', 'discord_id', 'weeklyPay')
            ->whereNotNull('discord_id')
            ->where('discord_id', '!=', '')
            ->where('weeklyPaymentRequired', true)
            ->whereDate('weeklyPay', $targetDate)
            ->where(function ($query) {
                $query
                    ->whereNull('weeklyPayReminderSentFor')
                    ->orWhereColumn('weeklyPayReminderSentFor', '!=', 'weeklyPay');
            })
            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))
                    ->from('weekly_submissions')
                    ->whereColumn('weekly_submissions.user_id', 'users.id')
                    ->where('weekly_submissions.accepted', false);
            })
            ->orderBy('IgName')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'IgName' => $user->IgName,
                'discord_id' => $user->discord_id,
                'weeklyPay' => optional($user->weeklyPay)->toDateString(),
            ])
            ->values();
    }

    private function pendingInactivityEndingReminders()
    {
        $targetDate = Carbon::now(config('app.timezone'))->addDay()->toDateString();

        return InactivityRequest::query()
            ->with('user:id,username,IgName,discord_id')
            ->where('status', 'approved')
            ->whereDate('end_date', $targetDate)
            ->where(function ($query) {
                $query
                    ->whereNull('endingReminderSentFor')
                    ->orWhereColumn('endingReminderSentFor', '!=', 'end_date');
            })
            ->whereHas('user', function ($query) {
                $query
                    ->whereNotNull('discord_id')
                    ->where('discord_id', '!=', '');
            })
            ->orderBy('end_date')
            ->get()
            ->map(fn (InactivityRequest $item) => [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'username' => $item->user?->username,
                'IgName' => $item->user?->IgName,
                'discord_id' => $item->user?->discord_id,
                'start_date' => optional($item->start_date)->toDateString(),
                'end_date' => optional($item->end_date)->toDateString(),
                'reason' => $item->reason,
            ])
            ->values();
    }

    private function pendingDiscordReactionRemovalJobs()
    {
        return DB::table('discord_reaction_removal_jobs')
            ->whereNull('processed_at')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'robbery_id' => $job->robbery_id,
                'user_id' => $job->user_id,
                'discord_user_id' => $job->discord_user_id,
                'discord_channel_id' => $job->discord_channel_id,
                'discord_message_id' => $job->discord_message_id,
                'action' => $job->action,
            ])
            ->values();
    }

    private function syncCounts(Robbery $robbery): void
    {
        $robbery->participants_count = DB::table('robbery_participants')
            ->where('robbery_id', $robbery->id)
            ->count();

        $robbery->applicants_count = DB::table('robbery_payout_requests')
            ->where('robbery_id', $robbery->id)
            ->count();

        $robbery->save();
    }

    private function isActivityOnlyRobbery(Robbery $robbery): bool
    {
        return strtoupper((string) $robbery->type) === 'OTHER' || (bool) $robbery->with_allies;
    }

    private function authorizeBot(Request $request): ?JsonResponse
    {
        $expectedToken = (string) config('services.discord_bot.api_token');

        if (!$expectedToken) {
            return response()->json([
                'message' => 'A Discord bot API token nincs beállítva.',
            ], 503);
        }

        $providedToken = (string) (
            $request->bearerToken()
            ?: $request->header('X-Bot-Token')
        );

        if (!$providedToken || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'message' => 'Érvénytelen bot token.',
            ], 401);
        }

        return null;
    }
}
