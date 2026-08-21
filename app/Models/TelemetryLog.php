<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TelemetryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'device_id',
        'latitude',
        'longitude',
        'temperature',
        'humidity',
        'mkt_value',
        'rsl_hours',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}