<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'name',
        'vin',
        'plate',
        'image',
        'warns',
        'max_keys',
        'all_members'
    ];

    protected $casts = [
        'all_members' => 'boolean',
        'max_keys' => 'integer',
        'warns' => 'integer',
    ];

    public function keyholders()
    {
        return $this->belongsToMany(
            User::class,
            'vehicle_keys'
        )
            ->wherePivot('status', 'approved')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function keyRequests()
    {
        return $this->hasMany(VehicleKey::class);
    }

    public function warnings()
    {
        return $this->hasMany(VehicleWarning::class);
    }
}
