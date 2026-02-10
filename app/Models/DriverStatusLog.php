<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'status',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

