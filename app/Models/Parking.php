<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parking extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'occupied'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
