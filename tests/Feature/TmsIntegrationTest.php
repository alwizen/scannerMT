<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Driver;
use App\Models\ScanSession;
use App\Models\Tanker;
use App\Models\TankerCompartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;

    private Device $device;

    private Tanker $tanker;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tms.base_url', 'http://tms.test');
        config()->set('services.tms.timeout', 5);

        $this->driver = Driver::create([
            'driver_no' => 'DRV-TMS-001',
            'name' => 'Slamet Riyadi',
            'role' => 'driver',
            'is_active' => true,
        ]);

        $this->device = Device::create([
            'device_uuid' => 'DEV-TMS-001',
            'name' => 'Scanner TMS',
            'is_active' => true,
        ]);

        $this->tanker = Tanker::create([
            'nopol' => 'G8401DE',
            'capacity_kl' => 16,
            'status' => 'available',
        ]);
    }

    public function test_posts_cek_compartment_to_tms_when_session_completes(): void
    {
        Http::fake([
            'tms.test/*' => Http::response(['nopol' => 'G8401DE'], 200),
        ]);

        $compartment = $this->createCompartment('RFID-TMS-001');

        $response = $this->postJson('/api/scan', [
            'driver_id' => $this->driver->id,
            'device_uuid' => $this->device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('scan_sessions', [
            'tanker_id' => $this->tanker->id,
            'status' => 'completed',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://tms.test/tegal_disit_rpc/set_cek_compartment/'
                && $request['nopol'] === 'G8401DE'
                && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $request['jam_cek_comp']) === 1;
        });
    }

    public function test_scan_still_completes_when_tms_cek_compartment_fails(): void
    {
        Http::fake([
            'tms.test/*' => Http::response('Server Error', 500),
        ]);

        $compartment = $this->createCompartment('RFID-TMS-002');

        $response = $this->postJson('/api/scan', [
            'driver_id' => $this->driver->id,
            'device_uuid' => $this->device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('scan_sessions', [
            'tanker_id' => $this->tanker->id,
            'status' => 'completed',
        ]);
    }

    public function test_rescan_blocked_when_tms_returns_no_gate_in(): void
    {
        $compartment = $this->createCompartment('RFID-TMS-003');

        $this->createCompletedSession(now()->subHour());

        Http::fake([
            'tms.test/*' => Http::response([
                'shipment_id' => 'False',
                'gate_in_time' => ' ',
                'nopol' => ' ',
                'status' => ' ',
                'nip_supir' => ' ',
                'nama_supir' => ' ',
                'nip_kernet' => ' ',
                'nama_kernet' => ' ',
            ], 200),
        ]);

        $response = $this->postJson('/api/scan', [
            'driver_id' => $this->driver->id,
            'device_uuid' => $this->device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
        ]);

        $response->assertStatus(409);
    }

    public function test_rescan_blocked_when_gate_in_time_is_older_than_last_completed_session(): void
    {
        $compartment = $this->createCompartment('RFID-TMS-004');

        $completedAt = now()->subHour();
        $this->createCompletedSession($completedAt);

        Http::fake([
            'tms.test/*' => Http::response([
                'shipment_id' => '45728938',
                'gate_in_time' => $completedAt->copy()->subHour()->format('Y-m-d H:i:s'),
                'nopol' => 'G8401DE',
                'status' => 'O',
                'nip_supir' => '',
                'nama_supir' => 'NUR SETO',
                'nip_kernet' => 'NA',
                'nama_kernet' => 'NA',
            ], 200),
        ]);

        $response = $this->postJson('/api/scan', [
            'driver_id' => $this->driver->id,
            'device_uuid' => $this->device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
        ]);

        $response->assertStatus(409);
    }

    public function test_rescan_allowed_when_gate_in_time_is_newer_than_last_completed_session(): void
    {
        $compartment = $this->createCompartment('RFID-TMS-005');

        $completedAt = now()->subHours(2);
        $this->createCompletedSession($completedAt);

        Http::fake([
            'tms.test/*' => Http::response([
                'shipment_id' => '45728938',
                'gate_in_time' => $completedAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
                'nopol' => 'G8401DE',
                'status' => 'O',
                'nip_supir' => '',
                'nama_supir' => 'NUR SETO',
                'nip_kernet' => 'NA',
                'nama_kernet' => 'NA',
            ], 200),
        ]);

        $response = $this->postJson('/api/scan', [
            'driver_id' => $this->driver->id,
            'device_uuid' => $this->device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
        ]);

        $response->assertStatus(200);

        $response->assertJsonPath('success', true);

        $this->assertNotSame(
            ScanSession::where('tanker_id', $this->tanker->id)->oldest('id')->value('id'),
            $response->json('data.scan_session_id')
        );

        $this->assertSame(2, ScanSession::where('tanker_id', $this->tanker->id)->count());
    }

    public function test_rescan_allowed_when_tms_is_unreachable(): void
    {
        $compartment = $this->createCompartment('RFID-TMS-006');

        $this->createCompletedSession(now()->subHour());

        Http::fake([
            'tms.test/*' => Http::response('Server Error', 500),
        ]);

        $response = $this->postJson('/api/scan', [
            'driver_id' => $this->driver->id,
            'device_uuid' => $this->device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
        ]);

        $response->assertStatus(200);
    }

    public function test_tms_requests_skipped_when_base_url_empty(): void
    {
        config()->set('services.tms.base_url', '');

        $compartment = $this->createCompartment('RFID-TMS-007');

        $this->createCompletedSession(now()->subHour());

        Http::fake();

        $response = $this->postJson('/api/scan', [
            'driver_id' => $this->driver->id,
            'device_uuid' => $this->device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
        ]);

        $response->assertStatus(200);

        Http::assertNothingSent();
    }

    private function createCompartment(string $rfidUid): TankerCompartment
    {
        return TankerCompartment::create([
            'tanker_id' => $this->tanker->id,
            'compartment_no' => $this->tanker->compartments()->count() + 1,
            'capacity_kl' => 8.00,
            'rfid_uid' => $rfidUid,
        ]);
    }

    private function createCompletedSession(\DateTimeInterface $completedAt): ScanSession
    {
        return ScanSession::create([
            'driver_id' => $this->driver->id,
            'device_id' => $this->device->id,
            'tanker_id' => $this->tanker->id,
            'status' => 'completed',
            'started_at' => $completedAt,
            'completed_at' => $completedAt,
        ]);
    }
}
