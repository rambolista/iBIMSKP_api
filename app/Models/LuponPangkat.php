<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LuponPangkat extends Model
{
    protected $table = 'lupon_pangkats';

    protected $appends = ['members_count'];

    protected $fillable = [
        'pangkat_id',
        'case_id',
        'date_constituted',
        'members_summary',
        'meeting_notes',
        'attendance_notes',
        'proceedings_notes',
        'documents_notes',
        'status',
        'remarks',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'date_constituted' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LuponCase::class, 'case_id');
    }

    public function getMembersCountAttribute(): int
    {
        $items = preg_split('/\r\n|\r|\n|,/', (string) ($this->members_summary ?? '')) ?: [];

        return count(array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $items))));
    }
}
