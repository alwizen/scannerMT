<?php

namespace App\Http\Controllers;

use App\Http\Requests\DriverLoginRequest;
use App\Http\Requests\ScanRequest;
use App\Http\Requests\StartScanSessionRequest;
use App\Models\Device;
use App\Models\Driver;
use App\Models\ParkingLocation;
use App\Models\ScanLog;
use App\Models\ScanSession;
use App\Models\Tanker;
use App\Models\TankerCompartment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TankerScanController extends Controller
{
    public function availableTankers(): JsonResponse
    {
        $tankers = Tanker::query()
            ->where('status', 'available')
            ->withCount('compartments')
            ->orderBy('nopol')
            ->get(['id', 'nopol', 'capacity_kl']);

        return response()->json([
            'success' => true,
            'message' => 'Daftar tanker tersedia berhasil diambil',
            'data' => $tankers,
        ]);
    }

    public function startScanSession(StartScanSessionRequest $request): JsonResponse
    {
        $driver = Driver::whereKey($request->driver_id)
            ->where('is_active', true)
            ->first();
        $device = Device::where('device_uuid', $request->device_uuid)
            ->where('is_active', true)
            ->first();
        $tanker = Tanker::whereKey($request->tanker_id)
            ->where('status', 'available')
            ->first();

        if (! $driver || ! $device || ! $tanker) {
            return response()->json([
                'success' => false,
                'message' => 'Driver, device, atau tanker tidak tersedia',
            ], 422);
        }

        $session = ScanSession::create([
            'driver_id' => $driver->id,
            'device_id' => $device->id,
            'tanker_id' => $tanker->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sesi scan berhasil dibuat',
            'data' => [
                'scan_session_id' => $session->id,
                'driver_id' => $session->driver_id,
                'device_id' => $session->device_id,
                'tanker_id' => $session->tanker_id,
                'status' => $session->status,
                'started_at' => $session->started_at->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

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

        if ($compartment->tanker?->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'Tanker tidak tersedia untuk scan',
            ], 422);
        }

        $session = ScanSession::whereKey($request->scan_session_id)
            ->where('driver_id', $driver->id)
            ->where('device_id', $device->id)
            ->where('tanker_id', $compartment->tanker_id)
            ->where('status', 'in_progress')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi scan tidak valid atau sudah selesai',
            ], 422);
        }

        $matchingLocation = null;
        $isInsideGeofence = false;

        if ($request->latitude !== null && $request->longitude !== null) {
            $matchingLocation = ParkingLocation::findMatchingLocation(
                (float) $request->latitude,
                (float) $request->longitude
            );
            $isInsideGeofence = $matchingLocation !== null;
        }

        $scanLog = DB::transaction(function () use ($session, $driver, $device, $compartment, $request, $isInsideGeofence, $matchingLocation) {
            if (ScanLog::where('scan_session_id', $session->id)
                ->where('tanker_compartment_id', $compartment->id)
                ->exists()) {
                abort(409, 'Kompartemen sudah discan dalam sesi ini');
            }

            $scanLog = ScanLog::create([
                'scan_session_id' => $session->id,
                'driver_id' => $driver->id,
                'device_id' => $device->id,
                'tanker_compartment_id' => $compartment->id,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'is_inside_geofence' => $isInsideGeofence,
                'parking_location_id' => $matchingLocation?->id,
                'scanned_at' => now(),
            ]);

            $compartmentCount = $session->tanker->compartments()->count();
            $scannedCount = ScanLog::where('scan_session_id', $session->id)
                ->distinct()
                ->count('tanker_compartment_id');

            if ($compartmentCount > 0 && $scannedCount >= $compartmentCount) {
                $session->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            return $scanLog;
        });

        $tanker = $compartment->tanker;
        $notification = Notification::make()
            ->title('Scan baru berhasil')
            ->body(sprintf(
                '%s melakukan scan MT %s, Kompartemen %s. %s',
                $driver->name,
                $tanker->nopol,
                $compartment->compartment_no,
                $isInsideGeofence ? 'Di dalam lokasi parkir.' : 'Di luar lokasi parkir.'
            ))
            ->status($isInsideGeofence ? 'success' : 'warning');

        User::query()->each(fn (User $user) => $notification->sendToDatabase($user));

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
                'geofence' => [
                    'is_inside' => $isInsideGeofence,
                    'location_id' => $matchingLocation?->id,
                    'location_name' => $matchingLocation?->name,
                    'status_text' => $isInsideGeofence
                        ? 'Di dalam lokasi parkir MT'
                        : 'Di luar lokasi parkir MT',
                ],
            ],
        ]);
    }

    public function scanHistory(Request $request): JsonResponse
    {
        $driverId = $request->query('driver_id');
        if (! $driverId) {
            return response()->json([
                'success' => false,
                'message' => 'driver_id parameter required',
            ], 400);
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));

        $logs = ScanLog::with(['tankerCompartment.tanker', 'parkingLocation'])
            ->where('driver_id', $driverId)
            ->orderBy('scanned_at', 'desc')
            ->paginate($perPage);

        $data = $logs->getCollection()->map(function ($log) {
            $compartment = $log->tankerCompartment;
            $tanker = $compartment?->tanker;

            return [
                'scan_log_id' => $log->id,
                'scanned_at' => $log->scanned_at ? $log->scanned_at->format('Y-m-d H:i:s') : null,
                'tanker' => $tanker ? [
                    'id' => $tanker->id,
                    'nopol' => $tanker->nopol,
                    'capacity_kl' => $tanker->capacity_kl,
                ] : null,
                'compartment' => $compartment ? [
                    'id' => $compartment->id,
                    'compartment_no' => $compartment->compartment_no,
                    'capacity_kl' => $compartment->capacity_kl,
                    'rfid_uid' => $compartment->rfid_uid,
                ] : null,
                'geofence' => [
                    'is_inside' => (bool) $log->is_inside_geofence,
                    'location_id' => $log->parking_location_id,
                    'location_name' => $log->parkingLocation?->name,
                    'status_text' => $log->is_inside_geofence
                        ? 'Di dalam lokasi parkir MT'
                        : 'Di luar lokasi parkir MT',
                ],
                'scan_status' => $log->scan_status,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat scan berhasil diambil',
            'data' => $data->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
