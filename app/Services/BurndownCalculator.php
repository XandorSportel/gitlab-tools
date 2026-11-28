<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BurndownCalculator
{
    private const string STORY_POINT_LABEL = '/^SP-(\d+(?:\.\d+)?)$/i';

    /** @return array{planned: float, completed: float, remaining: float} */
    public function retrospective(array $milestone, array $issues, array $completedLabels): array
    {
        $start = $this->date($milestone['start_date'] ?? null, 'The milestone needs a start date.');
        $due = $this->date($milestone['due_date'] ?? null, 'The milestone needs a due date.');
        $planned = $completed = $remaining = 0.0;

        foreach ($issues as $issue) {
            $created = CarbonImmutable::parse($issue['created_at'])->startOfDay();
            if ($created->gt($due)) {
                continue;
            }
            if ($created->lte($start)) {
                $planned += $this->valueAtDate($issue, 'story_points', $start);
            }

            $points = $this->valueAtDate($issue, 'story_points', $due);
            if ($this->isCompletedAt($issue, $due, $completedLabels)) {
                $completed += $points;
            } else {
                $remaining += $points;
            }
        }

        return compact('planned', 'completed', 'remaining');
    }

    /** @return array{missing: list<array>, conflicting: list<array>, openCompleted: list<array>} */
    public function healthCheck(array $issues, array $completedLabels): array
    {
        $missing = $conflicting = $openCompleted = [];

        foreach ($issues as $issue) {
            $labels = $issue['labels'] ?? [];
            $pointLabels = preg_grep(self::STORY_POINT_LABEL, $labels);

            if ($pointLabels === []) {
                $missing[] = $issue;
            } elseif (count($pointLabels) > 1) {
                $conflicting[] = $issue;
            }

            if (($issue['state'] ?? 'opened') !== 'closed' && $this->hasLabel($labels, $completedLabels)) {
                $openCompleted[] = $issue;
            }
        }

        return compact('missing', 'conflicting', 'openCompleted');
    }

    /**
     * @param  array<int, string>  $completedLabels
     * @param  list<int>  $workdays
     */
    public function calculate(array $milestone, array $issues, string $metric = 'count', array $completedLabels = [], array $workdays = [1, 2, 3, 4, 5, 6, 7]): array
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

        $labels = $actual = $ideal = $dailyChanges = $workingDays = [];
        $plannedWorkdays = 0;
        for ($date = $start->addDay(); $date->lte($due); $date = $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $workdays, true)) {
                $plannedWorkdays++;
            }
        }

        $elapsedWorkdays = 0;
        for ($date = $start; $date->lte($chartEnd); $date = $date->addDay()) {
            $isWorkingDay = in_array($date->dayOfWeekIso, $workdays, true);
            $workingDays[] = $isWorkingDay;
            if ($date->gt($start) && $date->lte($due) && $isWorkingDay) {
                $elapsedWorkdays++;
            }

            $labels[] = $date->format('M j');
            $ideal[] = $date->gte($due)
                ? 0.0
                : round($initial * (1 - $elapsedWorkdays / max(1, $plannedWorkdays)), 2);
            if ($date->gt($today)) {
                $actual[] = null;

                continue;
            }

            $previousDate = $date->subDay();
            $remainingOnDate = 0;
            $changes = [];
            foreach ($issues as $index => $issue) {
                $created = CarbonImmutable::parse($issue['created_at'])->startOfDay();
                if ($created->gt($date)) {
                    continue;
                }

                $completed = $this->isCompletedAt($issue, $date, $completedLabels);
                $remainingOnDate += $completed ? 0 : $this->valueAtDate($issue, $metric, $date);
                $events = $this->dailyIssueChanges($issue, $date, $previousDate, $created, $completedLabels);
                if ($events !== []) {
                    $changes[] = ['issue' => $index, 'events' => $events];
                }
            }
            $actual[] = $remainingOnDate;
            $dailyChanges[] = $changes;
        }

        $remaining = array_sum(array_map(
            fn (array $issue) => $this->isCompleted($issue, $completedLabels) ? 0 : $this->value($issue, $metric),
            $issues,
        ));

        return compact('labels', 'actual', 'ideal', 'dailyChanges', 'workingDays') + [
            'idealToday' => $ideal[count($dailyChanges) - 1] ?? null,
            'initial' => $initial,
            'total' => $totalAtEnd,
            'remaining' => $remaining,
            'completed' => $totalAtEnd - $remaining,
            'scope_added' => max(0, $totalAtEnd - $initial),
            'metric' => $metric,
        ];
    }

    /** @return list<string> */
    private function dailyIssueChanges(array $issue, CarbonImmutable $date, CarbonImmutable $previousDate, CarbonImmutable $created, array $completedLabels): array
    {
        $events = [];
        $labels = $this->labelsAt($issue, $date);
        $previousLabels = $created->eq($date) ? [] : $this->labelsAt($issue, $previousDate);

        if ($created->eq($date)) {
            $events[] = 'Issue created';
        }
        if (isset($issue['closed_at']) && CarbonImmutable::parse($issue['closed_at'])->startOfDay()->eq($date)) {
            $events[] = 'Issue closed';
        }
        foreach ($completedLabels as $label) {
            $wasApplied = $this->hasLabel($previousLabels, [$label]);
            $isApplied = $this->hasLabel($labels, [$label]);
            if ($wasApplied !== $isApplied) {
                $events[] = ($isApplied ? 'Label added: ' : 'Label removed: ').$label;
            }
        }
        $previousPoints = $this->storyPoints($previousLabels);
        $points = $this->storyPoints($labels);
        if ($previousPoints !== $points) {
            $events[] = "Story points: {$previousPoints} → {$points}";
        }

        return $events;
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
            if (preg_match(self::STORY_POINT_LABEL, $label, $matches) === 1) {
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
