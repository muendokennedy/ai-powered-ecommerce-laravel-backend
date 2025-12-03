<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/admin/dashboard', function (Request $request) {

    return response()->json([
        'admin' => $request->user()
    ]);

})->name('admin.dashboard');
