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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function Dashboard()
    {
        // recent orders with relationships (user, items, payment)
        $recentOrders = Order::with(['user', 'items', 'paymentDetail'])->latest()->limit(5)->get();

        // low stock products (include images when available)
        $lowStock = Product::with('images')->whereRaw("LOWER(COALESCE(status,'') ) = 'in stock'")->limit(4)->get();

        $totalOrders = Order::count();
        $totalClients = User::count();
        $totalProducts = Product::count();

        // compute total revenue by summing order items total_price + shipping
        $orders = Order::with('items')->get();
        $totalRevenue = 0.0;
        foreach ($orders as $o) {
            $items = $o->items ?: collect([]);
            $subtotal = $items->sum(function ($it) {
                return (float) ($it->total_price ?? ((float) ($it->price_at_purchase ?? 0) * (int) ($it->quantity ?? 0)));
            });
            $shipping = (float) ($o->shipping_cost ?? 0);
            $totalRevenue += $subtotal + $shipping;
        }

        // revenue this month and previous month for quick comparison
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfPrevMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endOfPrevMonth = $startOfMonth->copy()->subSecond();

        $revenueThisMonth = 0.0;
        $revenueLastMonth = 0.0;

        $ordersThisMonth = Order::with('items')->whereBetween('created_at', [$startOfMonth, $now])->get();
        foreach ($ordersThisMonth as $o) {
            $revenueThisMonth += $o->items->sum(fn($it) => (float) ($it->total_price ?? ((float) ($it->price_at_purchase ?? 0) * (int) ($it->quantity ?? 0)))) + (float) ($o->shipping_cost ?? 0);
        }

        $ordersLastMonth = Order::with('items')->whereBetween('created_at', [$startOfPrevMonth, $endOfPrevMonth])->get();
        foreach ($ordersLastMonth as $o) {
            $revenueLastMonth += $o->items->sum(fn($it) => (float) ($it->total_price ?? ((float) ($it->price_at_purchase ?? 0) * (int) ($it->quantity ?? 0)))) + (float) ($o->shipping_cost ?? 0);
        }

        return response()->json([
            'recentOrders' => $recentOrders,
            'lowStock' => $lowStock,
            'totalProducts' => $totalProducts,
            'totalOrders' => $totalOrders,
            'totalClients' => $totalClients,
            'totalRevenue' => round($totalRevenue, 2),
            'revenueThisMonth' => round($revenueThisMonth, 2),
            'revenueLastMonth' => round($revenueLastMonth, 2),
        ]);
    }

    public function Analytics()
    {
        // Build monthly aggregates for last 12 months
        $now = Carbon::now();
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $now->copy()->subMonths($i);
            $months[] = $m->format('Y-m');
        }

        $revenueByMonth = [];
        $ordersByMonth = [];
        foreach ($months as $m) {
            [$y, $mm] = explode('-', $m);
            $start = Carbon::createFromFormat('Y-m-d H:i:s', "$y-$mm-01 00:00:00")->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $orders = Order::with('items')->whereBetween('created_at', [$start, $end])->get();
            $ordersCount = $orders->count();
            $monthRevenue = 0.0;
            foreach ($orders as $o) {
                $monthRevenue += $o->items->sum(fn($it) => (float) ($it->total_price ?? ((float) ($it->price_at_purchase ?? 0) * (int) ($it->quantity ?? 0)))) + (float) ($o->shipping_cost ?? 0);
            }

            $revenueByMonth[] = ['month' => $m, 'total' => round($monthRevenue, 2)];
            $ordersByMonth[] = ['month' => $m, 'count' => $ordersCount];
        }

        // Top products by quantity sold
        $items = DB::table('order_items')
            ->select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_revenue'))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        $topProducts = [];
        foreach ($items as $it) {
            $prod = Product::find($it->product_id);
            if (!$prod) continue;
            $topProducts[] = [
                'id' => $prod->id,
                'name' => $prod->name,
                'sku' => $prod->product_sku_id ?? null,
                'category' => $prod->category ?? null,
                'sales' => (int) $it->total_qty,
                'revenue' => round((float) $it->total_revenue, 2),
            ];
        }

        // Sales by category
        $categoryRows = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.category', DB::raw('SUM(order_items.total_price) as revenue'), DB::raw('SUM(order_items.quantity) as quantity'))
            ->groupBy('products.category')
            ->get();

        $salesByCategory = [];
        foreach ($categoryRows as $r) {
            $salesByCategory[] = ['category' => $r->category ?? 'Uncategorized', 'revenue' => round((float) $r->revenue, 2), 'quantity' => (int) $r->quantity];
        }

        $totalOrders = Order::count();
        $totalRevenue = 0.0;
        foreach (Order::with('items')->get() as $o) {
            $totalRevenue += $o->items->sum(fn($it) => (float) ($it->total_price ?? ((float) ($it->price_at_purchase ?? 0) * (int) ($it->quantity ?? 0)))) + (float) ($o->shipping_cost ?? 0);
        }

        $avgOrderValue = $totalOrders ? round($totalRevenue / $totalOrders, 2) : 0;

        // orders per day (last 30 days)
        $days = [];
        for ($d = 29; $d >= 0; $d--) {
            $date = Carbon::now()->subDays($d)->format('Y-m-d');
            $days[] = $date;
        }
        $ordersPerDay = [];
        foreach ($days as $day) {
            $start = Carbon::createFromFormat('Y-m-d', $day)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $day)->endOfDay();
            $count = Order::whereBetween('created_at', [$start, $end])->count();
            $ordersPerDay[] = ['date' => $day, 'count' => $count];
        }

        $clientsCount = User::count();

        return response()->json([
            'revenueByMonth' => $revenueByMonth,
            'ordersByMonth' => $ordersByMonth,
            'topProducts' => $topProducts,
            'salesByCategory' => $salesByCategory,
            'avgOrderValue' => $avgOrderValue,
            'ordersPerDay' => $ordersPerDay,
            'clientsCount' => $clientsCount,
            'totalRevenue' => round($totalRevenue, 2),
            'totalOrders' => $totalOrders,
        ]);
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
