<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'username',
        'discord_id',
        'password',
        'IgName',
        'createdAt',
        'warn',
        'weeklyPay',
        'weeklyPaymentRequired',
        'isAdmin',
        'rank_id',
        'lastRankup',
        'profileImage',
        'successfulCassettes'
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'isAdmin' => 'boolean',
        'createdAt' => 'date',
        'weeklyPay' => 'date',
        'weeklyPaymentRequired' => 'boolean',
        'lastRankup' => 'datetime',
        'successfulCassettes' => 'integer'
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

    public function vehicleKeys()
    {
        return $this->belongsToMany(
            Vehicle::class,
            'vehicle_keys'
        )
            ->wherePivot('status', 'approved')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function refundRequests()
    {
        return $this->hasMany(RefundRequest::class, 'user_id');
    }

    public function handledRefundRequests()
    {
        return $this->hasMany(RefundRequest::class, 'handled_by');
    }

    public function news()
    {
        return $this->hasMany(News::class, 'created_by');
    }

    public function robberies()
    {
        return $this->hasMany(Robbery::class, 'created_by');
    }

    public function robberyIncomeImages()
    {
        return $this->hasMany(RobberyIncomeImage::class, 'submitted_by');
    }
}
