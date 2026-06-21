<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tanker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nopol',
        'capacity_kl',
        'status',
    ];

    protected $casts = [
        'capacity_kl' => 'integer',
    ];

    public function compartments(): HasMany
    {
        return $this->hasMany(TankerCompartment::class);
    }
}
