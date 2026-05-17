<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $fillable = [
        'user_id',
        'reason',
        'amount',
        'proof_link',
        'note',
        'status',
        'refunded',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'refunded' => 'boolean',
        'handled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
