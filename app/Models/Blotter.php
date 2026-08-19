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
        'complainant_name',
        'respondent_name',
        'location',
        'narrative',
        'action_taken',
        'evidence_paths',
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
            'evidence_paths' => 'array',
            'settled_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
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
}
