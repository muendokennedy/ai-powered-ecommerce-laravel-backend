<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function store(ProductStoreRequest $request)
    {
        $validated = $request->validated();

        $productData = collect($validated)->except([
            'primary_image',
            'secondary_image',
            'tertiary_image'
        ])->toArray();

        $productData['product_sku_id'] = $this->generateProductSku();

        
        $images = $this->storeImages($request);

        $product = DB::transaction(function() use ($productData, $images) {
            $product = Product::create($productData);

            if (!empty($images)) {
                $imagesWithProduct = array_map(function (array $image) use ($product) {
                    $image['product_id'] = $product->id;
                    return $image;
                }, $images);

                $product->images()->createMany($imagesWithProduct);
            }

            return $product;
        });

        return response()->json([
            'message' => 'Product added successfully',
            'data' => $product
        ]);
    }

    protected function generateProductSku(): string
    {
        do {
            $sku = 'PRD-' . Str::upper(Str::random(8));
        } while (Product::where('product_sku_id', $sku)->exists());

        return $sku;
    }

    protected function storeImages(ProductStoreRequest $request)
    {
        $rows = [];
        $imagesFields = [
            'primary' => 'primary_image', 
            'secondary' => 'secondary_image', 
            'tertiary' => 'tertiary_image'
            ];

        foreach($imagesFields as $type => $field){
            if($request->hasFile($field) && $request->file($field)->isValid()){
                $path = $request->file($field)->store('products', 'public');

                $rows[] = [
                    'image_type' => $type,
                    'image_path' => $path
                ];
            }
        }

        return $rows;
    }
}
