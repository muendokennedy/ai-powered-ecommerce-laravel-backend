<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;

class PagesController extends Controller
{
    //

    public function ProductsPage()
    {
        $products = Product::with('images')->get();

      return response()->json([
        'products' => $products
      ]); 
    }
}
