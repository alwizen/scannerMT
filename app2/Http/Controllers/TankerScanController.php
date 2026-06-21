<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverLoginRequest;
use App\Http\Requests\ScanRequest;
use App\Models\Device;
use App\Models\Driver;
use App\Models\ScanLog;
use App\Models\TankerCompartment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class TankerScanController extends Controller
{
    public function driverLogin(DriverLoginRequest $request): JsonResponse
    {
        $driver = Driver::where('driver_no', $request->driver_no)
            ->where('is_active', true)
            ->first();

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Driver ditemukan',
            'data' => [
                'id' => $driver->id,
                'driver_no' => $driver->driver_no,
                'name' => $driver->name,
                'role' => $driver->role,
            ],
        ]);
    }

    public function scan(ScanRequest $request): JsonResponse
    {
        $driver = Driver::where('id', $request->driver_id)
            ->where('is_active', true)
            ->first();

        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver tidak ditemukan atau tidak aktif',
            ], 404);
        }

        $device = Device::where('device_uuid', $request->device_uuid)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device tidak ditemukan atau tidak aktif',
            ], 404);
        }

        $compartment = TankerCompartment::where('rfid_uid', $request->rfid_uid)->first();

        if (! $compartment) {
            return response()->json([
                'success' => false,
                'message' => 'Kompartemen tidak ditemukan untuk RFID/NFC UID tersebut',
            ], 404);
        }

        $scanLog = DB::transaction(function () use ($driver, $device, $compartment, $request) {
            return ScanLog::create([
                'driver_id' => $driver->id,
                'device_id' => $device->id,
                'tanker_compartment_id' => $compartment->id,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'scanned_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Scan berhasil disimpan',
            'data' => [
                'scan_log_id' => $scanLog->id,
                'scanned_at' => $scanLog->scanned_at->format('Y-m-d H:i:s'),
                'driver' => [
                    'id' => $driver->id,
                    'driver_no' => $driver->driver_no,
                    'name' => $driver->name,
                    'role' => $driver->role,
                ],
                'device' => [
                    'id' => $device->id,
                    'device_uuid' => $device->device_uuid,
                    'name' => $device->name,
                ],
                'tanker' => [
                    'id' => $compartment->tanker->id,
                    'nopol' => $compartment->tanker->nopol,
                    'capacity_kl' => $compartment->tanker->capacity_kl,
                ],
                'compartment' => [
                    'id' => $compartment->id,
                    'compartment_no' => $compartment->compartment_no,
                    'capacity_kl' => $compartment->capacity_kl,
                    'rfid_uid' => $compartment->rfid_uid,
                ],
            ],
        ]);
    }
}
