<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    //

    protected $fillable = [
        'primary_image_url', 
        'second_image_url', 
        'third_image_url',
        'product_id'
        ];
}
