<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InactivityRequest extends Model
{
    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'handled_by',
        'handled_at',
        'rejection_reason',
        'endingReminderSentFor',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'handled_at' => 'datetime',
        'endingReminderSentFor' => 'date',
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
