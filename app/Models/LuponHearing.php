<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LuponHearing extends Model
{
    protected $table = 'lupon_hearings';

    private const ACTIVE_CASE_STATUSES = ['filed', 'for_mediation', 'for_conciliation', 'for_pangkat'];

    protected $fillable = [
        'case_id',
        'hearing_date',
        'hearing_time',
        'location',
        'case_status_at_scheduling',
        'archived_at',
        'archived_by',
    ];

    protected $appends = ['schedule_editable'];

    protected function casts(): array
    {
        return [
            'hearing_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LuponCase::class, 'case_id');
    }

    public function getScheduleEditableAttribute(): bool
    {
        if ($this->archived_at || ! $this->hearing_date || $this->hearing_date->lt(today())) {
            return false;
        }

        $caseStatus = $this->relationLoaded('case') ? $this->case?->status : $this->case()->value('status');

        return in_array($caseStatus, self::ACTIVE_CASE_STATUSES, true);
    }

    public function luponMembers(): BelongsToMany
    {
        return $this->belongsToMany(LuponMember::class, 'lupon_hearing_member', 'hearing_id', 'lupon_member_id')
            ->withTimestamps()
            ->orderBy('position')
            ->orderBy('id');
    }
}
