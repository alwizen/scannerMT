<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Driver;
use App\Models\Tanker;
use App\Models\TankerCompartment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TankerPilotSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Driver::create([
            'driver_no' => 'DRV001',
            'name' => 'Budi Santoso',
            'phone' => null,
            'role' => 'driver',
            'is_active' => true,
        ]);

        Driver::create([
            'driver_no' => 'DRV002',
            'name' => 'Joko Prasetyo',
            'phone' => null,
            'role' => 'helper',
            'is_active' => true,
        ]);

        Device::create([
            'device_uuid' => 'UNIWA-W999-01',
            'name' => 'UNIWA W999 Pilot',
            'is_active' => true,
        ]);

        $tanker = Tanker::create([
            'nopol' => 'G 8123 XX',
            'capacity_kl' => 24,
            'status' => 'available',
        ]);

        TankerCompartment::insert([
            [
                'tanker_id' => $tanker->id,
                'compartment_no' => 1,
                'capacity_kl' => 8.00,
                'rfid_uid' => 'NFC-COMP-001',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tanker_id' => $tanker->id,
                'compartment_no' => 2,
                'capacity_kl' => 8.00,
                'rfid_uid' => 'NFC-COMP-002',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tanker_id' => $tanker->id,
                'compartment_no' => 3,
                'capacity_kl' => 8.00,
                'rfid_uid' => 'NFC-COMP-003',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
