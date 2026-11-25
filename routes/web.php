<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BurndownController;
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
    Route::get('/chart/{milestone}', [BurndownController::class, 'show'])->whereNumber('milestone')->name('burndown.show');
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
    Route::get('/settings', [AccountController::class, 'settings'])->name('settings');
    Route::patch('/settings/burndown', [AccountController::class, 'updateBurndownSettings'])->name('settings.burndown.update');
    Route::delete('/settings/burndown', [AccountController::class, 'resetBurndownSettings'])->name('settings.burndown.reset');
});
