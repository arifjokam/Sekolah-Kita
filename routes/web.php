<?php

use App\Http\Controllers\AdministratorFoundationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->middleware('throttle:password-reset')
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware(['auth', 'auth.session', 'active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationPromptController::class, 'show'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationPromptController::class, 'store'])
        ->middleware('throttle:verification')
        ->name('verification.send');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');
});

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:administrator,guru,siswa'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:administrator'])->group(function (): void {
    Route::get('/administrator', [AdministratorFoundationController::class, 'index'])->name('admin.foundation');
});
