<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Retention analytics over graded attempts (ImplementationPlan §4.2, PRD §2.2).
 * "Retention" = share of non-pending attempts graded `correct`. Pending free-text
 * answers are excluded everywhere so an ungraded answer never counts against you.
 */
class StatsService
{
    /**
     * Daily retention trend for the chart.
     *
     * @return list<array{date: string, total: int, correct: int, rate: float|null}>
     */
    public function retentionTrend(int $userId, ?int $projectId = null, int $days = 90): array
    {
        $rows = $this->baseQuery($userId, $projectId)
            ->where('attempted_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(attempted_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN result = 'correct' THEN 1 ELSE 0 END) as correct")
            ->groupBy(DB::raw('DATE(attempted_at)'))
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($r): array => [
            'date' => (string) $r->date,
            'total' => (int) $r->total,
            'correct' => (int) $r->correct,
            'rate' => $r->total > 0 ? round($r->correct / $r->total * 100, 1) : null,
        ])->all();
    }

    /**
     * Retention bucketed by time since practice (1 / 7 / 30 days) [PRD §2.2].
     *
     * @return array<string, float|null>
     */
    public function retentionByInterval(int $userId, ?int $projectId = null): array
    {
        $buckets = [1 => 'Letzter Tag', 7 => 'Letzte Woche', 30 => 'Letzter Monat'];
        $result = [];

        foreach ($buckets as $days => $label) {
            $base = $this->baseQuery($userId, $projectId)
                ->where('attempted_at', '>=', now()->subDays($days));

            $total = (clone $base)->count();
            $correct = (clone $base)->where('result', 'correct')->count();

            $result[$label] = $total > 0 ? round($correct / $total * 100, 1) : null;
        }

        return $result;
    }

    /**
     * Per-topic retention for the project stats page.
     *
     * @return list<array{topic_tag: string|null, total: int, correct: int, rate: float|null}>
     */
    public function retentionByTopic(int $userId, int $projectId): array
    {
        $rows = $this->baseQuery($userId, $projectId)
            ->selectRaw('topic_tag')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN result = 'correct' THEN 1 ELSE 0 END) as correct")
            ->groupBy('topic_tag')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r): array => [
            'topic_tag' => $r->topic_tag !== null ? (string) $r->topic_tag : null,
            'total' => (int) $r->total,
            'correct' => (int) $r->correct,
            'rate' => $r->total > 0 ? round($r->correct / $r->total * 100, 1) : null,
        ])->all();
    }

    /** Current overall retention percentage. */
    public function currentRetention(int $userId, ?int $projectId = null): float
    {
        $base = $this->baseQuery($userId, $projectId);

        $total = (clone $base)->count();
        $correct = (clone $base)->where('result', 'correct')->count();

        return $total > 0 ? round($correct / $total * 100, 1) : 0.0;
    }

    /** Graded (non-pending) attempts for a user, optionally scoped to a project. */
    private function baseQuery(int $userId, ?int $projectId): Builder
    {
        $query = DB::table('attempts')
            ->where('user_id', $userId)
            ->where('result', '!=', 'pending');

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return $query;
    }
}
