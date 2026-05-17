<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'purpose',
        'amount',
        'description',
        'added_by'
    ];

    protected $casts = [
        'amount' => 'integer'
    ];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
