<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'personal.name' => ['required','string','max:255'],
            'personal.email' => ['required','email','max:255'],
            'personal.phone' => ['required','string','max:50'],

            'delivery.address' => ['required','string','max:500'],
            'delivery.apartment' => ['nullable','string','max:255'],
            'delivery.landmark' => ['nullable','string','max:255'],
            'delivery.city' => ['required','string','max:255'],
            'delivery.state' => ['required','string','max:255'],
            'delivery.postalCode' => ['required'],
            'delivery.country' => ['required','string','max:255'],
            'delivery.instructions' => ['nullable','string'],
            'delivery.coordinates.lat' => ['nullable','numeric'],
            'delivery.coordinates.lng' => ['nullable','numeric'],

            'order.items' => ['required','array','min:1'],
            'order.items.*.id' => ['required','integer','exists:products,id'],
            'order.items.*.name' => ['sometimes','string'],
            'order.items.*.price' => ['required','numeric'],
            'order.items.*.quantity' => ['required','integer','min:1'],
            'order.subtotal' => ['required','numeric'],
            'order.shippingCost' => ['required','numeric'],
            'order.tax' => ['nullable','numeric'],
            'order.total' => ['required','numeric'],

            'payment.type' => ['required','string'],
            'payment.recipientName' => ['nullable','string','max:255'],
        ];
    }
}
