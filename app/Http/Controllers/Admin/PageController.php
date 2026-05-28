<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PageController extends Controller
{
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
            'totalProducts' => null,
            'totalOrders' => $totalOrders,
            'totalClients' => $totalClients,
            'totalRevenue' => $totalRevenue,
        ]);
    }

    public function Analytics()
    {
        return response()->json(['analytics' => 'analytics']);
    }

    public function stock()
    {
        $products = Product::with('images')->latest()->get();

        $stockOverview = $products
            ->groupBy(fn(Product $product) => $product->category ?: 'Uncategorized')
            ->map(function (Collection $categoryProducts, string $category) {
                $inStock = $categoryProducts
                    ->filter(fn(Product $product) => strtolower((string) $product->status) === 'in stock')
                    ->sum('stock_quantity');

                $lowStock = $categoryProducts
                    ->filter(fn(Product $product) => strtolower((string) $product->status) === 'low stock')
                    ->count();

                $outOfStock = $categoryProducts
                    ->filter(fn(Product $product) => strtolower((string) $product->status) === 'out of stock')
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

        return response()->json(['products' => $products, 'stockOverview' => $stockOverview]);
    }

    public function OrdersPage()
    {
        $allOrders = Order::with(['user', 'items', 'paymentDetail'])->latest()->get();
        return response()->json(['allOrders' => $allOrders]);
    }

    public function ClientInfo()
    {
        $allClients = User::with('orders.items')->get();
        return response()->json(['allClients' => $allClients]);
    }

    public function settings(Request $request)
    {
        $currentAuthenticatedAdmin = Admin::with('actions')->find($request->user('admin')->id);
            // Delegate to SessionController to include devices and admins
            $sessionController = app(SessionController::class);
            return $sessionController->index($request);
    }

    /** Delete a client (User) by id. Requires an authenticated admin. */
    public function deleteClient(Request $request, $clientId)
    {
        $adminUser = $request->user('admin') ?? auth()->guard('admin')->user();
        if (!$adminUser) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);
        }

        $client = User::find($clientId);
        if (!$client) return response()->json(['success' => false, 'message' => 'Client not found'], 404);

        try {
            $client->delete();
            return response()->json(['success' => true, 'message' => 'Client deleted']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not delete client'], 500);
        }
    }
}
