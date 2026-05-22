<?php

use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Client\PagesController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\AdminAuth\AuthenticatedSessionController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Client\OrderController;
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

    Route::get('/admin/orders', [PageController::class, 'Orders'])->name('admin.orders.index');

});

Route::post('/cart/product/add/{product}', [CartController::class, 'addProduct'])->name('cart.product.add');
Route::post('/cart/products', [CartController::class, 'cartItems'])->name('cart.items.show');
Route::get('/cart/related-products', [CartController::class, 'cartRelatedProducts'])->name('cart.related.products');
Route::delete('/cart/items/{cartItemId}', [CartController::class, 'removeItem'])->name('cart.item.remove');
Route::post('/cart/items/{cartItemId}/update-quantity', [CartController::class, 'updateItemQuantity'])->name('cart.item.update-quantity');

Route::get('/index', [PagesController::class, 'HomePage'])->name('client.page.home');
Route::get('/products', [PagesController::class, 'ProductsPage'])->name('client.page.products');
Route::get('/product/page/{productId}', [PagesController::class, 'ProductPage'])->name('client.product.show');
Route::get('/products/{productId}/related', [PagesController::class, 'RelatedProducts'])->name('client.products.related');

Route::post('admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

Route::post('/checkout', [OrderController::class, 'store'])->name('client.order.checkout');

// Orders listing and details (controller performs auth checks)
Route::post('/orders', [OrderController::class, 'index'])->name('client.orders.index');
Route::post('/orders/{orderId}', [OrderController::class, 'show'])->name('client.orders.show');
Route::delete('/orders/{orderId}/delete', [OrderController::class, 'destroy'])->name('client.orders.destroy');

