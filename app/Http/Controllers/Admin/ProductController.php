<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected array $imageFields = [
        'primary' => 'primary_image',
        'secondary' => 'secondary_image',
        'tertiary' => 'tertiary_image',
    ];

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

    protected function storeImages($request): array
    {
        $rows = [];
        $imagesFields = $this->imageFields;

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

    public function update(ProductUpdateRequest $request, Product $product)
    {
        $validated = $request->validated();

        $productData = collect($validated)->except([
            'primary_image',
            'secondary_image',
            'tertiary_image'
        ])->toArray();

        DB::transaction(function() use ($request, $product, $productData){

            $product->update($productData);
            $this->syncUpdatedImages($request, $product);

        });

        return response()->json([
            'message' => 'product updated successfully',
            'data' => $product->fresh('images')
        ]);
    }

    protected function syncUpdatedImages(ProductUpdateRequest $request, Product $product)
    {
        foreach($this->imageFields as $type => $field){
            if(!$request->hasFile($field) || !$request->file($field)->isValid()){
                continue;
            }

            $existingImages = $product->images()->where('image_type', $type)->get();

            foreach($existingImages as $existingImage){
                Storage::disk('public')->delete($existingImage->image_path);
                $existingImage->delete();
            }

            $path = $request->file($field)->store('products', 'public');

            $product->images()->create([
                'image_type' => $type,
                'image_path' => $path
            ]);
        }
    }
    public function destroy(Product $product)
    {
        DB::transaction(function () use ($product) {
            $images = $product->images()->get();

            foreach ($images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            $product->images()->delete();

            $product->delete();
        });

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }
}
