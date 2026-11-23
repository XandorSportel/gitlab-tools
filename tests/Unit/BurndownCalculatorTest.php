<?php

namespace Tests\Unit;

use App\Services\BurndownCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BurndownCalculatorTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_reconstructs_daily_scope_and_completed_work(): void
    {
        CarbonImmutable::setTestNow('2026-11-04 12:00:00');
        $milestone = ['start_date' => '2026-11-01', 'due_date' => '2026-11-05'];
        $issues = [
            ['created_at' => '2026-10-30T09:00:00Z', 'closed_at' => '2026-11-03T10:00:00Z', 'state' => 'closed', 'weight' => 3],
            ['created_at' => '2026-11-02T09:00:00Z', 'closed_at' => null, 'state' => 'opened', 'weight' => 2],
        ];

        $result = (new BurndownCalculator)->calculate($milestone, $issues, 'count');

        $this->assertSame(1, $result['initial']);
        $this->assertSame(1, $result['scope_added']);
        $this->assertSame([1, 2, 1, 1, null], $result['actual']);
        $this->assertSame(1, $result['remaining']);
    }

    public function test_it_uses_issue_weights(): void
    {
        CarbonImmutable::setTestNow('2026-11-05');
        $result = (new BurndownCalculator)->calculate(
            ['start_date' => '2026-11-01', 'due_date' => '2026-11-05'],
            [['created_at' => '2026-10-30T09:00:00Z', 'closed_at' => null, 'state' => 'opened', 'weight' => 5]],
            'weight'
        );

        $this->assertSame(5.0, $result['remaining']);
        $this->assertSame(5.0, $result['initial']);
    }

    public function test_story_point_labels_determine_issue_value(): void
    {
        CarbonImmutable::setTestNow('2026-11-05');
        $result = (new BurndownCalculator)->calculate(
            ['start_date' => '2026-11-01', 'due_date' => '2026-11-05'],
            [
                ['created_at' => '2026-10-30T09:00:00Z', 'closed_at' => '2026-11-03T09:00:00Z', 'state' => 'closed', 'labels' => ['backend', 'SP-8']],
                ['created_at' => '2026-10-31T09:00:00Z', 'closed_at' => null, 'state' => 'opened', 'labels' => ['sp-1']],
                ['created_at' => '2026-10-31T09:00:00Z', 'closed_at' => null, 'state' => 'opened', 'labels' => ['no-estimate']],
            ],
            'story_points',
        );

        $this->assertSame(9.0, $result['initial']);
        $this->assertSame(8.0, $result['completed']);
        $this->assertSame(1.0, $result['remaining']);
        $this->assertSame([9.0, 9.0, 1.0, 1.0, 1.0], $result['actual']);
    }

    public function test_it_requires_milestone_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new BurndownCalculator)->calculate(['start_date' => null, 'due_date' => null], []);
    }

    public function test_an_overdue_chart_continues_through_today(): void
    {
        CarbonImmutable::setTestNow('2026-11-08');
        $result = (new BurndownCalculator)->calculate(
            ['start_date' => '2026-11-01', 'due_date' => '2026-11-05'],
            [['created_at' => '2026-10-30T09:00:00Z', 'closed_at' => '2026-11-07T09:00:00Z', 'state' => 'closed']],
        );

        $this->assertCount(8, $result['labels']);
        $this->assertSame([1, 1, 1, 1, 1, 1, 0, 0], $result['actual']);
        $this->assertSame(0.0, $result['ideal'][7]);
    }
}
