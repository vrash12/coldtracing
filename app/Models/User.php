<?php

namespace App\Models;

use App\Models\Order;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'phone',
        'permanent_delivery_address',
        'permanent_delivery_lat',
        'permanent_delivery_lng',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permanent_delivery_lat' => 'float',
            'permanent_delivery_lng' => 'float',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function driverTrips()
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    public function receiverTrips()
    {
        return $this->hasMany(Trip::class, 'receiver_id');
    }

    public function createdOrders()
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function receivedOrders()
    {
        return $this->hasMany(Order::class, 'receiver_id');
    }

    public function isAdministrator(): bool
    {
        return $this->role?->name === 'Administrator';
    }

    public function isDriver(): bool
    {
        return $this->role?->name === 'Driver';
    }

    public function isReceiver(): bool
    {
        return $this->role?->name === 'Receiver';
    }

    public function hasPermanentDeliveryAddress(): bool
    {
        return !empty($this->permanent_delivery_address)
            && $this->permanent_delivery_lat !== null
            && $this->permanent_delivery_lng !== null;
    }

   public function assignedTruck()
{
    return $this->hasOne(Truck::class, 'driver_id');
}
}
