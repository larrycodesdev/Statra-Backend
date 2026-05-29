<?php

namespace App\Http\Requests\CheckIn;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'name'     => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6'],
            'email'    => ['nullable', 'email', 'unique:users,email'],
        ];
    }
}
