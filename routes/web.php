<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/admin/login', function(){
    return response()->json(['message' => 'The admin is not authenticated']);
})->name('admin.login');


require __DIR__.'/auth.php';
require __DIR__.'/adminauth.php';
