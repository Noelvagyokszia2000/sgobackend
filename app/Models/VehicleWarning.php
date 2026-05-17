<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleWarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'vehicle_name',
        'reason',
        'issued_by'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
