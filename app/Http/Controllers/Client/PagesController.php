<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    //
    public function HomePage()
    {
      $topSales = Product::with('images')->get();
 
      $newArrivals = Product::with('images')->latest()->get();

      return response()->json([
        'topSales' => $topSales,
        'newArrivals' => $newArrivals
      ]);

    }
    public function ProductsPage()
    {
        $products = Product::with('images')->get();

      return response()->json([
        'products' => $products
      ]); 
    }

    public function ProductPage(Request $request, string $productId)
    {
      try{
        $product = Product::with('images')->where('id', $productId)->firstOrFail();
        return response()->json([
        'product' => $product
      ]);
      } catch (ModelNotFoundException $e){
        return response()->json([
          'message' => 'Product not found'
        ], 404);
      } catch (\Throwable $e){
        return response()->json([
          'error' => $e->getMessage()
        ], 500);
      }
    }
}
