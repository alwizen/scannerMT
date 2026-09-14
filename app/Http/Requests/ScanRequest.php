<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scan_session_id' => ['nullable', 'integer', 'exists:scan_sessions,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'device_uuid' => ['required', 'string', 'exists:devices,device_uuid'],
            'rfid_uid' => ['required', 'string'],
            'content_status' => ['nullable', 'string', 'in:kosong,air,sisa_minyak,lainnya'],
            'note' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
