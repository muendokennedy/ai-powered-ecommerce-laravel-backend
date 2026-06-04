<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Models\AdminActivity;

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

        $response = ['currentAuthenticatedAdmin' => $currentAuthenticatedAdmin];

        // if primary admin, include full admins list
        if ($currentAuthenticatedAdmin && strtolower($currentAuthenticatedAdmin->role) === 'primary admin') {
            $response['admins'] = Admin::with('actions')->get();
        }

        return response()->json($response);
    }

    /**
     * Suspend an admin (set status to 0). Only primary admins may perform this.
     */
    public function suspendAdmin(Request $request, $adminId)
    {
        $actor = $request->user('admin') ?? auth('admin')->user();
        if (!$actor) return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);
        if (strtolower($actor->role) !== 'primary admin') return response()->json(['success' => false, 'message' => 'Only primary admin may perform this'], 403);

        $target = Admin::find($adminId);
        if (!$target) return response()->json(['success' => false, 'message' => 'Admin not found'], 404);
        if (strtolower($target->role) === 'primary admin') return response()->json(['success' => false, 'message' => 'Cannot suspend primary admin'], 400);

        try {
            $target->status = 0;
            $target->save();

            AdminActivity::create([
                'admin_id' => $target->id,
                'action' => json_encode(['activity' => 'Account suspended by '.$actor->id, 'time' => now()->timestamp]),
                'total_actions' => ($target->totalActions ?? 0),
                'last_login' => $target->last_login ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Admin suspended']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not suspend admin'], 500);
        }
    }

    /**
     * Delete an admin. Only allowed when admin is already suspended (status = 0). Only primary admins may perform this.
     */
    public function deleteAdmin(Request $request, $adminId)
    {
        $actor = $request->user('admin') ?? auth('admin')->user();
        if (!$actor) return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);
        if (strtolower($actor->role) !== 'primary admin') return response()->json(['success' => false, 'message' => 'Only primary admin may perform this'], 403);

        $target = Admin::find($adminId);
        if (!$target) return response()->json(['success' => false, 'message' => 'Admin not found'], 404);
        if (strtolower($target->role) === 'primary admin') return response()->json(['success' => false, 'message' => 'Cannot delete primary admin'], 400);
        if ((int)$target->status !== 0) return response()->json(['success' => false, 'message' => 'Admin must be suspended before deletion'], 400);

        try {
            $target->delete();
            return response()->json(['success' => true, 'message' => 'Admin deleted']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not delete admin'], 500);
        }
    }

    /** Delete a client (User) by id. Requires an authenticated admin. */
    public function deleteClient(Request $request, $clientId)
    {
        $adminUser = $request->user('admin') ?? auth('admin')->user();
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
