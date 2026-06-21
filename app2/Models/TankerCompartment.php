<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TankerCompartment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tanker_id',
        'compartment_no',
        'capacity_kl',
        'rfid_uid',
    ];

    protected $casts = [
        'tanker_id' => 'integer',
        'compartment_no' => 'integer',
        'capacity_kl' => 'decimal:2',
    ];

    public function tanker(): BelongsTo
    {
        return $this->belongsTo(Tanker::class);
    }
}
