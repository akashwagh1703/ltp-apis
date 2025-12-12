<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTurfRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:1000',
            'sport_type' => 'sometimes|required|in:cricket,football,badminton,tennis,basketball,volleyball',
            'address_line1' => 'sometimes|required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'state' => 'sometimes|required|string|max:100',
            'pincode' => 'sometimes|required|string|size:6',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'size' => 'sometimes|required|string|max:50',
            'capacity' => 'sometimes|required|integer|min:1|max:100',
            'opening_time' => 'sometimes|required|date_format:H:i',
            'closing_time' => 'sometimes|required|date_format:H:i|after:opening_time',
            'slot_duration' => 'sometimes|required|integer|in:30,60,90,120',
            'pricing_type' => 'sometimes|required|in:uniform,dynamic',
            'uniform_price' => 'required_if:pricing_type,uniform|nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,approved,suspended',
            'images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'amenities' => 'nullable|string',
            'pricing' => 'nullable|string',
        ];
    }
}
