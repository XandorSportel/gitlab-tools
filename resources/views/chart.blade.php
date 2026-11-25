@extends('layouts.app')
@section('title', $milestoneData['title'].' — Burndown')
@section('header', 'Burndown charts')
@section('content')
    <div class="page-head"><div><h2>{{ $milestoneData['title'] }}</h2><p>{{ $milestoneData['start_date'] }} → {{ $milestoneData['due_date'] }}</p></div></div>
    <div class="toolbar">
        <a class="button secondary" href="{{ route('burndown.index') }}">← All milestones</a>
        <form method="get"><label for="metric" style="margin:0">Measure by</label><select id="metric" name="metric" onchange="this.form.submit()"><option value="count" @selected($metric === 'count')>Issue count</option><option value="weight" @selected($metric === 'weight')>Issue weight</option><option value="story_points" @selected($metric === 'story_points')>Story points</option></select></form>
    </div>
    <div class="stats">
        <div class="stat"><span>Initial scope</span><strong>{{ $chart['initial'] }}</strong></div>
        <div class="stat"><span>Completed</span><strong>{{ $chart['completed'] }}</strong></div>
        <div class="stat"><span>Remaining</span><strong>{{ $chart['remaining'] }}</strong></div>
        <div class="stat"><span>Scope added</span><strong>+{{ $chart['scope_added'] }}</strong></div>
    </div>
    <section class="card">
        <h3 class="section-title">Burndown trajectory</h3><p class="section-copy">Daily remaining work compared with the ideal trajectory.</p>
        <div class="section-copy">
            Working days ({{ $burndownSettings['personalWorkdays'] ? 'personal' : 'project default' }}):
            {{ implode(', ', array_intersect_key(\App\Models\User::WEEKDAY_NAMES, array_flip($burndownSettings['workdays']))) }}<br>
            Completion labels ({{ $burndownSettings['personalLabels'] ? 'personal' : 'project default' }}):
            {{ implode(', ', $burndownSettings['completedLabels']) ?: 'Closed issues only' }}.
            <a href="{{ route('settings') }}">Settings</a>
        </div>
        <div class="chart-wrap"><svg id="burndown" class="chart" role="img" aria-label="Burndown chart"></svg></div>
        <div class="legend"><span><i class="dot" style="background:#6d45e6"></i>Actual remaining</span><span><i class="dot" style="background:#c8cedb"></i>Ideal trajectory</span></div>
    </section>
    @if($chart['dailyChanges'] !== [])
        <section class="card issues">
            <h3 class="section-title">Daily changes</h3>
            <p class="section-copy">Select a chart point or choose a date to see changes between daily snapshots.</p>
            <label for="burndown-day">Date</label>
            <select id="burndown-day">
                @foreach($chart['dailyChanges'] as $day => $changes)
                    <option value="{{ $day }}" @selected($loop->last)>{{ $chart['labels'][$day] }}</option>
                @endforeach
            </select>
            <div aria-live="polite">
                @foreach($chart['dailyChanges'] as $day => $changes)
                    <div data-day-details="{{ $day }}" @if(!$loop->last) hidden @endif>
                        <p>Actual remaining: {{ $chart['actual'][$day] }}</p>
                        @forelse($changes as $change)
                            @php($changedIssue = $issues[$change['issue']])
                            <div class="issue">
                                <span>#{{ $changedIssue['iid'] }}</span>
                                <a href="{{ $changedIssue['web_url'] }}" target="_blank" rel="noopener">{{ $changedIssue['title'] }}</a>
                                <span>{{ implode(' · ', $change['events']) }}</span>
                            </div>
                        @empty
                            <p class="muted">No issue changes recorded for this date.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </section>
    @endif
    <section class="card issues" aria-labelledby="sprint-health-heading">
        <h3 id="sprint-health-heading" class="section-title">Sprint health check</h3>
        <p class="section-copy">An issue can appear in more than one category.</p>
        @foreach([
            'missing' => 'No story-point label',
            'conflicting' => 'Multiple story-point labels',
            'openCompleted' => 'Counted as completed and still open in GitLab',
        ] as $category => $heading)
            <details style="padding:12px 0;border-top:1px solid var(--line)">
                <summary style="cursor:pointer">{{ $heading }} <span class="badge">{{ count($healthCheck[$category]) }}</span></summary>
                @forelse($healthCheck[$category] as $issue)
                    <div class="issue" style="margin-top:8px">
                        <span>#{{ $issue['iid'] }}</span>
                        <a href="{{ $issue['web_url'] }}" target="_blank" rel="noopener">{{ $issue['title'] }}</a>
                        <span class="muted">{{ implode(', ', $issue['labels'] ?? []) }}</span>
                    </div>
                @empty
                    <p class="muted">No matching issues.</p>
                @endforelse
            </details>
        @endforeach
    </section>
    <section class="card issues">
        <h3 class="section-title">Milestone scope</h3><p class="section-copy">{{ count($issues) }} issues</p>
        @foreach($issues as $issue)
            <div class="issue"><span class="muted">#{{ $issue['iid'] }}</span><a href="{{ $issue['web_url'] }}" target="_blank" rel="noopener">{{ $issue['title'] }}</a><span class="badge">{{ $issue['state'] }}{{ $metric === 'weight' ? ' · '.($issue['weight'] ?? 0).' pts' : '' }}</span></div>
        @endforeach
    </section>
