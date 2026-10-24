<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
