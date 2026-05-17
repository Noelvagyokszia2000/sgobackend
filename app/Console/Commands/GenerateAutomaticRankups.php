<?php

namespace App\Console\Commands;

use App\Models\Rankup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateAutomaticRankups extends Command
{
    protected $signature = 'rankups:generate-automatic';

    protected $description = 'Automatikus rangléptetés a táblázat szerinti idő alapján';

    private const AUTO_PROMOTION_WEEKS_BY_TARGET_RANK = [
        3 => 1,
        4 => 2,
        5 => 3,
        6 => 4,
        7 => 5,
        8 => 6,
        9 => 7,
        10 => 8,
    ];

    public function handle(): int
    {
        $now = Carbon::now();
        $promotedUsers = 0;

        User::query()
            ->whereNotNull('rank_id')
            ->where('rank_id', '<', 10)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($now, &$promotedUsers) {
                foreach ($users as $user) {
                    $nextRankId = (int) $user->rank_id + 1;
                    $requiredWeeks = self::AUTO_PROMOTION_WEEKS_BY_TARGET_RANK[$nextRankId] ?? null;

                    if (!$requiredWeeks) {
                        continue;
                    }

                    $baseDate = $user->lastRankup
                        ? Carbon::parse($user->lastRankup)
                        : ($user->createdAt ? Carbon::parse($user->createdAt) : null);

                    if (!$baseDate || $baseDate->copy()->addWeeks($requiredWeeks)->gt($now)) {
                        continue;
                    }

                    $previousRankId = (int) $user->rank_id;

                    $user->rank_id = $nextRankId;
                    $user->lastRankup = $now;
                    $user->save();

                    Rankup::create([
                        'user_id' => $user->id,
                        'issued_by' => null,
                        'previous_rank_id' => $previousRankId,
                        'next_rank_id' => $nextRankId,
                        'issued_at_site' => $now,
                        'issued_at_game' => $now,
                        'completed' => true
                    ]);

                    $promotedUsers++;
                }
            });

        $this->info("Automatikus rangléptetés lefutott. Léptetett felhasználók: {$promotedUsers}");

        return self::SUCCESS;
    }
}
