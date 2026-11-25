<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

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

    public function settings(Request $request): View
    {
        return view('account.settings', [
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

    public function updateBurndownSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workdays' => ['required', 'array', 'min:1', 'max:7'],
            'workdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'completed_labels' => ['present', 'nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $user->burndown_workdays = array_values(array_map(intval(...), $data['workdays']));
        $user->burndown_completed_labels = array_values(array_filter(
            array_map('trim', explode(',', $data['completed_labels'] ?? '')),
            fn (string $label): bool => $label !== '',
        ));
        $user->save();

        return back()->with('status', 'Burndown settings updated.');
    }

    public function resetBurndownSettings(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->burndown_workdays = null;
        $user->burndown_completed_labels = null;
        $user->save();

        return back()->with('status', 'Project defaults restored.');
    }
}
