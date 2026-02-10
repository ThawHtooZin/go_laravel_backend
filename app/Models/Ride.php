<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected $fillable = [
        'passenger_id',
        'driver_id',
        'status',
        'origin_text',
        'destination_text',
        'pickup_lat',
        'pickup_lng',
        'dropoff_lat',
        'dropoff_lng',
        'distance_km',
        'vehicle_type',
        'estimated_price',
    ];

    protected function casts(): array
    {
        return [
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dropoff_lat' => 'float',
            'dropoff_lng' => 'float',
            'distance_km' => 'float',
        ];
    }

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}

