<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;

class PageController extends Controller
{
    //
    public function Dashboard()
    {

        $recentOrders = Order::latest()->limit(5)->get();
        $lowStock = Product::where('status', 'low stock')->limit(4)->get();
        $totalOrders = Order::all()->count();
        $totalClients = User::all()->count();
        $totalRevenue = '364536';

        return response()->json([
            'recentOrders' => $recentOrders,
            'lowStock' => $lowStock,
            'totalProducts' => $totalProducts,
            'totalOrders' => $totalOrders,
            'totalClients' => $totalClients,
            'totalRevenue' => $totalRevenue
        ]);
    }

    public function Analytics()
    {
        return response()->json([
            'analytics' => 'analytics'
        ]);
    }
    public function stock()
    {
        $products = Product::with('images')->latest()->get();

        $stockOverview = $products
            ->groupBy(function (Product $product) {
                return $product->category ?: 'Uncategorized';
            })
            ->map(function (Collection $categoryProducts, string $category) {
                $inStock = $categoryProducts
                    ->filter(fn (Product $product) => strtolower((string) $product->status) === 'in stock')
                    ->sum('stock_quantity');

                $lowStock = $categoryProducts
                    ->filter(fn (Product $product) => strtolower((string) $product->status) === 'low stock')
                    ->count();

                $outOfStock = $categoryProducts
                    ->filter(fn (Product $product) => strtolower((string) $product->status) === 'out of stock')
                    ->count();

                $totalValue = $categoryProducts->sum(function (Product $product) {
                    $unitPrice = $product->discount_price && $product->discount_price > 0
                        ? $product->discount_price
                        : $product->base_price;

                    return (float) $unitPrice * (float) $product->stock_quantity;
                });

                return [
                    'category' => $category,
                    'totalProducts' => $categoryProducts->count(),
                    'inStock' => (float) $inStock,
                    'lowStock' => $lowStock,
                    'outOfStock' => $outOfStock,
                    'totalValue' => round($totalValue, 2),
                ];
            })
            ->sortBy('category')
            ->values();

        return response()->json([
            'products' => $products,
            'stockOverview' => $stockOverview,
        ]);
    }
    public function Orders()
    {
        return response()->json([
            'orders' => 'orders'
        ]);
    }
    public function ClientInfo()
    {
        return response()->json([
            'info' => 'info'
        ]);
    }
    public function settings()
    {
        return response()->json([
            'settings' => 'settings'
        ]);
    }
}
