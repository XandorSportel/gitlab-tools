<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BurndownController;
use App\Http\Controllers\PlanningController;
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
        Route::get('/', [PlanningController::class, 'index'])->name('index');
        Route::post('/', [PlanningController::class, 'store'])->can('create planning tasks')->name('store');
        Route::get('/{task}/edit', [PlanningController::class, 'edit'])->whereNumber('task')->can('edit planning tasks')->name('edit');
        Route::patch('/{task}', [PlanningController::class, 'update'])->whereNumber('task')->can('edit planning tasks')->name('update');
        Route::patch('/{task}/completion', [PlanningController::class, 'updateCompletion'])
            ->whereNumber('task')->can('edit planning tasks')->name('completion.update');
        Route::delete('/{task}', [PlanningController::class, 'destroy'])->whereNumber('task')->can('delete planning tasks')->name('destroy');
    });
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
    Route::get('/settings', [AccountController::class, 'settings'])->name('settings');
    Route::patch('/settings/burndown', [AccountController::class, 'updateBurndownSettings'])->name('settings.burndown.update');
    Route::delete('/settings/burndown', [AccountController::class, 'resetBurndownSettings'])->name('settings.burndown.reset');
});
