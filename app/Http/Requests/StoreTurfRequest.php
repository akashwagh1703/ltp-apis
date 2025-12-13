<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTurfRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'owner_id' => 'required|exists:owners,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'sport_type' => 'required|in:cricket,football,badminton,tennis,basketball,volleyball',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|size:6',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'size' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1|max:100',
            'opening_time' => 'required|date_format:H:i',
            'closing_time' => 'required|date_format:H:i|after:opening_time',
            'slot_duration' => 'required|integer|in:30,60,90,120',
            'pricing_type' => 'required|in:uniform,dynamic',
            'uniform_price' => 'required_if:pricing_type,uniform|nullable|numeric|min:0',
            'images' => 'nullable|array',
            'images.*' => 'nullable|file|image|mimes:jpeg,jpg,png,gif|max:5120',
            'amenities' => 'nullable|string',
            'pricing' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'owner_id.required' => 'Owner is required',
            'owner_id.exists' => 'Selected owner does not exist',
            'closing_time.after' => 'Closing time must be after opening time',
            'images.*.image' => 'All files must be images',
            'images.*.mimes' => 'Only JPG, JPEG, PNG, and GIF images are allowed',
            'images.*.max' => 'Image size must not exceed 5MB',
        ];
    }
}
