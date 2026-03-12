<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'supplier' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'specifications' => ['required', 'json'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['required', 'numeric', 'min:0', 'lte:base_price'],
            'vat_rate' => ['required', 'numeric', 'between:0,1'],
            'status' => ['required', 'string', 'in:in stock,out of stock,low stock'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            
            'primary_image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp'],
            'secondary_image' => ['required', 'file','mimes:jpeg,jpg,png,webp'],
            'tertiary_image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp']

        ];
    }
}
