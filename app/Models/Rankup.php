<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rankup extends Model
{
    protected $table = 'rankups';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'issued_by',
        'previous_rank_id',
        'next_rank_id',
        'issued_at_site',
        'issued_at_game',
        'completed'
    ];

    protected $casts = [
        'issued_at_site' => 'datetime',
        'issued_at_game' => 'datetime',
        'completed' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function previousRank()
    {
        return $this->belongsTo(Rank::class, 'previous_rank_id');
    }

    public function nextRank()
    {
        return $this->belongsTo(Rank::class, 'next_rank_id');
    }
}