<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentDetail;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function store(CheckoutRequest $request): JsonResponse
    {
        // perform authentication check her

        $user = $request->user();
        
           if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Please log in to place an order.',
                'error' => 'unauthenticated'
            ], 401);
        }

        $payload = $request->validated();

        DB::beginTransaction();
        try {
            // Update user info
            $user->update([
                'name' => data_get($payload, 'personal.name'),
                'email' => data_get($payload, 'personal.email'),
                'phone' => data_get($payload, 'personal.phone'),
                'status' => 1,
                'total_spent' => $user->total_spent + data_get($payload, 'order.total', 0),
                'loyalty_points' => $user->loyalty_points + 10,
            ]);

            $delivery = data_get($payload, 'delivery');

            // Coordinates: if provided, attempt to store using an appropriate spatial function
            // depending on the DB driver. Postgres (PostGIS) uses ST_GeogFromText; MySQL/MariaDB
            // use ST_GeomFromText('POINT(lng lat)'). If neither is available we skip storing
            // coordinates to avoid SQL errors.
            $lat = data_get($delivery, 'coordinates.lat');
            $lng = data_get($delivery, 'coordinates.lng');
            $coordinatesValue = null;
            if ($lat !== null && $lng !== null) {
                try {
                    $driver = DB::connection()->getPDO()->getAttribute(\PDO::ATTR_DRIVER_NAME);
                    if ($driver === 'pgsql') {
                        $coordinatesValue = DB::raw("ST_GeogFromText('SRID=4326;POINT({$lng} {$lat})')");
                    } else {
                        // Assume MySQL/MariaDB
                        $coordinatesValue = DB::raw("ST_GeomFromText('POINT({$lng} {$lat})')");
                    }
                } catch (\Throwable $e) {
                    Log::warning('Could not build spatial coordinates: '.$e->getMessage());
                    $coordinatesValue = null;
                }
            }

            // Create order. Use ORD-<timestamp> format for tracking number and
            // store postal code as string to preserve leading zeros.
            $order = Order::create([
                'user_id' => $user->id,
                'order_tracking_number' => 'ORD-'.time(),
                'status' => 'pending',
                'shipping_cost' => data_get($payload, 'order.shippingCost'),
                'street_address' => data_get($delivery, 'address'),
                'apartment/suite' => data_get($delivery, 'apartment'),
                'city/town' => data_get($delivery, 'city'),
                'region' => data_get($delivery, 'state'),
                'postal_code' => (string) data_get($delivery, 'postalCode'),
                'country' => data_get($delivery, 'country'),
                'delivery_instructions' => data_get($delivery, 'instructions'),
                'coordinates' => $coordinatesValue,
            ]);

            // Create order items
            $items = data_get($payload, 'order.items', []);

            foreach ($items as $item) {
                $product = Product::find($item['id']);
                $price = $item['price'] ?? ($product?->base_price ?? 0);
                $quantity = $item['quantity'] ?? 1;
                $vat = $product?->vat_rate ?? 0;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_name_snapshot' => $item['name'] ?? $product?->name,
                    'price_at_purchase' => $price,
                    'vat_at_purchase' => $vat,
                    'quantity' => $quantity,
                    'total_price' => $price * $quantity,
                ]);
            }

            // Create payment details
            $payment = data_get($payload, 'payment', []);
            PaymentDetail::create([
                'order_id' => $order->id,
                'payment_method' => data_get($payment, 'type'),
                'payment_status' => 'pending',
                'payment_details' => $payment,
            ]);

            // Remove user's cart
            if ($user->cart) {
                $user->cart->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'order_tracking_number' => $order->order_tracking_number,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Checkout failed: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not process order'], 500);
        }
    }

    /**
     * List orders for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        info($user);

        $orders = Order::with(['items.product.images', 'paymentDetail'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        info($orders);

        $payload = $orders->map(fn($order) => $this->formatOrderForClient($order));

        return response()->json($payload);
    }

    /**
     * Show a single order by tracking number or id.
     */
    public function show(Request $request, $order): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $orderModel = Order::with(['items.product.images', 'paymentDetail', 'user'])
            ->where(function($q) use ($order) {
                $q->where('order_tracking_number', $order)
                  ->orWhere('id', $order);
            })->first();

        if (!$orderModel || $orderModel->user_id !== $user->id) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($this->formatOrderForClient($orderModel));
    }

    protected function formatOrderForClient(Order $order): array
    {
        $statusMap = [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'in transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        $items = $order->items->map(function($it) {
            $image = null;
            if ($it->product && $it->product->images && $it->product->images->count()) {
                $path = $it->product->images->first()->image_path;
                $image = str_starts_with($path, 'http') ? $path : url('storage/'.$path);
            }

            return [
                'id' => $it->id,
                'productId' => $it->product_id,
                'name' => $it->product_name_snapshot,
                'price' => (float) $it->price_at_purchase,
                'quantity' => (int) $it->quantity,
                'image' => $image,
            ];
        })->toArray();

        $subtotal = (float) $order->items->sum(fn($it) => $it->price_at_purchase * $it->quantity);

    // Calculate tax based on vat_at_purchase treated as rate (percentage)
        // $tax = (float) $order->items->sum(function($it) {
        //     $rate = (float) $it->vat_at_purchase;
        //     return ($it->price_at_purchase * $it->quantity) * ($rate / 100);
        // });    

        $deliveryFee = (float) $order->shipping_cost;

        $totalAmount = $subtotal + $deliveryFee;

        // Build delivery address using the column names (note: some have slashes)
        $parts = [];
        if ($order->street_address) $parts[] = $order->street_address;
        if ($order->{'apartment/suite'}) $parts[] = $order->{'apartment/suite'};
        if ($order->{'city/town'}) $parts[] = $order->{'city/town'};
        if ($order->region) $parts[] = $order->region;
        if ($order->postal_code) $parts[] = $order->postal_code;
        if ($order->country) $parts[] = $order->country;

        $deliveryAddress = implode(', ', $parts);

        $paymentMethod = $order->paymentDetail?->payment_method ?? $order->paymentDetail?->payment_details['type'] ?? null;

        return [
            'id' => $order->id,
            'orderDate' => $order->created_at?->toIso8601String(),
            'status' => $statusMap[$order->status] ?? ucfirst($order->status),
            'paymentMethod' => $paymentMethod,
            'items' => $items,
            'subtotal' => $subtotal,
            'deliveryFee' => $deliveryFee,
            'totalAmount' => $totalAmount,
            'deliveryAddress' => $deliveryAddress,
            'estimatedDelivery' => null,
            'deliveredDate' => $order->updated_at && $order->status === 'delivered' ? $order->updated_at->toIso8601String() : null,
            'trackingNumber' => $order->order_tracking_number,
            'createdBy' => [
                'name' => $order->user?->name,
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
            ],
        ];
    }

    public function destroy(Request $request, $orderId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $order = Order::where('id', $orderId)->where('user_id', $user->id)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if (!in_array($order->status, ['pending', 'processing'])) {
            return response()->json(['success' => false, 'message' => 'Only pending or processing orders can be cancelled'], 400);
        }

        $order->delete();

        return response()->json(['success' => true, 'message' => 'Order cancelled']);
    }

    /**
     * Admin: delete an order by id.
     * Route: admin/orders/{orderId}/delete
     */
    public function adminDestroy(Request $request, $orderId): JsonResponse
    {
        $admin = auth()->guard('admin')->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);
        }

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $order->delete();
            return response()->json(['success' => true, 'message' => 'Order deleted']);
        } catch (\Throwable $e) {
            Log::error('Admin order delete failed: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not delete order'], 500);
        }
    }

    /**
     * Admin: update order status.
     * Route: admin/orders/{orderId}/status/update
     */
    public function adminUpdateStatus(Request $request, $orderId): JsonResponse
    {
        $admin = auth()->guard('admin')->user();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated (admin)'], 401);
        }

        $status = $request->input('status');
        if ($status === null) {
            return response()->json(['success' => false, 'message' => 'Status is required'], 422);
        }

        $status = trim(strtolower($status));
        $allowed = ['pending', 'processing', 'in transit', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid status provided'], 400);
        }

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $order->status = $status;
            $order->save();

            return response()->json(['success' => true, 'message' => 'Order status updated', 'order' => $this->formatOrderForClient($order)]);
        } catch (\Throwable $e) {
            Log::error('Admin order status update failed: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not update order status'], 500);
        }
    }

}
