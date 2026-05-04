<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Rankup;
use Carbon\Carbon;

class GenerateAutomaticRankups extends Command
{
    protected $signature = 'rankups:generate-automatic';
    protected $description = 'Automatikus rangupok generálása a felhasználók számára';

    public function handle(): int
    {
        $now = Carbon::now();

        $users = User::with('rank')->get();

        foreach ($users as $user) {
            if (!$user->rank_id) {
                continue;
            }

            $baseDate = null;

            if ($user->lastRankup) {
                $lastRankup = Carbon::parse($user->lastRankup);
                $createdAt = $user->createdAt ? Carbon::parse($user->createdAt) : null;

                if ($createdAt && $lastRankup->equalTo($createdAt)) {
                    $baseDate = $createdAt;
                } else {
                    $baseDate = $lastRankup;
                }
            } else {
                $baseDate = $user->createdAt ? Carbon::parse($user->createdAt) : null;
            }

            if (!$baseDate) {
                continue;
            }

            $dueDate = (clone $baseDate)->addWeeks(2);

            if ($dueDate->gt($now)) {
                continue;
            }

            $nextRankId = $user->rank_id + 1;

            $alreadyExists = Rankup::where('user_id', $user->id)
                ->whereNull('issued_by')
                ->where('previous_rank_id', $user->rank_id)
                ->where('next_rank_id', $nextRankId)
                ->whereDate('issued_at_site', $dueDate->toDateString())
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            Rankup::create([
                'user_id' => $user->id,
                'issued_by' => null,
                'previous_rank_id' => $user->rank_id,
                'next_rank_id' => $nextRankId,
                'issued_at_site' => $dueDate,
                'issued_at_game' => null,
                'completed' => false
            ]);
        }

        $this->info('Automatikus rankup generálás lefutott.');

        return self::SUCCESS;
    }
}