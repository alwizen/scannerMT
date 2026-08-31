<?php

namespace Tests\Feature;

use App\Filament\Resources\ParkingLocations\ParkingLocationResource;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingLocationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parking_location_resource_form_instantiation(): void
    {
        $schema = Schema::make();
        $formSchema = ParkingLocationResource::form($schema);

        $this->assertInstanceOf(Schema::class, $formSchema);
        $this->assertNotEmpty($formSchema->getComponents());
    }
}
