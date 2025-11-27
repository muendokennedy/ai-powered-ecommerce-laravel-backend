<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AdminAuth\RegisteredUserController;

use App\Http\Controllers\AdminAuth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;


Route::post('admin/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('admin.register');

Route::post('admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('admin.login');

Route::post('admin/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');

Route::post('admin/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.store');

Route::get('admin/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('admin/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::post('admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
