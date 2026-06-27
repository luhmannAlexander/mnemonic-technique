<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Daily practice streak (ImplementationPlan §4.1, BackendSchema §7). A streak is
 * a run of consecutive calendar days with at least one finished session.
 *
 * "Keep alive today": a run that ends *yesterday* still counts as the current
 * streak until today is over — practising today keeps it, a missed day breaks it.
 * Results are cached for an hour and invalidated when a session finishes.
 */
class StreakService
{
    private const TTL = 3600;

    public function current(int $userId): int
    {
        return Cache::remember("streak:{$userId}:current", self::TTL, fn (): int => $this->compute($userId)['current']);
    }

    public function longest(int $userId): int
    {
        return Cache::remember("streak:{$userId}:longest", self::TTL, fn (): int => $this->compute($userId)['longest']);
    }

    public function invalidate(int $userId): void
    {
        Cache::forget("streak:{$userId}:current");
        Cache::forget("streak:{$userId}:longest");
    }

    /**
     * @return array{current: int, longest: int}
     */
    private function compute(int $userId): array
    {
        /** @var Collection<int, CarbonImmutable> $days */
        $days = DB::table('session_logs')
            ->where('user_id', $userId)
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->pluck('finished_at')
            ->map(fn ($d): CarbonImmutable => CarbonImmutable::parse($d)->startOfDay())
            ->unique(fn (CarbonImmutable $d): string => $d->toDateString())
            ->sortDesc()
            ->values();

        if ($days->isEmpty()) {
            return ['current' => 0, 'longest' => 0];
        }

        $today = CarbonImmutable::now()->startOfDay();
        $mostRecent = $days->first();

        // Current streak is alive only if the latest practice was today or yesterday.
        $current = 0;
        if ($mostRecent->diffInDays($today) <= 1) {
            $current = 1;
            for ($i = 1; $i < $days->count(); $i++) {
                if ($days[$i]->eq($days[$i - 1]->subDay())) {
                    $current++;
                } else {
                    break;
                }
            }
        }

        // Longest run anywhere in the history.
        $longest = 1;
        $run = 1;
        for ($i = 1; $i < $days->count(); $i++) {
            if ($days[$i]->eq($days[$i - 1]->subDay())) {
                $run++;
            } else {
                $run = 1;
            }
            $longest = max($longest, $run);
        }

        return ['current' => $current, 'longest' => max($longest, $current)];
    }
}
