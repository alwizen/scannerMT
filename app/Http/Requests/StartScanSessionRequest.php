<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartScanSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'device_uuid' => ['required', 'string', 'exists:devices,device_uuid'],
            'tanker_id' => ['required', 'integer', 'exists:tankers,id'],
        ];
    }
}