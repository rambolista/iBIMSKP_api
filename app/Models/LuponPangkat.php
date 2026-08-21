<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LuponPangkat extends Model
{
    protected $table = 'lupon_pangkats';

    protected $appends = ['members_count'];

    protected $fillable = [
        'pangkat_id',
        'case_id',
        'date_constituted',
        'members_summary',
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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(LuponMember::class, 'lupon_pangkat_member', 'pangkat_id', 'lupon_member_id')
            ->withTimestamps()
            ->orderBy('position')
            ->orderBy('id');
    }

    public function getMembersCountAttribute(): int
    {
        return $this->relationLoaded('members')
            ? $this->members->count()
            : $this->members()->count();
    }
}
