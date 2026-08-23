<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangayIdLog extends Model
{
    protected $fillable = [
        'barangay_id_id', 'label', 'message', 'actor_id',
    ];

    public function barangayId(): BelongsTo
    {
        return $this->belongsTo(BarangayId::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
