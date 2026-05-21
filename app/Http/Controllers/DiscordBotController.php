<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\Robbery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscordBotController extends Controller
{
    public function pendingRobberies(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $robberies = Robbery::query()
            ->with('author:id,username,IgName,profileImage')
            ->whereNull('discord_announced_at')
            ->where('finished', false)
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (Robbery $robbery) => [
                'id' => $robbery->id,
                'name' => $robbery->name,
                'type' => $robbery->type,
                'author_name' => $robbery->author?->IgName
                    ?: $robbery->author?->username
                    ?: 'Ismeretlen',
                'participants_count' => (int) $robbery->participants_count,
                'applicants_count' => (int) $robbery->applicants_count,
                'activity_only' => $this->isActivityOnlyRobbery($robbery),
            ])
            ->values();

        return response()->json([
            'robberies' => $robberies,
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

    public function pendingNews(Request $request): JsonResponse
    {
        if ($response = $this->authorizeBot($request)) {
            return $response;
        }

        $news = News::query()
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

        return response()->json([
            'news' => $news,
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
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'IgName' => $user->IgName,
            ],
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
        return strtoupper((string) $robbery->type) === 'OTHER';
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
