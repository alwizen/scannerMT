<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_no' => ['required', 'string'],
        ];
    }
}
