<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Driver;
use App\Models\ParkingLocation;
use App\Models\Tanker;
use App\Models\TankerCompartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingLocationScanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_api_detects_inside_geofence(): void
    {
        $driver = Driver::create([
            'driver_no' => 'DRV-001',
            'name' => 'Budi Driver',
            'role' => 'driver',
            'is_active' => true,
        ]);

        $device = Device::create([
            'device_uuid' => 'DEV-UUID-001',
            'name' => 'Scanner Terminal 1',
            'is_active' => true,
        ]);

        $tanker = Tanker::create([
            'nopol' => 'B 9999 K',
            'capacity_kl' => 24,
            'status' => 'available',
        ]);

        $compartment = TankerCompartment::create([
            'tanker_id' => $tanker->id,
            'compartment_no' => 1,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-TAG-100',
        ]);

        $parkingLocation = ParkingLocation::create([
            'name' => 'Lokasi Parkir MT Merak',
            'type' => 'radius',
            'latitude' => -5.9320000,
            'longitude' => 106.0000000,
            'radius_meters' => 300,
            'is_active' => true,
        ]);

        // Coordinates inside radius (~ 50 meters away)
        $response = $this->postJson('/api/scan', [
            'driver_id' => $driver->id,
            'device_uuid' => $device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
            'latitude' => -5.9323000,
            'longitude' => 106.0003000,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'geofence' => [
                        'is_inside' => true,
                        'location_id' => $parkingLocation->id,
                        'location_name' => 'Lokasi Parkir MT Merak',
                        'status_text' => 'Di dalam lokasi parkir MT',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('scan_logs', [
            'driver_id' => $driver->id,
            'device_id' => $device->id,
            'tanker_compartment_id' => $compartment->id,
            'is_inside_geofence' => true,
            'parking_location_id' => $parkingLocation->id,
        ]);
    }

    public function test_scan_api_detects_outside_geofence(): void
    {
        $driver = Driver::create([
            'driver_no' => 'DRV-002',
            'name' => 'Siti Driver',
            'role' => 'driver',
            'is_active' => true,
        ]);

        $device = Device::create([
            'device_uuid' => 'DEV-UUID-002',
            'name' => 'Scanner Terminal 2',
            'is_active' => true,
        ]);

        $tanker = Tanker::create([
            'nopol' => 'B 8888 X',
            'capacity_kl' => 16,
            'status' => 'available',
        ]);

        $compartment = TankerCompartment::create([
            'tanker_id' => $tanker->id,
            'compartment_no' => 1,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-TAG-200',
        ]);

        ParkingLocation::create([
            'name' => 'Lokasi Parkir MT Merak',
            'type' => 'radius',
            'latitude' => -5.9320000,
            'longitude' => 106.0000000,
            'radius_meters' => 300,
            'is_active' => true,
        ]);

        // Coordinates far outside (Jakarta coordinates)
        $response = $this->postJson('/api/scan', [
            'driver_id' => $driver->id,
            'device_uuid' => $device->device_uuid,
            'rfid_uid' => $compartment->rfid_uid,
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'geofence' => [
                        'is_inside' => false,
                        'location_id' => null,
                        'status_text' => 'Di luar lokasi parkir MT',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('scan_logs', [
            'driver_id' => $driver->id,
            'device_id' => $device->id,
            'tanker_compartment_id' => $compartment->id,
            'is_inside_geofence' => false,
            'parking_location_id' => null,
        ]);
    }
}
