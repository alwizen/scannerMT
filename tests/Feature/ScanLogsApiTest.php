<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Driver;
use App\Models\ScanLog;
use App\Models\ScanSession;
use App\Models\Tanker;
use App\Models\TankerCompartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanLogsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanlogs_api_returns_summary_fields(): void
    {
        $driver = Driver::create([
            'driver_no' => 'DRV-100',
            'name' => 'Budi Santoso',
            'role' => 'driver',
            'is_active' => true,
        ]);

        $helper = Driver::create([
            'driver_no' => 'HEL-100',
            'name' => 'Andi Wijaya',
            'role' => 'helper',
            'is_active' => true,
        ]);

        $device = Device::create([
            'device_uuid' => 'DEV-SCANLOGS-001',
            'name' => 'Scanner Terminal',
            'is_active' => true,
        ]);

        $tanker = Tanker::create([
            'nopol' => 'B 1234 KT',
            'capacity_kl' => 24,
            'status' => 'available',
        ]);

        $compartment1 = TankerCompartment::create([
            'tanker_id' => $tanker->id,
            'compartment_no' => 1,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-SCANLOGS-001',
        ]);

        $compartment2 = TankerCompartment::create([
            'tanker_id' => $tanker->id,
            'compartment_no' => 2,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-SCANLOGS-002',
        ]);

        $session = ScanSession::create([
            'driver_id' => $driver->id,
            'device_id' => $device->id,
            'tanker_id' => $tanker->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $helperSession = ScanSession::create([
            'driver_id' => $helper->id,
            'device_id' => $device->id,
            'tanker_id' => $tanker->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        ScanLog::create([
            'scan_session_id' => $session->id,
            'driver_id' => $driver->id,
            'device_id' => $device->id,
            'tanker_compartment_id' => $compartment1->id,
            'scanned_at' => now(),
        ]);

        ScanLog::create([
            'scan_session_id' => $session->id,
            'driver_id' => $driver->id,
            'device_id' => $device->id,
            'tanker_compartment_id' => $compartment2->id,
            'scanned_at' => now(),
        ]);

        ScanLog::create([
            'scan_session_id' => $helperSession->id,
            'driver_id' => $helper->id,
            'device_id' => $device->id,
            'tanker_compartment_id' => $compartment1->id,
            'scanned_at' => now(),
        ]);

        $response = $this->getJson('/api/scanlogs');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data riwayat scan berhasil diambil',
            ]);

        $data = $response->json('data');

        $this->assertCount(2, $data);

        $driverRow = collect($data)->firstWhere('driver_id', $driver->id);
        $helperRow = collect($data)->firstWhere('driver_id', $helper->id);

        $this->assertNotNull($driverRow);
        $this->assertNotNull($helperRow);

        $this->assertSame(now()->toDateString(), $driverRow['tanggal']);
        $this->assertSame('Budi Santoso', $driverRow['nama_amt']);
        $this->assertSame('B 1234 KT', $driverRow['nopol']);
        $this->assertSame(24, $driverRow['kapasitas']);
        $this->assertSame('AMT 1', $driverRow['jabatan']);
        $this->assertSame('done', $driverRow['status']);
        $this->assertSame('Complete', $driverRow['status_text']);
        $this->assertSame(2, $driverRow['scanned_compartments']);
        $this->assertSame(2, $driverRow['total_compartments']);

        $this->assertSame('Andi Wijaya', $helperRow['nama_amt']);
        $this->assertSame('AMT 2', $helperRow['jabatan']);
        $this->assertSame('kurang', $helperRow['status']);
        $this->assertSame('Belum Lengkap', $helperRow['status_text']);
        $this->assertSame(1, $helperRow['scanned_compartments']);

        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_scanlogs_api_supports_filters(): void
    {
        $driver = Driver::create([
            'driver_no' => 'DRV-200',
            'name' => 'Rina Amalia',
            'role' => 'driver',
            'is_active' => true,
        ]);

        $device = Device::create([
            'device_uuid' => 'DEV-SCANLOGS-002',
            'name' => 'Scanner Terminal 2',
            'is_active' => true,
        ]);

        $tanker = Tanker::create([
            'nopol' => 'D 5678 KT',
            'capacity_kl' => 16,
            'status' => 'available',
        ]);

        $compartment = TankerCompartment::create([
            'tanker_id' => $tanker->id,
            'compartment_no' => 1,
            'capacity_kl' => 16.00,
            'rfid_uid' => 'RFID-SCANLOGS-003',
        ]);

        $session = ScanSession::create([
            'driver_id' => $driver->id,
            'device_id' => $device->id,
            'tanker_id' => $tanker->id,
            'status' => 'in_progress',
            'started_at' => now()->subDay(),
        ]);

        ScanLog::create([
            'scan_session_id' => $session->id,
            'driver_id' => $driver->id,
            'device_id' => $device->id,
            'tanker_compartment_id' => $compartment->id,
            'scanned_at' => now()->subDay(),
        ]);

        $filteredByNopol = $this->getJson('/api/scanlogs?nopol=D%205678');
        $filteredByNopol->assertStatus(200);
        $this->assertCount(1, $filteredByNopol->json('data'));
        $this->assertSame('D 5678 KT', $filteredByNopol->json('data.0.nopol'));

        $filteredByDriver = $this->getJson('/api/scanlogs?driver_id='.$driver->id);
        $filteredByDriver->assertStatus(200);
        $this->assertCount(1, $filteredByDriver->json('data'));
        $this->assertSame('Rina Amalia', $filteredByDriver->json('data.0.nama_amt'));

        $filteredByDate = $this->getJson('/api/scanlogs?date='.now()->toDateString());
        $filteredByDate->assertStatus(200);
        $this->assertCount(0, $filteredByDate->json('data'));

        $filteredByDate = $this->getJson('/api/scanlogs?date='.now()->subDay()->toDateString());
        $filteredByDate->assertStatus(200);
        $this->assertCount(1, $filteredByDate->json('data'));

        $filteredByRange = $this->getJson('/api/scanlogs?from='.now()->subDay()->toDateString().'&until='.now()->toDateString());
        $filteredByRange->assertStatus(200);
        $this->assertCount(1, $filteredByRange->json('data'));
    }
}