@endsection
@push('scripts')
<script>
(() => {
  const data = {{ Illuminate\Support\Js::from(\Illuminate\Support\Arr::only($chart, ['labels', 'ideal', 'actual'])) }};
  const svg = document.getElementById('burndown');
  const daySelect = document.getElementById('burndown-day');
  const dayDetails = document.querySelectorAll('[data-day-details]');

  const showDay = (day) => {
    daySelect.value = day;
    dayDetails.forEach((details) => {
      details.hidden = details.dataset.dayDetails !== day;
    });
  };
  daySelect?.addEventListener('change', () => showDay(daySelect.value));
  svg.addEventListener('click', (event) => {
    const point = event.target.closest('[data-chart-day]');
    if (point && daySelect) {
      showDay(point.dataset.chartDay);
      daySelect.focus();
    }
  });
  const draw = () => {
    const w = svg.clientWidth || 900, h = 390, pad = {l:48,r:18,t:32,b:45};
    const max = Math.max(1, ...data.ideal, ...data.actual.filter(v => v !== null));
    const x = i => pad.l + i * (w-pad.l-pad.r) / Math.max(1,data.labels.length-1);
    const y = v => pad.t + (max-v) * (h-pad.t-pad.b) / max;
    const path = vals => vals.map((v,i) => v===null ? null : `${i && vals[i-1]!==null?'L':'M'}${x(i).toFixed(1)},${y(v).toFixed(1)}`).filter(Boolean).join(' ');
    let html = '';
    for(let i=0;i<=4;i++){ const value=max*(1-i/4), yy=pad.t+i*(h-pad.t-pad.b)/4; html+=`<line x1="${pad.l}" y1="${yy}" x2="${w-pad.r}" y2="${yy}" stroke="#e9ecf2"/><text x="${pad.l-10}" y="${yy+4}" text-anchor="end" fill="#7a8497" font-size="11">${Math.round(value*10)/10}</text>` }
    const ticks = Math.min(6,data.labels.length); for(let t=0;t<ticks;t++){const i=Math.round(t*(data.labels.length-1)/Math.max(1,ticks-1));html+=`<text x="${x(i)}" y="${h-16}" text-anchor="middle" fill="#7a8497" font-size="11">${data.labels[i]}</text>`}
    html += `<path d="${path(data.ideal)}" fill="none" stroke="#c8cedb" stroke-width="2" stroke-dasharray="6 6"/><path d="${path(data.actual)}" fill="none" stroke="#6d45e6" stroke-width="4" stroke-linejoin="round" stroke-linecap="round"/>`;
    data.actual.forEach((v, i) => {
      if (v !== null) {
        html += `<g data-chart-day="${i}" style="cursor:pointer">
          <circle cx="${x(i)}" cy="${y(v)}" r="12" fill="transparent"/>
          <circle cx="${x(i)}" cy="${y(v)}" r="3.5" fill="#fff" stroke="#6d45e6" stroke-width="2"/>
          <title>${data.labels[i]}: ${v}</title>
        </g>`;
      }
    });
    svg.setAttribute('viewBox',`0 0 ${w} ${h}`); svg.innerHTML=html;
  }; draw(); window.addEventListener('resize',draw);
})();
</script>
@endpush
