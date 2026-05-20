<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RobberyIncomeImage extends Model
{
    protected $fillable = [
        'robbery_id',
        'submitted_by',
        'submitted_at',
        'amount',
        'drilled_count',
        'fee_amount',
        'net_amount',
        'image',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'amount' => 'integer',
        'drilled_count' => 'integer',
        'fee_amount' => 'integer',
        'net_amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function robbery()
    {
        return $this->belongsTo(Robbery::class);
    }
}
