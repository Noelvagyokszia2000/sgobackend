<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'username',
        'password',
        'IgName',
        'createdAt',
        'warn',
        'weeklyPay',
        'isAdmin',
        'rank_id',
        'lastRankup'
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'isAdmin' => 'boolean',
        'createdAt' => 'date',
        'weeklyPay' => 'date',
        'lastRankup' => 'datetime'
    ];

    public $timestamps = false;

    public function warns()
    {
        return $this->hasMany(Warn::class, 'user_id');
    }

    public function issuedWarns()
    {
        return $this->hasMany(Warn::class, 'issued_by');
    }

    public function parkings()
    {
        return $this->hasMany(Parking::class);
    }

    public function rank()
    {
        return $this->belongsTo(Rank::class, 'rank_id');
    }

    public function rankups()
    {
        return $this->hasMany(Rankup::class, 'user_id');
    }

    public function issuedRankups()
    {
        return $this->hasMany(Rankup::class, 'issued_by');
    }
}