<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlanningTagController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BurndownController;
use App\Http\Controllers\PlanningBoardController;
use App\Http\Controllers\PlanningController;
use App\Services\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [BurndownController::class, 'index'])->name('burndown.index');
    Route::get('/retrospective', [BurndownController::class, 'retrospective'])->name('retrospective.index');
    Route::get('/chart/{milestone}', [BurndownController::class, 'show'])->whereNumber('milestone')->name('burndown.show');
    Route::prefix('planning')->name('planning.')->middleware('can:view planning')->group(function () {
        Route::get('/', [PlanningBoardController::class, 'index'])->name('index');
        Route::get('/tasks', [PlanningController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/create', [PlanningController::class, 'create'])
            ->can('create planning tasks')->name('tasks.create');
        Route::post('/tasks', [PlanningController::class, 'store'])
            ->can('create planning tasks')->name('tasks.store');
        Route::get('/issues/{issue}/tasks/create', [PlanningController::class, 'create'])
            ->whereNumber('issue')->can('create planning tasks')->name('create');
        Route::post('/issues/{issue}/tasks', [PlanningController::class, 'store'])
            ->whereNumber('issue')->can('create planning tasks')->name('store');
        Route::patch('/issues/{issue}/tags', [PlanningBoardController::class, 'updateTags'])
            ->whereNumber('issue')->can('manage planning tags')->name('tags.update');
        Route::get('/{task}/edit', [PlanningController::class, 'edit'])->whereNumber('task')->can('edit planning tasks')->name('edit');
        Route::patch('/{task}', [PlanningController::class, 'update'])->whereNumber('task')->can('edit planning tasks')->name('update');
        Route::patch('/{task}/completion', [PlanningController::class, 'updateCompletion'])
            ->whereNumber('task')->can('edit planning tasks')->name('completion.update');
        Route::delete('/{task}', [PlanningController::class, 'destroy'])->whereNumber('task')->can('delete planning tasks')->name('destroy');
    });
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', function (): RedirectResponse {
            foreach (AdminAccess::Pages as $page => $adminPage) {
                if (auth()->user()->can($adminPage['permission'])) {
                    return redirect()->route('admin.'.$page.'.index');
                }
            }

            abort(403);
        })->name('index');

        Route::middleware('can:admin users')->group(function () {
            Route::resource('users', UserController::class)->except('show');
            Route::patch('/users/{user}/access', [UserController::class, 'updateAccess'])
                ->can('admin permissions')->name('users.access');
        });

        Route::resource('roles', RoleController::class)->except('show')->middleware('can:admin roles');
        Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'update', 'destroy'])
            ->middleware('can:admin permissions');
        Route::resource('tags', PlanningTagController::class)->only(['index', 'store', 'update', 'destroy'])
            ->middleware('can:admin planning tags');
    });
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
    Route::get('/settings', [AccountController::class, 'settings'])->name('settings');
    Route::patch('/settings/planning', [AccountController::class, 'updatePlanningSettings'])
        ->can('view planning')->name('settings.planning.update');
    Route::patch('/settings/burndown', [AccountController::class, 'updateBurndownSettings'])->name('settings.burndown.update');
    Route::delete('/settings/burndown', [AccountController::class, 'resetBurndownSettings'])->name('settings.burndown.reset');
});
