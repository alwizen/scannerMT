<?php

namespace Tests\Unit;

use App\Models\ParkingLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingLocationGeofenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_haversine_distance_calculation(): void
    {
        // Distance between Monas Jakarta (-6.1753924, 106.8271528) and Stasiun Gambir (-6.17667, 106.83065) is approx 410 meters
        $distance = ParkingLocation::haversineDistance(-6.1753924, 106.8271528, -6.17667, 106.83065);
        $this->assertGreaterThan(350, $distance);
        $this->assertLessThan(480, $distance);
    }

    public function test_point_in_polygon_calculation(): void
    {
        // Define a square polygon around (-6.200, 106.800) to (-6.210, 106.810)
        $polygon = [
            ['lat' => -6.200, 'lng' => 106.800],
            ['lat' => -6.200, 'lng' => 106.810],
            ['lat' => -6.210, 'lng' => 106.810],
            ['lat' => -6.210, 'lng' => 106.800],
        ];

        // Point inside (-6.205, 106.805)
        $this->assertTrue(ParkingLocation::isPointInPolygon(-6.205, 106.805, $polygon));

        // Point outside (-6.220, 106.805)
        $this->assertFalse(ParkingLocation::isPointInPolygon(-6.220, 106.805, $polygon));
    }

    public function test_radius_geofence_matching(): void
    {
        $location = ParkingLocation::create([
            'name' => 'Depot Parkir MT Jakarta',
            'type' => 'radius',
            'latitude' => -6.2000000,
            'longitude' => 106.8000000,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        // Coordinates within 200 meters (~ 50 meters away)
        $this->assertTrue($location->isPointInside(-6.2003000, 106.8003000));

        // Coordinates far away (10 km away)
        $this->assertFalse($location->isPointInside(-6.3000000, 106.9000000));
    }

    public function test_polygon_geofence_matching(): void
    {
        $location = ParkingLocation::create([
            'name' => 'Depot Parkir MT Merak',
            'type' => 'polygon',
            'latitude' => -5.932,
            'longitude' => 106.000,
            'polygon_coordinates' => [
                ['lat' => -5.930, 'lng' => 105.990],
                ['lat' => -5.930, 'lng' => 106.010],
                ['lat' => -5.940, 'lng' => 106.010],
                ['lat' => -5.940, 'lng' => 105.990],
            ],
            'is_active' => true,
        ]);

        // Point inside polygon
        $this->assertTrue($location->isPointInside(-5.935, 106.000));

        // Point outside polygon
        $this->assertFalse($location->isPointInside(-5.950, 106.050));
    }

    public function test_find_matching_location(): void
    {
        $locationA = ParkingLocation::create([
            'name' => 'Parkir MT Zone A',
            'type' => 'radius',
            'latitude' => -6.1000000,
            'longitude' => 106.7000000,
            'radius_meters' => 500,
            'is_active' => true,
        ]);

        $found = ParkingLocation::findMatchingLocation(-6.1010000, 106.7010000);
        $this->assertNotNull($found);
        $this->assertEquals($locationA->id, $found->id);

        $notFound = ParkingLocation::findMatchingLocation(-7.0000000, 107.0000000);
        $this->assertNull($notFound);
    }
}
