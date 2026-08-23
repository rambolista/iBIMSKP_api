<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Blotter extends Model
{
    protected $appends = ['evidence_urls'];

    protected $fillable = [
        'blotter_number',
        'incident_date',
        'incident_time',
        'resident_id',
        'incident_type',
        'complainant_name',
        'respondent_name',
        'respondent_resident_id',
        'location',
        'narrative',
        'witnesses',
        'action_taken',
        'evidence_paths',
        'responding_official',
        'investigation_started_at',
        'referred_case_id',
        'referred_by',
        'referred_reason',
        'referred_at',
        'settled_at',
        'closed_at',
        'status',
        'remarks',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'evidence_paths' => 'array',
            'investigation_started_at' => 'datetime',
            'referred_at' => 'datetime',
            'settled_at' => 'date',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function respondentResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'respondent_resident_id');
    }

    public function referredCase(): BelongsTo
    {
        return $this->belongsTo(LuponCase::class, 'referred_case_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BlotterLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function relatedCases(): HasMany
    {
        return $this->hasMany(LuponCase::class, 'related_blotter_id')
            ->orderByDesc('date_filed')
            ->orderByDesc('id');
    }

    public function getEvidenceUrlsAttribute(): array
    {
        return collect($this->evidence_paths ?? [])
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->values()
            ->all();
    }

    public function replaceEvidencePhotos(array $files): void
    {
        $storedPaths = collect($this->evidence_paths ?? [])
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();

        if ($storedPaths->isNotEmpty()) {
            Storage::disk('public')->delete($storedPaths->all());
        }

        $nextPaths = collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file) => $file->store('blotter-evidence', 'public'))
            ->values()
            ->all();

        $this->forceFill(['evidence_paths' => $nextPaths])->save();
    }

    public function appendEvidencePhotos(array $files): void
    {
        $existingPaths = collect($this->evidence_paths ?? [])
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();

        $newPaths = collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file) => $file->store('blotter-evidence', 'public'))
            ->values();

        $this->forceFill(['evidence_paths' => $existingPaths->concat($newPaths)->all()])->save();
    }
}
