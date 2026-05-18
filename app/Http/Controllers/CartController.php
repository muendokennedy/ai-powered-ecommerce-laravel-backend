<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Add a new product to cart or update if exists
     */
    public function addProduct(Request $request, Product $product)
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in to add items to cart.',
                'error' => 'unauthenticated'
            ], 401);
        }

        // Validate the request
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:1',
        ]);

        $user = $request->user();
        $cart = $user->cart()->firstOrCreate(
            ['user_id' => $user->id]
        );

        // Check if the product is already in the cart
        $cartItem = $cart->items()->where('product_id', $product->id)->first();

        if ($cartItem) {
            // Check if the submitted quantity is the same as the existing quantity
            if ($cartItem->quantity == $validated['quantity']) {
                return response()->json([
                    'message' => 'Product is already added in the cart with the same quantity.',
                    'error' => 'duplicate_quantity'
                ], 409);
            }
            
            // Update quantity
            $cartItem->quantity = $validated['quantity'];
            $cartItem->save();

            return response()->json([
                'message' => 'Product updated successfully.',
                'cart' => $cart->load('items.product'),
            ], 200);
        } else {
            // Create a new cart item
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
            ]);

            return response()->json([
                'message' => 'Product added to cart successfully.',
                'cart' => $cart->load('items.product'),
            ], 200);
        }
    }

    /**
     * Update cart item quantity - unified method for increment/decrement/direct update
     * action: 'increment', 'decrement', or null for direct update
     * quantity: required - amount to increment/decrement by, or direct quantity when action is null
     */
    public function updateItemQuantity(Request $request, string $cartItemId)
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in to modify cart.',
                'error' => 'unauthenticated'
            ], 401);
        }

        // Validate the request
        $validated = $request->validate([
            'action' => 'nullable|in:increment,decrement',
            'quantity' => 'required|numeric|min:1',
        ]);

        $user = $request->user();
        $cart = $user->cart()->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
                'error' => 'cart_not_found'
            ], 404);
        }

        $cartItem = $cart->items()->find($cartItemId);

        if (!$cartItem) {
            return response()->json([
                'message' => 'Cart item not found.',
                'error' => 'item_not_found'
            ], 404);
        }

        $action = $validated['action'] ?? null;
        $amount = $validated['quantity'];

        // Perform the action based on whether action is increment, decrement, or null
        if ($action === 'increment') {
            $cartItem->quantity += $amount;
            $message = 'Cart item quantity incremented successfully.';
        } elseif ($action === 'decrement') {
            $cartItem->quantity -= $amount;

            // If quantity becomes 0 or negative, remove the item
            if ($cartItem->quantity <= 0) {
                $cartItem->delete();

                return response()->json([
                    'message' => 'Cart item removed (quantity reached zero).',
                    'cart_item_id' => $cartItemId,
                ], 200);
            }

            $message = 'Cart item quantity decremented successfully.';
        } else {
            // Direct update - no action specified, just set the quantity
            $cartItem->quantity = $amount;
            $message = 'Cart item quantity updated successfully.';
        }

        $cartItem->save();

        return response()->json([
            'message' => $message,
            'cart_item_id' => $cartItem->id,
            'new_quantity' => $cartItem->quantity,
        ], 200);
    }

    /**
     * Get all cart items for the authenticated user
     */
    public function cartItems(Request $request)
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in to view cart.',
                'error' => 'unauthenticated'
            ], 401);
        }

        $user = $request->user();

        // Get user's cart with items and products (eager load relationships)
        $cart = $user->cart()->with([
            'items.product' => function ($query) {
                $query->with('images');
            }
        ])->first();

        // If no cart exists, return empty cart
        if (!$cart) {
            return response()->json([
                'message' => 'Cart is empty.',
                'items' => [],
                'cart_total' => 0
            ], 200);
        }

        // Transform cart items to include only required fields
        $items = $cart->items->map(function ($cartItem) {
            $product = $cartItem->product;
            
            // Get the first image or null
            $image = $product->images->first();

            return [
                'cart_item_id' => $cartItem->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'quantity' => $cartItem->quantity,
                'discount_price' => $product->discount_price,
                'image' => $image ? $image->image_path : null,
            ];
        });

        // Calculate total (optional but useful)
        $cartTotal = $items->sum(function ($item) {
            return $item['discount_price'] * $item['quantity'];
        });

        return response()->json([
            'message' => 'Cart items retrieved successfully.',
            'items' => $items,
            'cart_total' => $cartTotal,
            'item_count' => $items->count()
        ], 200);
    }

    /**
     * Remove an item from the cart
     */
    public function removeItem(Request $request, $cartItemId)
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in to remove items from cart.',
                'error' => 'unauthenticated'
            ], 401);
        }

        $user = $request->user();

        // Get the user's cart
        $cart = $user->cart()->first();

        // If no cart exists, return error
        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found.',
                'error' => 'cart_not_found'
            ], 404);
        }

        // Find the cart item and verify it belongs to the user's cart
        $cartItem = $cart->items()->find($cartItemId);

        if (!$cartItem) {
            return response()->json([
                'message' => 'Cart item not found.',
                'error' => 'item_not_found'
            ], 404);
        }

        // Delete the cart item
        $cartItem->delete();

        return response()->json([
            'message' => 'Item removed from cart successfully.',
            'item_id' => $cartItemId,
            'item_count' => $cart->items()->count()
        ], 200);
    }

    /**
     * Get related products based on all items in the cart
     * Considers all categories and brands from cart items
     */
    public function cartRelatedProducts(Request $request)
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in to view related products.',
                'error' => 'unauthenticated'
            ], 401);
        }

        try {
            $user = $request->user();

            // Get user's cart with products
            $cart = $user->cart()->with('items.product')->first();

            // If no cart exists, return empty related products
            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'message' => 'Cart is empty. No related products can be suggested.',
                    'related_products' => []
                ], 200);
            }

            // Extract all categories and brands from cart items
            $cartProducts = $cart->items->map(function ($item) {
                return $item->product;
            });

            $cartCategories = $cartProducts->pluck('category')->unique()->toArray();
            $cartBrands = $cartProducts->pluck('brand')->unique()->toArray();
            $cartProductIds = $cartProducts->pluck('id')->toArray();

            $limit = 12;
            $related = collect();

            // Tier 1: Same category + same brand (from any cart item)
            $tier1 = Product::with('images')
                ->whereNotIn('id', $cartProductIds)
                ->whereIn('category', $cartCategories)
                ->whereIn('brand', $cartBrands)
                ->limit($limit)
                ->get();

            $related = $related->concat($tier1);

            // Tier 2: Same category + different brands
            if ($related->count() < $limit) {
                $needed = $limit - $related->count();
                $tier2 = Product::with('images')
                    ->whereNotIn('id', $cartProductIds)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->whereIn('category', $cartCategories)
                    ->whereNotIn('brand', $cartBrands)
                    ->limit($needed)
                    ->get();
                $related = $related->concat($tier2);
            }

            // Tier 3: Different categories + same brand
            if ($related->count() < $limit) {
                $needed = $limit - $related->count();
                $tier3 = Product::with('images')
                    ->whereNotIn('id', $cartProductIds)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->whereNotIn('category', $cartCategories)
                    ->whereIn('brand', $cartBrands)
                    ->limit($needed)
                    ->get();
                $related = $related->concat($tier3);
            }

            // Tier 4: Different categories + different brands
            if ($related->count() < $limit) {
                $needed = $limit - $related->count();
                $tier4 = Product::with('images')
                    ->whereNotIn('id', $cartProductIds)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->whereNotIn('category', $cartCategories)
                    ->whereNotIn('brand', $cartBrands)
                    ->limit($needed)
                    ->get();
                $related = $related->concat($tier4);
            }

            // Apply scoring based on multiple cart items
            $related = $related->map(function ($item) use ($cartProducts, $cartCategories, $cartBrands) {
                $score = 0;

                $categoryPriorityMap = [
                    'phones' => [
                        'phones' => 50,
                        'laptops' => 40,
                        'televisions' => 30,
                        'smartwatches' => 20,
                    ],
                    'laptops' => [
                        'laptops' => 50,
                        'phones' => 40,
                        'televisions' => 30,
                        'smartwatches' => 20,
                    ],
                    'televisions' => [
                        'televisions' => 50,
                        'laptops' => 40,
                        'phones' => 30,
                        'smartwatches' => 20,
                    ],
                    'smartwatches' => [
                        'smartwatches' => 50,
                        'phones' => 40,
                        'laptops' => 30,
                        'televisions' => 20,
                    ]
                ];

                $itemCategory = strtolower($item->category);

                // Score based on all cart items
                foreach ($cartProducts as $cartProduct) {
                    $currentCategory = strtolower($cartProduct->category);
                    
                    // Add category priority score
                    $score += $categoryPriorityMap[$currentCategory][$itemCategory] ?? 0;

                    // Add brand match bonus
                    if ($item->brand === $cartProduct->brand) {
                        $score += 10;
                    }
                }

                $item->similarity_score = $score;
                return $item;
            })->sortByDesc('similarity_score')->values();

            return response()->json([
                'message' => 'Related products retrieved successfully.',
                'related_products' => $related,
                'total_related' => $related->count()
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
