<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Driver;
use App\Models\ScanLog;
use App\Models\ScanSession;
use App\Models\Tanker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RitaseHistorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed riwayat ritase (scan session) selama 4 bulan ke belakang.
     * Hanya memakai master data tanker yang kompartemennya sudah punya NFC (rfid_uid).
     *
     * Aturan per hari:
     * - Setiap tanker dengan NFC punya ritase sendiri (independen)
     * - Jumlah ritase per tanker per hari: 2-5 (acak)
     * - Contoh 1 hari (4 tanker NFC):
     *     B9023SFV = 5, G1926DE = 4, E9260YC = 2, E9251YC = 3 → total 14 ritase
     * - Semua isi kompartemen: kosong
     * - Device: UNIWA saja
     *
     * Usage:
     *   php artisan db:seed --class=RitaseHistorySeeder
     *
     * Options via env:
     *   RITASE_MONTHS=4
     *   RITASE_MIN_PER_TANKER=2
     *   RITASE_MAX_PER_TANKER=5
     *   RITASE_DRIVER_IDS=6,10,20,25,28,30
     *   RITASE_DEVICE_NAMES=UNIWA
     */
    public function run(): void
    {
        $months = max(1, (int) env('RITASE_MONTHS', 4));
        $minPerTanker = max(1, (int) env('RITASE_MIN_PER_TANKER', 2));
        $maxPerTanker = max($minPerTanker, (int) env('RITASE_MAX_PER_TANKER', 5));

        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subMonths($months)->startOfDay();

        $allowedDriverIds = collect(explode(',', (string) env('RITASE_DRIVER_IDS', '6,10,20,25,28,30')))
            ->map(fn($id) => (int) trim($id))
            ->filter()
            ->values();

        $drivers = Driver::where('is_active', true)
            ->whereIn('id', $allowedDriverIds)
            ->get(['id', 'role']);

        $allowedDeviceNames = collect(explode(',', (string) env('RITASE_DEVICE_NAMES', 'UNIWA')))
            ->map(fn($name) => trim($name))
            ->filter()
            ->values();

        $devices = Device::where('is_active', true)
            ->where(function ($query) use ($allowedDeviceNames) {
                $query->where(function ($q) use ($allowedDeviceNames) {
                    foreach ($allowedDeviceNames as $name) {
                        $q->orWhere('name', 'like', '%' . $name . '%');
                    }
                });
            })
            ->pluck('id');

        if ($drivers->isEmpty() || $devices->isEmpty()) {
            $this->command->warn('Driver atau device aktif tidak ditemukan. Seeder dihentikan.');

            return;
        }

        $missingDrivers = $allowedDriverIds->diff($drivers->pluck('id'))->values();
        if ($missingDrivers->isNotEmpty()) {
            $this->command->warn('Driver ID tidak ditemukan/tidak aktif: ' . $missingDrivers->implode(', '));
        }

        $tankers = Tanker::query()
            ->whereHas('compartments', function ($query) {
                $query->whereNotNull('rfid_uid')->where('rfid_uid', '!=', '');
            })
            ->with(['compartments' => function ($query) {
                $query->whereNotNull('rfid_uid')->where('rfid_uid', '!=', '')
                    ->orderBy('compartment_no');
            }])
            ->get();

        if ($tankers->isEmpty()) {
            $this->command->warn('Tidak ada tanker dengan kompartemen NFC (rfid_uid). Seeder dihentikan.');

            return;
        }

        $this->command->info("Periode: {$startDate->toDateString()} s.d. {$endDate->toDateString()}");
        $this->command->info("Ritase per tanker per hari: {$minPerTanker}-{$maxPerTanker} (independen)");
        $this->command->info('Estimasi ritase/hari: ' . ($tankers->count() * $minPerTanker) . ' - ' . ($tankers->count() * $maxPerTanker));
        $this->command->info('Tanker dengan NFC: ' . $tankers->pluck('nopol')->implode(', '));
        $this->command->info('Driver aktif: ' . $drivers->count() . ', Device (UNIWA): ' . $devices->count());
        $this->command->info('Isi kompartemen: semua kosong');

        $this->purgeRange($startDate, $endDate);

        $driverWeights = $drivers->mapWithKeys(fn(Driver $driver) => [
            $driver->id => $driver->role === 'driver' ? 3 : 1,
        ])->all();
        $driverPool = [];
        foreach ($driverWeights as $driverId => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $driverPool[] = $driverId;
            }
        }

        $deviceIds = $devices->all();
        $tankerList = $tankers->values()->all();

        $sessionRows = [];
        $sessionCount = 0;
        $logCount = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Tiap tanker NFC punya ritase sendiri per hari
            foreach ($tankerList as $tanker) {
                $compartments = $tanker->compartments;

                if ($compartments->isEmpty()) {
                    continue;
                }

                $ritaseCount = random_int($minPerTanker, $maxPerTanker);
                $timeSlots = $this->timeSlotsForTankerDay($ritaseCount);

                foreach ($timeSlots as $time) {
                    $driverId = $driverPool[random_int(0, count($driverPool) - 1)];
                    $deviceId = $deviceIds[random_int(0, count($deviceIds) - 1)];

                    $startedAt = $date->copy()->setTimeFromTimeString($time);
                    $completedAt = $startedAt->copy()->addMinutes(random_int(20, 120));

                    if ($completedAt->gt($date->copy()->endOfDay())) {
                        $completedAt = $date->copy()->endOfDay()->subMinutes(random_int(5, 30));
                    }

                    // Lokasi scan tetap (titik operasional)
                    $lat = -6.871580780480517;
                    $lng = 109.18625159967219;
                    $isInsideGeofence = random_int(1, 10) <= 7;

                    $sessionRows[] = [
                        'driver_id' => $driverId,
                        'device_id' => $deviceId,
                        'tanker_id' => $tanker->id,
                        'status' => 'completed',
                        'started_at' => $startedAt->toDateTimeString(),
                        'completed_at' => $completedAt->toDateTimeString(),
                        'created_at' => $completedAt,
                        'updated_at' => $completedAt,
                    ];

                    $pendingLogs = [];

                    $scanCursor = $startedAt->copy()->addMinutes(random_int(1, 5));
                    foreach ($compartments as $compIndex => $compartment) {
                        $scanAt = $scanCursor->copy()->addMinutes(random_int(2, 15) + ($compIndex * random_int(2, 8)));
                        if ($scanAt->gt($completedAt)) {
                            $scanAt = $completedAt->copy()->subMinutes(random_int(0, 5));
                        }

                        $pendingLogs[] = [
                            'driver_id' => $driverId,
                            'device_id' => $deviceId,
                            'tanker_compartment_id' => $compartment->id,
                            'content_status' => 'kosong',
                            'note' => null,
                            'latitude' => $lat,
                            'longitude' => $lng,
                            'is_inside_geofence' => $isInsideGeofence,
                            'parking_location_id' => null,
                            'scanned_at' => $scanAt->toDateTimeString(),
                            'created_at' => $scanAt,
                            'updated_at' => $scanAt,
                        ];

                        $scanCursor = $scanAt->copy()->addMinutes(random_int(1, 4));
                    }

                    $sessionRows[array_key_last($sessionRows)]['_logs'] = $pendingLogs;
                    $sessionCount++;
                }
            }
        }

        if ($sessionCount === 0) {
            $this->command->warn('Tidak ada data ritase yang dibuat.');

            return;
        }

        DB::transaction(function () use (&$sessionRows, &$logCount) {
            foreach ($sessionRows as $sessionRow) {
                $logs = $sessionRow['_logs'] ?? [];
                unset($sessionRow['_logs']);

                $sessionId = DB::table('scan_sessions')->insertGetId($sessionRow);

                foreach ($logs as $log) {
                    $log['scan_session_id'] = $sessionId;
                    DB::table('scan_logs')->insert($log);
                    $logCount++;
                }
            }
        });

        $this->command->info("Selesai. Ritase dibuat: {$sessionCount}, Scan log dibuat: {$logCount}.");
    }

    /**
     * Hapus scan_sessions & scan_logs pada rentang tanggal tertentu.
     */
    protected function purgeRange(Carbon $startDate, Carbon $endDate): void
    {
        $sessionIds = ScanSession::query()
            ->whereBetween('started_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return;
        }

        ScanLog::whereIn('scan_session_id', $sessionIds)->delete();
        ScanSession::whereIn('id', $sessionIds)->delete();

        $this->command->info('Data ritase lama pada rentang tersebut dihapus: ' . $sessionIds->count() . ' session.');
    }

    /**
     * Buat slot jam unik untuk ritase 1 tanker dalam 1 hari.
     * Sebar pagi & sore agar realistis.
     *
     * @return array<int, string> daftar jam (H:i), terurut
     */
    protected function timeSlotsForTankerDay(int $ritaseCount): array
    {
        // Range jam operasional: 05:00 - 17:00
        $allSlots = [
            '05:00',
            '05:30',
            '06:00',
            '06:30',
            '07:00',
            '07:30',
            '08:00',
            '08:30',
            '09:00',
            '09:30',
            '10:00',
            '13:00',
            '13:30',
            '14:00',
            '14:30',
            '15:00',
            '15:30',
            '16:00',
            '16:30',
        ];

        // Acak urutan lalu ambil sejumlah ritaseCount, lalu sort
        shuffle($allSlots);
        $slots = array_slice($allSlots, 0, $ritaseCount);
        sort($slots);

        return $slots;
    }
}
