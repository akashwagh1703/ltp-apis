<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'turf_id' => 'required|exists:turfs,id',
            'turf_slot_id' => 'required|exists:turf_slots,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'player_name' => 'required_if:booking_type,offline|string|max:255',
            'player_phone' => 'required_if:booking_type,offline|string|max:15',
        ];
    }
}
