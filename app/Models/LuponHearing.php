<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LuponHearing extends Model
{
    protected $table = 'lupon_hearings';

    protected $fillable = [
        'case_id',
        'hearing_date',
        'hearing_time',
        'type',
        'location',
        'attendance_summary',
        'result_summary',
        'next_hearing_at',
        'status',
        'proceedings',
        'next_action_notes',
        'documents_notes',
        'remarks',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'hearing_date' => 'date',
            'next_hearing_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LuponCase::class, 'case_id');
    }

    public function luponMembers(): BelongsToMany
    {
        return $this->belongsToMany(LuponMember::class, 'lupon_hearing_member', 'hearing_id', 'lupon_member_id')
            ->withTimestamps()
            ->orderBy('position')
            ->orderBy('id');
    }
}
