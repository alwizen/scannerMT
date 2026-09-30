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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function validateCompartment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rfid_uid' => ['required', 'string'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'device_uuid' => ['required', 'string', 'exists:devices,device_uuid'],
        ]);

        $compartment = TankerCompartment::with('tanker')
            ->where('rfid_uid', $validated['rfid_uid'])
            ->first();

        if (! $compartment) {
            return response()->json([
                'success' => false,
                'message' => 'RFID/QR Kompartemen tidak terdaftar',
            ], 404);
        }

        if ($compartment->tanker?->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'Tanker kompartemen tidak tersedia untuk scan',
            ], 422);
        }

        $device = Device::where('device_uuid', $validated['device_uuid'])->firstOrFail();
        $activeSession = ScanSession::query()
            ->where('driver_id', $validated['driver_id'])
            ->where('device_id', $device->id)
            ->where('tanker_id', $compartment->tanker_id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if ($activeSession && ScanLog::where('scan_session_id', $activeSession->id)
            ->where('tanker_compartment_id', $compartment->id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kompartemen ini sudah dilakukan pemeriksaan pada sesi aktif',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kompartemen terdaftar',
            'data' => [
                'id' => $compartment->id,
                'compartment_no' => $compartment->compartment_no,
                'capacity_kl' => $compartment->capacity_kl,
                'rfid_uid' => $compartment->rfid_uid,
                'tanker' => [
                    'id' => $compartment->tanker?->id,
                    'nopol' => $compartment->tanker?->nopol,
                    'capacity_kl' => $compartment->tanker?->capacity_kl,
                ],
            ],
        ]);
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

        $matchingLocation = null;
        $isInsideGeofence = false;

        if ($request->latitude !== null && $request->longitude !== null) {
            $matchingLocation = ParkingLocation::findMatchingLocation(
                (float) $request->latitude,
                (float) $request->longitude
            );
            $isInsideGeofence = $matchingLocation !== null;
        }

        $scanLog = DB::transaction(function () use ($driver, $device, $compartment, $request, $isInsideGeofence, $matchingLocation) {
            $session = ScanSession::query()
                ->where('driver_id', $driver->id)
                ->where('device_id', $device->id)
                ->where('tanker_id', $compartment->tanker_id)
                ->where('status', 'in_progress')
                ->latest('id')
                ->first();

            if ($request->filled('scan_session_id')) {
                $requestedSession = ScanSession::whereKey($request->scan_session_id)
                    ->where('driver_id', $driver->id)
                    ->where('device_id', $device->id)
                    ->where('tanker_id', $compartment->tanker_id)
                    ->where('status', 'in_progress')
                    ->first();

                if ($requestedSession) {
                    $session = $requestedSession;
                }
            }

            if (! $session) {
                $recentlyCompletedSession = ScanSession::query()
                    ->where('driver_id', $driver->id)
                    ->where('device_id', $device->id)
                    ->where('tanker_id', $compartment->tanker_id)
                    ->where('status', 'completed')
                    ->where('completed_at', '>=', now()->subMinutes(5))
                    ->latest('completed_at')
                    ->first();

                if ($recentlyCompletedSession) {
                    abort(409, 'Sesi scan baru dapat dimulai setelah jeda 5 menit');
                }

                $session = ScanSession::create([
                    'driver_id' => $driver->id,
                    'device_id' => $device->id,
                    'tanker_id' => $compartment->tanker_id,
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]);
            }

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
                'content_status' => $request->content_status,
                'note' => $request->note,
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
                '%s melakukan scan MT %s, Kompartemen %s.',
                $driver->name,
                $tanker->nopol,
                $compartment->compartment_no,
            ))
            ->status($isInsideGeofence ? 'success' : 'warning');

        User::query()->each(fn (User $user) => $notification->sendToDatabase($user));

        return response()->json([
            'success' => true,
            'message' => 'Scan berhasil disimpan',
            'data' => [
                'scan_log_id' => $scanLog->id,
                'scan_session_id' => $scanLog->scan_session_id,
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
                'content_status' => $scanLog->content_status,
                'note' => $scanLog->note,
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

    public function scanLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'nopol' => ['nullable', 'string', 'max:30'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = max(1, min((int) ($validated['per_page'] ?? 15), 100));

        $query = ScanLog::query()
            ->join('tanker_compartments', 'scan_logs.tanker_compartment_id', '=', 'tanker_compartments.id')
            ->join('tankers', 'tanker_compartments.tanker_id', '=', 'tankers.id')
            ->join('drivers', 'scan_logs.driver_id', '=', 'drivers.id')
            ->select([
                DB::raw('DATE(scan_logs.scanned_at) as tanggal'),
                'drivers.name as nama_amt',
                'drivers.role as jabatan',
                'tankers.nopol as nopol',
                'tankers.capacity_kl as kapasitas',
                'scan_logs.driver_id',
                'scan_logs.scan_session_id',
                'tanker_compartments.tanker_id',
                DB::raw('MAX(scan_logs.scanned_at) as last_update'),
                DB::raw('COUNT(DISTINCT scan_logs.tanker_compartment_id) as scanned_compartments'),
                DB::raw('(SELECT COUNT(*) FROM tanker_compartments tc WHERE tc.tanker_id = tanker_compartments.tanker_id AND tc.deleted_at IS NULL) as total_compartments'),
            ])
            ->groupBy([
                'scan_logs.driver_id',
                'scan_logs.scan_session_id',
                'tanker_compartments.tanker_id',
                'drivers.name',
                'drivers.role',
                'tankers.nopol',
                'tankers.capacity_kl',
                DB::raw('DATE(scan_logs.scanned_at)'),
            ]);

        if ($validated['date'] ?? null) {
            $query->whereDate('scan_logs.scanned_at', $validated['date']);
        }

        if ($validated['from'] ?? null) {
            $query->whereDate('scan_logs.scanned_at', '>=', $validated['from']);
        }

        if ($validated['until'] ?? null) {
            $query->whereDate('scan_logs.scanned_at', '<=', $validated['until']);
        }

        if ($validated['driver_id'] ?? null) {
            $query->where('scan_logs.driver_id', $validated['driver_id']);
        }

        if ($validated['nopol'] ?? null) {
            $query->where('tankers.nopol', 'like', '%'.$validated['nopol'].'%');
        }

        $logs = $query->orderByDesc('last_update')->paginate($perPage);

        $data = $logs->getCollection()->map(function ($row) {
            $total = (int) $row->total_compartments;
            $scanned = (int) $row->scanned_compartments;
            $isComplete = $total > 0 && $scanned >= $total;

            return [
                'tanggal' => $row->tanggal,
                'nama_amt' => $row->nama_amt,
                'nopol' => $row->nopol,
                'kapasitas' => $row->kapasitas !== null ? (int) $row->kapasitas : null,
                'jabatan' => match ($row->jabatan) {
                    'driver' => 'AMT 1',
                    'helper' => 'AMT 2',
                    default => $row->jabatan,
                },
                'status' => $isComplete ? 'done' : 'kurang',
                'status_text' => $isComplete ? 'Complete' : 'Belum Lengkap',
                'driver_id' => $row->driver_id,
                'scan_session_id' => $row->scan_session_id,
                'tanker_id' => $row->tanker_id,
                'scanned_compartments' => $scanned,
                'total_compartments' => $total,
                'last_update' => $row->last_update
                    ? Carbon::parse($row->last_update)->format('Y-m-d H:i:s')
                    : null,
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
                'scan_session_id' => $log->scan_session_id,
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
                'content_status' => $log->content_status,
                'note' => $log->note,
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
