<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanSession extends Model
{
    protected $fillable = [
        'driver_id',
        'device_id',
        'tanker_id',
        'status',
        'started_at',
        'completed_at',
        'action_note',
        'action_handled_at',
        'action_handled_by',
    ];

    protected $casts = [
        'driver_id' => 'integer',
        'device_id' => 'integer',
        'tanker_id' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'action_handled_at' => 'datetime',
        'action_handled_by' => 'integer',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function tanker(): BelongsTo
    {
        return $this->belongsTo(Tanker::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_handled_by');
    }

    public function isActionHandled(): bool
    {
        return filled($this->action_note) || filled($this->action_handled_at);
    }

    public function scopeActionHandled($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('action_note')
                ->orWhereNotNull('action_handled_at');
        });
    }

    public function scopeActionPending($query)
    {
        return $query->whereNull('action_note')
            ->whereNull('action_handled_at');
    }
}