<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email'],
            'phone'          => ['required', 'string', 'max:30'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'band_size'      => ['required', 'in:S,M,L'],
            'quantity'       => ['required', 'integer', 'min:1', 'max:10'],
            'plan'           => ['required', 'in:band_only,band_care_plan'],
        ];
    }
}
