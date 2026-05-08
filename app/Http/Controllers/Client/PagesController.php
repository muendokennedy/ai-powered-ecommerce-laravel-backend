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

        $relatedProducts = $this->getRelatedProducts($product);

        return response()->json([
        'product' => $product,
        'related_products' => $relatedProducts
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

    protected function getRelatedProducts(Product $product)
    {
      $limit = 8;

      $specs = is_array($product->specifications) ? $product->specifications : json_decode($product->specifications, true);

      $related = collect();

      // Tier 1 same category + same brand

      $tier1 = Product::with('images')->where('id', '!=', $product->id)->where('category', $product->category)->where('brand', $product->brand)->limit($limit)->get();

      $related = $related->concat($tier1);

      // Tier 2 same category other brands

      if($related->count() < $limit){
        $needed = $limit - $related->count();
        $tier2 = Product::with('images')->where('id', '!=', $product->id)->whereNotIn('id', $related->pluck('id'))->where('category', $product->category)->where('brand', '!=', $product->brand)->limit($needed)->get();
        $related = $related->concat($tier2);
      }

      // Tier 3 same brand other categories
      if($related->count() < $limit){
        $needed = $limit - $related->count();
        $tier3 = Product::with('images')->where('id', '!=', $product->id)->whereNotIn('id', $related->pluck('id'))->where('category', '!=', $product->category)->where('brand', $product->brand)->limit($needed)->get();
        $related = $related->concat($tier3);
      }

      // Tier 4 Other categories and other brands
      if($related->count() < $limit){
        $needed = $limit - $related->count();
        $tier4 = Product::with('images')->where('id', '!=', $product->id)->whereNotIn('id', $related->pluck('id'))->where('category', '!=', $product->category)->where('brand', '!=', $product->brand)->limit($needed)->get();
        $related = $related->concat($tier4);
      }

      // Final scoring

      $related = $related->map(function($item) use ($product, $specs) {
        $score = 0;
        $itemSpecs = is_array($item->specifications) ? $item->specifications : json_decode($item->specifications, true);

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

        $currentCategory = strtolower($product->category);
        $itemCategory = strtolower($item->category);

        $score += $categoryPriorityMap[$currentCategory][$itemCategory] ?? 0;

        if($item->brand === $product->brand){
          $score += 15;
        }
        if(($itemSpecs['RAM'] ?? null) === ($specs['RAM'] ?? null)){
          $score += 10;
        }
        if(($itemSpecs['Storage'] ?? null) === ($specs['Storage'] ?? null)){
          $score += 10;
        }
        if(($itemSpecs['Operating System'] ?? null) === ($specs['Operating System'] ?? null)){
          $score += 10;
        }
        if(($itemSpecs['Display Size'] ?? null) === ($specs['Display Size'] ?? null)){
          $score += 10;
        }
        $item->similarity_score = $score;

        return $item;
      })->sortByDesc('similarity_score')->values();

      return $related;
    }
}
