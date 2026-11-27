@extends('layouts.app')
@section('title', 'Sprint retrospective — GitLab Tools')
@section('header', 'Sprint retrospective')

@section('content')
    <div class="page-head"><div><h2>Compare sprints</h2><p>Review story points at the start and end of each milestone.</p></div></div>
    @if($error || $errors->any())
        <div class="alert" role="alert">{{ $error ?? $errors->first() }}</div>
    @endif
    @if($milestones !== [])
        <section class="card">
            <form method="get" action="{{ route('retrospective.index') }}">
                <fieldset>
                    <legend>Select up to three past milestones</legend>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0">
                        @foreach($milestones as $milestone)
                            <label>
                                <input style="width:auto" type="checkbox" name="milestones[]" value="{{ $milestone['id'] }}"
                                    @checked(in_array((int) $milestone['id'], $selected, true))>
                                {{ $milestone['title'] }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <button class="primary" type="submit">Compare milestones</button>
            </form>
        </section>
    @elseif(!$error)
        <p>No past milestones with a start and due date are available.</p>
    @endif

    @if($rows !== [])
        <section class="card issues">
            <h3 class="section-title">Story points</h3>
            <p class="section-copy">
                Planned points use the start date. Completed and remaining points use the due date.
                Closed issues always count as completed.
                @if($settings['completedLabels'] !== [])
                    Completion labels: {{ implode(', ', $settings['completedLabels']) }}.
                @endif
            </p>
            <p class="section-copy">Based on issues currently assigned to each milestone and their available label history.</p>
            <div style="overflow-x:auto">
                <table style="width:100%;text-align:left;border-collapse:collapse" aria-label="Story points by milestone">
                    <thead>
                        <tr>
                            <th scope="col">Milestone</th>
                            <th scope="col">Planned</th>
                            <th scope="col">Completed</th>
                            <th scope="col">Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr style="border-top:1px solid var(--line)">
                                <th scope="row" style="padding:12px 16px 12px 0">
                                    <a href="{{ route('burndown.show', ['milestone' => $row['milestone']['id'], 'metric' => 'story_points']) }}">{{ $row['milestone']['title'] }}</a>
                                    <br><small class="muted">{{ $row['milestone']['start_date'] }} <i class="fi fi-rr-arrow-right" aria-hidden="true"></i> {{ $row['milestone']['due_date'] }}</small>
                                </th>
                                @if($row['points'] !== null)
                                    <td>{{ $row['points']['planned'] }}</td>
                                    <td>{{ $row['points']['completed'] }}</td>
                                    <td>{{ $row['points']['remaining'] }}</td>
                                @else
                                    <td colspan="3" class="muted">Unable to load issue history.</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif($selected !== [] && !$error)
        <p>No eligible milestones match the selection.</p>
    @endif
@endsection
