<?php

namespace App\Http\Controllers;

use App\Services\PlanningSprint;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class AccountController extends Controller
{
    public function profile(): View
    {
        return view('account.profile');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
        ]);
        $request->user()->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Password updated.');
    }

    public function settings(Request $request, PlanningSprint $sprint): View
    {
        $milestones = [];
        $planningError = null;
        if ($request->user()->can('view planning')) {
            try {
                $milestones = $sprint->milestones();
            } catch (HttpClientException|RuntimeException $exception) {
                report($exception);
                $planningError = 'Unable to load milestones from GitLab.';
            }
        }

        return view('account.settings', [
            'planningMilestones' => $milestones,
            'planningError' => $planningError,
            ...$request->user()->burndownSettings(),
            'projectWorkdays' => config('services.gitlab.workdays'),
            'projectCompletedLabels' => config('services.gitlab.completed_labels'),
            'gitlab' => [
                'url' => config('services.gitlab.url'),
                'project' => config('services.gitlab.project'),
                'token_configured' => filled(config('services.gitlab.token')),
            ],
        ]);
    }

    public function updatePlanningSettings(Request $request, PlanningSprint $sprint): RedirectResponse
    {
        $data = $request->validate([
            'planning_milestone_id' => ['nullable', 'integer', 'min:1'],
            'planning_done_label' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $milestoneId = isset($data['planning_milestone_id']) ? (int) $data['planning_milestone_id'] : null;
        if ($milestoneId !== null && $milestoneId !== $user->planning_milestone_id) {
            try {
                $milestones = $sprint->milestones();
            } catch (HttpClientException|RuntimeException $exception) {
                report($exception);
                throw ValidationException::withMessages(['planning_milestone_id' => 'Unable to verify the milestone in GitLab.']);
            }

            if (! collect($milestones)->contains(fn (array $milestone): bool => (int) $milestone['id'] === $milestoneId)) {
                throw ValidationException::withMessages(['planning_milestone_id' => 'Choose a milestone from the configured GitLab project.']);
            }
        }

        $user->planning_milestone_id = $milestoneId;
        $user->planning_done_label = $data['planning_done_label'] ?? null;
        $user->save();

        return redirect()->to(route('settings').'#planning-settings')->with('planning_status', 'Planning preferences updated.');
    }

    public function updateBurndownSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workdays' => ['required', 'array', 'min:1', 'max:7'],
            'workdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'completed_labels' => ['present', 'nullable', 'string', 'max:1000'],
            'hide_chart_text' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->burndown_workdays = array_values(array_map(intval(...), $data['workdays']));
        $user->burndown_completed_labels = array_values(array_filter(
            array_map('trim', explode(',', $data['completed_labels'] ?? '')),
            fn (string $label): bool => $label !== '',
        ));
        if (isset($data['hide_chart_text'])) {
            $user->burndown_hide_chart_text = (bool) $data['hide_chart_text'];
        }
        $user->save();

        return back()->with('status', 'Burndown settings updated.');
    }

    public function resetBurndownSettings(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->burndown_workdays = null;
        $user->burndown_completed_labels = null;
        $user->burndown_hide_chart_text = false;
        $user->save();

        return back()->with('status', 'Project defaults restored.');
    }
}
