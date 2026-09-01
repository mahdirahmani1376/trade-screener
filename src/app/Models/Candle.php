<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candle extends Model
{
    protected $fillable = [
        'market_id',
        'timeframe',
        'open_time',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];

    protected $casts = [
        'open_time' => 'datetime',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
