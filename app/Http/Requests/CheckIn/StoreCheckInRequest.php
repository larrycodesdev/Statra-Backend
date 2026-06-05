<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'genotype'   => ['required', 'in:SS,SC,SB+,SB0,Unknown'],
            'meds'       => ['required', 'in:Yes,No,Missed'],
            'pain'       => ['required', 'integer', 'min:0', 'max:10'],
            'fatigue'    => ['required', 'in:Low,Medium,High'],
            'sleep'      => ['required', 'in:Good,Okay,Poor'],
            'hydration'  => ['required', 'in:Good,Okay,Low'],
            'condition'  => ['required', 'string'],
            'safety'     => ['required', 'string'],
            'notes'      => ['nullable', 'string', 'max:1000'],
            'symptoms'   => ['nullable', 'array'],
            'symptoms.*' => ['string'],
            'flags'      => ['nullable', 'array'],
            'flags.*'    => ['string'],
            'triggers'   => ['nullable', 'array'],
            'triggers.*' => ['string'],
        ];
    }
}
