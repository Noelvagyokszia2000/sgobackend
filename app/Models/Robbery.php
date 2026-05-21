<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Robbery extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'created_by',
        'name',
        'type',
        'participants_count',
        'applicants_count',
        'finished',
        'discord_channel_id',
        'discord_message_id',
        'discord_announced_at',
    ];

    protected $casts = [
        'participants_count' => 'integer',
        'applicants_count' => 'integer',
        'finished' => 'boolean',
        'discord_announced_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function incomeImages()
    {
        return $this->hasMany(RobberyIncomeImage::class);
    }
}
