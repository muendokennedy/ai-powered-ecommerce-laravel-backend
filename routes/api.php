<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminAuth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\ProductController;

Route::group(['middleware' => ['auth:sanctum']], function() {
    Route::get('/admin/dashboard', function(Request $request){
        return response()->json([
        'admin' => $request->user()
        ]);
    })->name('admin.dashboard');

    // Admin Product Resource Management
    Route::post('/admin/product/add', [ProductController::class, 'store'])->name('admin.product.add');
});

Route::post('admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

