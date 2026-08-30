<?php

namespace Tests\Feature;

use App\Filament\Resources\TankerCompartments\TankerCompartmentResource;
use App\Models\Tanker;
use App\Models\TankerCompartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TankerCompartmentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_eloquent_query_only_returns_first_compartment(): void
    {
        // Create a tanker with 3 compartments
        $tanker1 = Tanker::create([
            'nopol' => 'G 1111 AA',
            'capacity_kl' => 24,
            'status' => 'available',
        ]);

        $comp1 = TankerCompartment::create([
            'tanker_id' => $tanker1->id,
            'compartment_no' => 1,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-1-1',
        ]);

        $comp2 = TankerCompartment::create([
            'tanker_id' => $tanker1->id,
            'compartment_no' => 2,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-1-2',
        ]);

        $comp3 = TankerCompartment::create([
            'tanker_id' => $tanker1->id,
            'compartment_no' => 3,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-1-3',
        ]);

        // Create another tanker with 1 compartment
        $tanker2 = Tanker::create([
            'nopol' => 'G 2222 BB',
            'capacity_kl' => 8,
            'status' => 'available',
        ]);

        $comp4 = TankerCompartment::create([
            'tanker_id' => $tanker2->id,
            'compartment_no' => 1,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-2-1',
        ]);

        // Retrieve the query
        $results = TankerCompartmentResource::getEloquentQuery()->get();

        // Verify it only returned compartment_no = 1
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $comp1->id));
        $this->assertTrue($results->contains('id', $comp4->id));
        $this->assertFalse($results->contains('id', $comp2->id));
        $this->assertFalse($results->contains('id', $comp3->id));
    }

    public function test_save_other_compartments_creates_and_updates_and_deletes(): void
    {
        $tanker = Tanker::create([
            'nopol' => 'G 3333 CC',
            'capacity_kl' => 24,
            'status' => 'available',
        ]);

        $comp1 = TankerCompartment::create([
            'tanker_id' => $tanker->id,
            'compartment_no' => 1,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-3-1',
        ]);

        // 1. Test creation of comp 2 and comp 3
        $data = [
            'comp2_capacity' => 8.00,
            'comp2_rfid' => 'RFID-3-2',
            'comp3_capacity' => 8.00,
            'comp3_rfid' => 'RFID-3-3',
        ];

        TankerCompartmentResource::saveOtherCompartments($comp1, $data);

        $this->assertDatabaseHas('tanker_compartments', [
            'tanker_id' => $tanker->id,
            'compartment_no' => 2,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-3-2',
        ]);

        $this->assertDatabaseHas('tanker_compartments', [
            'tanker_id' => $tanker->id,
            'compartment_no' => 3,
            'capacity_kl' => 8.00,
            'rfid_uid' => 'RFID-3-3',
        ]);

        // 2. Test updating of comp 2 and deletion of comp 3
        $data2 = [
            'comp2_capacity' => 10.00,
            'comp2_rfid' => 'RFID-3-2-UPDATED',
            'comp3_capacity' => null,
            'comp3_rfid' => '',
        ];

        TankerCompartmentResource::saveOtherCompartments($comp1, $data2);

        $this->assertDatabaseHas('tanker_compartments', [
            'tanker_id' => $tanker->id,
            'compartment_no' => 2,
            'capacity_kl' => 10.00,
            'rfid_uid' => 'RFID-3-2-UPDATED',
        ]);

        $this->assertSoftDeleted('tanker_compartments', [
            'tanker_id' => $tanker->id,
            'compartment_no' => 3,
        ]);
    }
}
