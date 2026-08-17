<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blotter extends Model
{
    protected $fillable = [
        'blotter_number',
        'incident_date',
        'incident_time',
        'resident_id',
        'complainant_name',
        'respondent_name',
        'location',
        'narrative',
        'action_taken',
        'settled_at',
        'status',
        'remarks',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'settled_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}

