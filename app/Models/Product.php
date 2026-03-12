<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    //

    protected $fillable = [
        'product_sku_id',
        'name',
        'brand',
        'supplier',
        'category',
        'description',
        'specifications',
        'base_price',
        'discount_price',
        'vat_rate',
        'status',
        'stock_quantity',
        'low_stock_threshold',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
