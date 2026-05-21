<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'created_by',
        'text',
        'image',
        'published_at',
        'deleted_at',
        'discord_channel_id',
        'discord_message_id',
        'discord_announced_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'deleted_at' => 'datetime',
        'discord_announced_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
