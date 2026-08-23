<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlotterLog extends Model
{
    protected $fillable = [
        'blotter_id', 'label', 'message', 'actor_id',
    ];

    public function blotter(): BelongsTo
    {
        return $this->belongsTo(Blotter::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
