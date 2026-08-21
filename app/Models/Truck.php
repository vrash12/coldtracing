<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Truck extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'plate_number',
        'model',
        'driver_name',
        'status',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }
}