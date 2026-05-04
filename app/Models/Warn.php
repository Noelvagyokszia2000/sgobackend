<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warn extends Model
{
    public $timestamps = false;

        protected $fillable = [
            'user_id',
            'issued_by',
            'reason',
            'created_at'
        ];

    public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

    public function issuer()
{
    return $this->belongsTo(User::class, 'issued_by');
}
}