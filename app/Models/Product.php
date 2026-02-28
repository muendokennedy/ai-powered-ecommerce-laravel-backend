<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    //

    protected $fillable = [
        'product_sku_id',
        'name',
        'brand',
        'description',
        'specifications',
        'base_price',
        'discount_price',
        'vat_rate',
        'status',
        'stock_quantity',
        'low_stock_threshold',
        'category_id',
        'supplier_id',
    ];
}
