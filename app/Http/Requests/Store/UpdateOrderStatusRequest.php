<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status'          => ['required', 'in:paid,processing,shipped,delivered,cancelled'],
            'tracking_number' => ['required_if:status,shipped', 'nullable', 'string', 'max:100'],
            'courier'         => ['required_if:status,shipped', 'nullable', 'string', 'max:100'],
        ];
    }
}
