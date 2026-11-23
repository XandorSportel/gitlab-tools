<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BurndownCalculator
{
    /**
     * @param  array<int, string>  $completedLabels
     */
    public function calculate(array $milestone, array $issues, string $metric = 'count', array $completedLabels = []): array
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
            fn (array $issue) => CarbonImmutable::parse($issue['created_at'])->startOfDay()->lte($start)
                ? $this->valueAtDate($issue, $metric, $start)
                : 0,
            $issues
        ));

        $labels = $actual = $ideal = [];
        $days = max(1, $start->diffInDays($due));

        for ($date = $start, $offset = 0; $date->lte($chartEnd); $date = $date->addDay(), $offset++) {
            $labels[] = $date->format('M j');
            $ideal[] = round(max(0, $initial * (1 - ($offset / $days))), 2);
            $actual[] = $date->gt($today) ? null : array_sum(array_map(function (array $issue) use ($date, $metric, $completedLabels) {
                $created = CarbonImmutable::parse($issue['created_at'])->startOfDay();

                return $created->lte($date) && ! $this->isCompletedAt($issue, $date, $completedLabels)
                    ? $this->valueAtDate($issue, $metric, $date)
                    : 0;
            }, $issues));
        }

        $remaining = array_sum(array_map(
            fn (array $issue) => $this->isCompleted($issue, $completedLabels) ? 0 : $this->value($issue, $metric),
            $issues,
        ));

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
        if ($metric === 'weight') {
            return (float) ($issue['weight'] ?? 0);
        }

        if ($metric === 'story_points') {
            return $this->storyPoints($issue['labels'] ?? []);
        }

        return 1;
    }

    private function valueAtDate(array $issue, string $metric, CarbonImmutable $date): int|float
    {
        if ($metric !== 'story_points') {
            return $this->value($issue, $metric);
        }

        return $this->storyPoints($this->labelsAt($issue, $date));
    }

    /**
     * @param  array<int, string>  $completedLabels
     */
    private function isCompleted(array $issue, array $completedLabels): bool
    {
        return ($issue['state'] ?? 'opened') === 'closed'
            || $this->hasLabel($issue['labels'] ?? [], $completedLabels);
    }

    /**
     * @param  array<int, string>  $completedLabels
     */
    private function isCompletedAt(array $issue, CarbonImmutable $date, array $completedLabels): bool
    {
        $closedAt = isset($issue['closed_at']) ? CarbonImmutable::parse($issue['closed_at'])->startOfDay() : null;

        return ($closedAt && $closedAt->lte($date))
            || $this->hasLabel($this->labelsAt($issue, $date), $completedLabels);
    }

    /**
     * @return array<int, string>
     */
    private function labelsAt(array $issue, CarbonImmutable $date): array
    {
        if (! array_key_exists('label_events', $issue)) {
            return $issue['labels'] ?? [];
        }

        $labels = [];

        foreach ($issue['label_events'] as $event) {
            if (CarbonImmutable::parse($event['created_at'])->startOfDay()->gt($date)) {
                continue;
            }

            $label = $event['label']['name'] ?? null;
            if (! $label) {
                continue;
            }

            if ($event['action'] === 'add') {
                $labels[mb_strtolower($label)] = $label;
            } elseif ($event['action'] === 'remove') {
                unset($labels[mb_strtolower($label)]);
            }
        }

        return array_values($labels);
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<int, string>  $expectedLabels
     */
    private function hasLabel(array $labels, array $expectedLabels): bool
    {
        $normalizedLabels = array_map(mb_strtolower(...), $labels);

        foreach ($expectedLabels as $expectedLabel) {
            if (in_array(mb_strtolower($expectedLabel), $normalizedLabels, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function storyPoints(array $labels): float
    {
        foreach ($labels as $label) {
            if (preg_match('/^SP-(\d+(?:\.\d+)?)$/i', $label, $matches) === 1) {
                return (float) $matches[1];
            }
        }

        return 0.0;
    }

    private function date(?string $value, string $message): CarbonImmutable
    {
        if (! $value) {
            throw new InvalidArgumentException($message);
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }
}
