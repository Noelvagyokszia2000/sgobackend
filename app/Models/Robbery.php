<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Robbery extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'created_by',
        'participants_count',
        'applicants_count',
        'finished',
    ];

    protected $casts = [
        'participants_count' => 'integer',
        'applicants_count' => 'integer',
        'finished' => 'boolean',
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
