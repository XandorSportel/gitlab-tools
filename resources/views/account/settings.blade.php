@extends('layouts.app')
@section('title','Settings — GitLab Tools')
@section('header','Settings')
@section('content')
<div class="page-head"><div><h2>Application settings</h2><p>Runtime configuration for this GitLab Tools installation.</p></div></div>
<section class="card" style="max-width:760px"><h3 class="section-title">GitLab connection</h3><p class="section-copy">These values are managed through the server environment and cannot be changed here.</p><div class="settings-list"><div class="setting-row"><strong>GitLab URL</strong><span>{{ $gitlab['url'] }}</span></div><div class="setting-row"><strong>Project</strong><span>{{ $gitlab['project'] ?: 'Not configured' }}</span></div><div class="setting-row"><strong>Access token</strong><span class="{{ $gitlab['token_configured'] ? 'ok':'bad' }}">{{ $gitlab['token_configured'] ? 'Configured':'Not configured' }}</span></div></div></section>
<section class="card" style="max-width:760px;margin-top:18px">
    <h3 class="section-title">Burndown preferences</h3>
    <p class="section-copy">These settings apply to the charts you view.</p>
    @if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('settings.burndown.update') }}">
        @csrf
        @method('PATCH')
        <fieldset>
            <legend>Working days</legend>
            <p class="section-copy">The ideal line drops on working days. Actual progress is shown every day.</p>
            @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $index => $day)
                <label style="display:inline-block;margin-right:12px">
                    <input style="width:auto" type="checkbox" name="workdays[]" value="{{ $index + 1 }}"
                        @checked(in_array($index + 1, (array) old('workdays', $errors->any() ? [] : $workdays)))>
                    {{ $day }}
                </label>
            @endforeach
        </fieldset>
        <div style="margin:16px 0">
            <label for="completed_labels">Completion labels</label>
            <input id="completed_labels" name="completed_labels" maxlength="1000" value="{{ old('completed_labels', implode(', ', $completedLabels)) }}" aria-describedby="completed-labels-help">
            <p id="completed-labels-help" class="section-copy">Separate GitLab labels with commas. Leave blank to count only closed issues as complete.</p>
        </div>
        <button type="submit" class="primary">Save burndown preferences</button>
    </form>
</section>
@endsection
