<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
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

        // Check if the user has an existing cart, if not create one
        $cart = $user->cart()->firstOrCreate(
            ['user_id' => $user->id]
        );

        // Check if the product is already in the cart
        $cartItem = $cart->items()->where('product_id', $product->id)->first();

        if ($cartItem) {
            // Update quantity if product already exists in cart
            $cartItem->quantity += $validated['quantity'];
            $cartItem->save();
        } else {
            // Create a new cart item
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart successfully.',
            'cart' => $cart->load('items.product'),
        ], 200);
    }

    public function cartIte
}
