<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_temp',
        'max_temp',
        'initial_shelf_life_hours',
        'reference_storage_temp_celsius',
        'activation_energy_j_per_mol',
    ];

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

        public function orders()
        {
            return $this->hasMany(Order::class);
        }
}
