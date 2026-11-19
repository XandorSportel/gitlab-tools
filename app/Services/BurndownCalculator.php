<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BurndownCalculator
{
    public function calculate(array $milestone, array $issues, string $metric = 'count'): array
    {
        $start = $this->date($milestone['start_date'] ?? null, 'The milestone needs a start date.');
        $due = $this->date($milestone['due_date'] ?? null, 'The milestone needs a due date.');
        if ($due->lt($start)) {
            throw new InvalidArgumentException('The milestone due date is before its start date.');
        }

        $today = CarbonImmutable::today();
        $chartEnd = $due->lt($today) ? $today : $due;
        $totalAtEnd = array_sum(array_map(fn (array $issue) => $this->value($issue, $metric), $issues));
        $initial = array_sum(array_map(
            fn (array $issue) => CarbonImmutable::parse($issue['created_at'])->startOfDay()->lte($start) ? $this->value($issue, $metric) : 0,
            $issues
        ));

        $labels = $actual = $ideal = [];
        $days = max(1, $start->diffInDays($due));

        for ($date = $start, $offset = 0; $date->lte($chartEnd); $date = $date->addDay(), $offset++) {
            $labels[] = $date->format('M j');
            $ideal[] = round(max(0, $initial * (1 - ($offset / $days))), 2);
            $actual[] = $date->gt($today) ? null : array_sum(array_map(function (array $issue) use ($date, $metric) {
                $created = CarbonImmutable::parse($issue['created_at'])->startOfDay();
                $closed = isset($issue['closed_at']) ? CarbonImmutable::parse($issue['closed_at'])->startOfDay() : null;
                return $created->lte($date) && (! $closed || $closed->gt($date)) ? $this->value($issue, $metric) : 0;
            }, $issues));
        }

        $remaining = array_sum(array_map(fn (array $issue) => ($issue['state'] ?? 'opened') === 'closed' ? 0 : $this->value($issue, $metric), $issues));

        return compact('labels', 'actual', 'ideal') + [
            'initial' => $initial,
            'total' => $totalAtEnd,
            'remaining' => $remaining,
            'completed' => $totalAtEnd - $remaining,
            'scope_added' => max(0, $totalAtEnd - $initial),
            'metric' => $metric,
        ];
    }

    private function value(array $issue, string $metric): int|float
    {
        return $metric === 'weight' ? (float) ($issue['weight'] ?? 0) : 1;
    }

    private function date(?string $value, string $message): CarbonImmutable
    {
        if (! $value) {
            throw new InvalidArgumentException($message);
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }
}
