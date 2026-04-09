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
}
