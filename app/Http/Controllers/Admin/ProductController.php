<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;

class ProductController extends Controller
{
    public function store(ProductStoreRequest $request)
    {
        $data = $request->validated();

        return response()->json($data);

        if(isset($data['specifications']) && is_array($data['specifications'])){
            $data['specifications'] = json_encode($data['specifications']);
        }
    }
}
