<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'latitude',
        'longitude',
        'radius_meters',
        'polygon_coordinates',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_meters' => 'integer',
        'polygon_coordinates' => 'array',
        'is_active' => 'boolean',
    ];

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    /**
     * Check if a given latitude & longitude coordinate is inside this parking location geofence.
     */
    public function isPointInside(float $lat, float $lng): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->type === 'radius') {
            if ($this->latitude === null || $this->longitude === null) {
                return false;
            }

            $distance = self::haversineDistance((float) $this->latitude, (float) $this->longitude, $lat, $lng);
            return $distance <= ($this->radius_meters ?? 100);
        }

        if ($this->type === 'polygon') {
            $coords = $this->polygon_coordinates;
            if (is_array($coords) && count($coords) >= 3) {
                return self::isPointInPolygon($lat, $lng, $coords);
            }

            // Fallback to center point radius if polygon not defined properly
            if ($this->latitude !== null && $this->longitude !== null) {
                $distance = self::haversineDistance((float) $this->latitude, (float) $this->longitude, $lat, $lng);
                return $distance <= ($this->radius_meters ?? 100);
            }
        }

        return false;
    }

    /**
     * Find active parking location that contains the given coordinate.
     */
    public static function findMatchingLocation(float $lat, float $lng): ?self
    {
        $locations = self::where('is_active', true)->get();

        foreach ($locations as $location) {
            if ($location->isPointInside($lat, $lng)) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Calculate Haversine distance in meters between two coordinate points.
     */
    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if a point (lat, lng) is inside a polygon using Ray Casting Algorithm.
     */
    public static function isPointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $vertices = [];

        foreach ($polygon as $pt) {
            if (is_array($pt)) {
                if (isset($pt['lat']) && isset($pt['lng'])) {
                    $vertices[] = [(float) $pt['lat'], (float) $pt['lng']];
                } elseif (isset($pt[0]) && isset($pt[1])) {
                    $vertices[] = [(float) $pt[0], (float) $pt[1]];
                }
            } elseif (is_object($pt)) {
                if (isset($pt->lat) && isset($pt->lng)) {
                    $vertices[] = [(float) $pt->lat, (float) $pt->lng];
                }
            }
        }

        $numVertices = count($vertices);
        if ($numVertices < 3) {
            return false;
        }

        $inside = false;
        $j = $numVertices - 1;

        for ($i = 0; $i < $numVertices; $i++) {
            $latI = $vertices[$i][0];
            $lngI = $vertices[$i][1];
            $latJ = $vertices[$j][0];
            $lngJ = $vertices[$j][1];

            $denom = ($lngJ - $lngI) != 0 ? ($lngJ - $lngI) : 1e-10;
            $intersect = (($lngI > $lng) !== ($lngJ > $lng))
                && ($lat < ($latJ - $latI) * ($lng - $lngI) / $denom + $latI);

            if ($intersect) {
                $inside = ! $inside;
            }

            $j = $i;
        }

        return $inside;
    }
}
