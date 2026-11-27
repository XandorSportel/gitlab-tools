@php
    $currentMilestone = auth()->user()->planning_milestone_id;
    $selectedMilestone = old('planning_milestone_id', $currentMilestone);
    $doneLabel = old('planning_done_label', auth()->user()->planningDoneLabel());
@endphp

<section class="card mt-[18px] max-w-[760px]" id="planning-settings" aria-labelledby="planning-settings-title">
    <h3 class="section-title" id="planning-settings-title">Planning preferences</h3>
    <p class="section-copy">Choose the sprint shown in your Planning view and the GitLab label that marks issues as done.</p>

    @if (session('planning_status'))
        <div class="alert success" role="status">{{ session('planning_status') }}</div>
    @endif

    @if ($error)
        <p class="alert" role="alert">{{ $error }}</p>
    @endif

    <form class="grid gap-4" method="post" action="{{ route('settings.planning.update') }}">
        @csrf
        @method('PATCH')
        <div>
            <label for="planning_milestone_id">Current sprint</label>
            <select id="planning_milestone_id" name="planning_milestone_id">
                <option value="">Choose a milestone</option>
                @if ($currentMilestone && ! collect($milestones)->contains(fn ($milestone) => (int) $milestone['id'] === $currentMilestone))
                    <option value="{{ $currentMilestone }}" @selected($selectedMilestone == $currentMilestone)>Current milestone (unavailable)</option>
                @endif
                @foreach ($milestones as $milestone)
                    <option value="{{ $milestone['id'] }}" @selected($selectedMilestone == $milestone['id'])>{{ $milestone['title'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="planning_done_label">Done label</label>
            <input id="planning_done_label" name="planning_done_label" value="{{ is_string($doneLabel) ? $doneLabel : '' }}" maxlength="255" placeholder="Done">
        </div>
        <div><button type="submit" class="primary">Save planning preferences</button></div>
    </form>
</section>
