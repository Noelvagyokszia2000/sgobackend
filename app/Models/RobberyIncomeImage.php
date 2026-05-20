<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RobberyIncomeImage extends Model
{
    protected $fillable = [
        'submitted_by',
        'submitted_at',
        'amount',
        'image',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
