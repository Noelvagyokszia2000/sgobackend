<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklySubmission extends Model
{
    protected $table = 'weekly_submissions';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'imageLink',
        'amount',
        'accepted',
        'created_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
