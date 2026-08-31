<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    protected $appends = [
        'scan_status',
    ];

    protected $fillable = [
        'driver_id',
        'device_id',
        'tanker_compartment_id',
        'latitude',
        'longitude',
        'is_inside_geofence',
        'parking_location_id',
        'scanned_at',
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'device_id' => 'integer',
        'tanker_compartment_id' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_inside_geofence' => 'boolean',
        'parking_location_id' => 'integer',
        'scanned_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function tankerCompartment(): BelongsTo
    {
        return $this->belongsTo(TankerCompartment::class);
    }

    public function parkingLocation(): BelongsTo
    {
        return $this->belongsTo(ParkingLocation::class);
    }

    public function getScanStatusAttribute(): string
    {
        $tanker = $this->tankerCompartment->tanker;

        if (! $tanker) {
            return 'kurang';
        }

        $compartmentIds = $tanker->compartments()->pluck('id');

        if ($compartmentIds->isEmpty()) {
            return 'kurang';
        }

        $scannedCount = self::whereIn('tanker_compartment_id', $compartmentIds)
            ->distinct()
            ->count('tanker_compartment_id');

        return $scannedCount >= $compartmentIds->count() ? 'done' : 'kurang';
    }
}
