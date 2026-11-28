@extends('layouts.app')
@section('title','Settings — GitLab Tools')
@section('header','Settings')
@section('content')
<div class="page-head"><div><h2>Application settings</h2><p>Runtime configuration for this GitLab Tools installation.</p></div></div>
<section class="card" style="max-width:760px"><h3 class="section-title">GitLab connection</h3><p class="section-copy">These values are managed through the server environment and cannot be changed here.</p><div class="settings-list"><div class="setting-row"><strong>GitLab URL</strong><span>{{ $gitlab['url'] }}</span></div><div class="setting-row"><strong>Project</strong><span>{{ $gitlab['project'] ?: 'Not configured' }}</span></div><div class="setting-row"><strong>Access token</strong><span class="{{ $gitlab['token_configured'] ? 'ok':'bad' }}">{{ $gitlab['token_configured'] ? 'Configured':'Not configured' }}</span></div></div></section>
<section class="card" style="max-width:760px;margin-top:18px">
    <h3 class="section-title">Burndown preferences</h3>
    <section aria-labelledby="project-defaults-heading" style="background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:16px;margin-bottom:24px">
        <h4 id="project-defaults-heading" class="section-title">Project defaults</h4>
        <p class="section-copy">Managed on the server and used when you have no personal preferences.</p>
        <dl>
            <div class="setting-row" style="grid-template-columns:repeat(auto-fit,minmax(min(100%, 170px),1fr));gap:6px">
                <dt class="muted">Working days</dt>
                <dd>{{ count($projectWorkdays) === 7 ? 'Every day' : implode(', ', array_intersect_key(\App\Models\User::WEEKDAY_NAMES, array_flip($projectWorkdays))) }}</dd>
            </div>
            <div class="setting-row" style="grid-template-columns:repeat(auto-fit,minmax(min(100%, 170px),1fr));gap:6px">
                <dt class="muted">Completion labels</dt>
                <dd style="display:flex;flex-wrap:wrap;gap:6px">
                    @forelse($projectCompletedLabels as $label)
                        <span class="badge" style="overflow-wrap:anywhere">{{ $label }}</span>
                    @empty
                        <span>Closed issues only</span>
                    @endforelse
                </dd>
            </div>
        </dl>
    </section>
    @if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
    <h4 class="section-title" style="margin-bottom:12px">Personal preferences</h4>
    <form method="post" action="{{ route('settings.burndown.update') }}">
        @csrf
        @method('PATCH')
        <fieldset>
            <legend>Working days</legend>
            <p class="section-copy">The ideal line drops on working days. Actual progress is shown every day.</p>
            @foreach(\App\Models\User::WEEKDAY_NAMES as $number => $day)
                <label style="display:inline-block;margin-right:12px">
                    <input style="width:auto" type="checkbox" name="workdays[]" value="{{ $number }}"
                        @checked(in_array($number, (array) old('workdays', $errors->any() ? [] : $workdays)))>
                    {{ $day }}
                </label>
            @endforeach
        </fieldset>
        <div style="margin:16px 0">
            <label for="completed_labels">Completion labels</label>
            <input id="completed_labels" name="completed_labels" maxlength="1000" value="{{ old('completed_labels', implode(', ', $completedLabels)) }}" aria-describedby="completed-labels-help">
            <p id="completed-labels-help" class="section-copy">Separate GitLab labels with commas. Leave blank to count only closed issues as complete.</p>
        </div>
        <input type="hidden" name="hide_chart_text" value="0">
        <label class="mb-4">
            <input class="w-auto!" type="checkbox" name="hide_chart_text" value="1" @checked(old('hide_chart_text', $hideChartText))>
            Hide the burndown chart explanation
        </label>
        <button type="submit" class="primary">Save burndown preferences</button>
    </form>
    @if($personalWorkdays || $personalLabels || $hideChartText)
        <form method="post" action="{{ route('settings.burndown.reset') }}" style="margin-top:12px">
            @csrf
            @method('DELETE')
            <button type="submit" class="button secondary">Use project defaults</button>
        </form>
    @endif
</section>
@can('view planning')
    @include('account.planning-settings', ['milestones' => $planningMilestones, 'error' => $planningError])
@endcan
@endsection
