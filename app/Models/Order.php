<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_code',
        'created_by',
        'receiver_id',
        'driver_id',
        'product_id',
        'quantity',
        'unit',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'expected_delivery_at',
        'notes',
        'status',
    ];

    protected $casts = [
        'expected_delivery_at' => 'datetime',
        'quantity' => 'decimal:2',
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'delivery_lat' => 'decimal:7',
        'delivery_lng' => 'decimal:7',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function trip()
    {
        return $this->hasOne(Trip::class);
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['delivered', 'cancelled'], true);
    }
}
