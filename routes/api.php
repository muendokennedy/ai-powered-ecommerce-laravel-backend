<?php

use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Client\PagesController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\AdminAuth\AuthenticatedSessionController;
use App\Http\Controllers\CartController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function() {
    Route::get('/admin/dashboard', function(Request $request){
        return response()->json([
        'admin' => $request->user()
        ]);
    })->name('admin.dashboard');
    Route::get('/admin/stock', [PageController::class, 'stock'])->name('admin.stock');
    // Admin Product Resource Management
    Route::post('/admin/product/add', [ProductController::class, 'store'])->name('admin.product.add');
    Route::post('/admin/product/update/{product}', [ProductController::class, 'update'])->name('admin.product.update');
    Route::delete('/admin/product/delete/{product}', [ProductController::class, 'destroy'])->name('admin.product.destroy');

});

Route::post('/cart/product/add/{product}', [CartController::class, 'addProduct'])->name('cart.product.add');
Route::post('/cart/products', [CartController::class, 'cartItems'])->name('cart.items.show');
Route::delete('/cart/items/{cartItemId}', [CartController::class, 'removeItem'])->name('cart.item.remove');
Route::post('/cart/items/{cartItemId}/update-quantity', [CartController::class, 'updateItemQuantity'])->name('cart.item.update-quantity');

Route::get('/products', [PagesController::class, 'productsPage'])->name('client.page.products');

Route::post('admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

