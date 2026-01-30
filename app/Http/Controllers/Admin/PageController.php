<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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
    public function Stock()
    {
        return response()->json([
            'stock' => 'stock'
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
