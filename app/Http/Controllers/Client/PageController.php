<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;

class PageController extends Controller
{
    //

    public function ProductsPage()
    {
        $products = Product::all();

      return response()->json([
        'products' => $products
      ]); 
    }
}
