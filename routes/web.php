<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PhoneOtpController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WorkspaceSelectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['guest'])->group(function (): void {
    Route::get('/otp/login', [PhoneOtpController::class, 'create'])->name('otp.login');
    Route::post('/otp/request', [PhoneOtpController::class, 'requestOtp'])->name('otp.request');
    Route::get('/otp/verify', [PhoneOtpController::class, 'verifyForm'])->name('otp.verify.form');
    Route::post('/otp/verify', [PhoneOtpController::class, 'verify'])->name('otp.verify');

    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook'])
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook'])
        ->name('social.callback');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', function () {
        return redirect()->route('workspace.choose');
    })->name('dashboard');

    Route::get('/workspaces/choose', [WorkspaceSelectionController::class, 'choose'])->name('workspace.choose');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceSelectionController::class, 'switch'])->name('workspace.switch');
    Route::redirect('/subscription', '/workspace/subscriptions')->name('subscription.page');
    Route::redirect('/billing', '/workspace/payments')->name('billing.page');
    Route::redirect('/settings', '/profile')->name('settings.page');
    Route::redirect('/security', '/profile')->name('security.page');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

require __DIR__.'/auth.php';
