<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LuponMember extends Model
{
    protected $table = 'lupon_members';

    protected $appends = ['photo_url', 'term_label', 'assigned_cases_count', 'assigned_hearings_count'];

    protected $fillable = [
        'member_id',
        'resident_id',
        'photo_path',
        'position',
        'date_appointed',
        'term_start',
        'term_end',
        'status',
        'appointment_notes',
        'remarks',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'date_appointed' => 'date',
            'term_start' => 'date',
            'term_end' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'resident_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(LuponCase::class, 'lupon_case_member', 'lupon_member_id', 'case_id')
            ->withTimestamps()
            ->orderByDesc('date_filed')
            ->orderByDesc('id');
    }

    public function hearings(): BelongsToMany
    {
        return $this->belongsToMany(LuponHearing::class, 'lupon_hearing_member', 'lupon_member_id', 'hearing_id')
            ->withTimestamps()
            ->orderBy('hearing_date')
            ->orderBy('hearing_time')
            ->orderBy('id');
    }

    public function getTermLabelAttribute(): string
    {
        $start = $this->term_start?->format('M d, Y');
        $end = $this->term_end?->format('M d, Y');

        if ($start && $end) {
            return "{$start} - {$end}";
        }

        if ($start) {
            return "{$start} onwards";
        }

        if ($end) {
            return "Until {$end}";
        }

        return '—';
    }

    public function getAssignedCasesCountAttribute(): int
    {
        if (array_key_exists('cases_count', $this->attributes)) {
            return (int) $this->attributes['cases_count'];
        }

        return $this->relationLoaded('cases') ? $this->cases->count() : $this->cases()->count();
    }

    public function getAssignedHearingsCountAttribute(): int
    {
        if (array_key_exists('hearings_count', $this->attributes)) {
            return (int) $this->attributes['hearings_count'];
        }

        return $this->relationLoaded('hearings') ? $this->hearings->count() : $this->hearings()->count();
    }

    public function replacePhoto(UploadedFile $file): void
    {
        if ($this->photo_path) {
            Storage::disk('public')->delete($this->photo_path);
        }

        $this->forceFill(['photo_path' => $file->store('lupon-member-photos', 'public')])->save();
    }
}
