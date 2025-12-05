<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuth\AuthenticatedSessionController;

Route::middleware(['auth:sanctum'])->get('/admin/dashboard', function (Request $request) {

    return response()->json([
        'admin' => $request->user()
    ]);

})->name('admin.dashboard');

Route::post('admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

