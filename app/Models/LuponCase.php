<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LuponCase extends Model
{
    protected $table = 'lupon_cases';

    protected $appends = ['complainant_display', 'respondent_display', 'attachment_evidence_files'];

    protected $fillable = [
        'case_number',
        'date_filed',
        'complainant_resident_id',
        'complainant_name',
        'respondent_resident_id',
        'respondent_name',
        'nature',
        'assigned_lupon',
        'status',
        'next_hearing_at',
        'date_closed',
        'complaint_details',
        'case_timeline',
        'hearing_notes',
        'attendance_notes',
        'mediation_notes',
        'conciliation_notes',
        'settlement_status',
        'settlement_date',
        'settlement_agreement',
        'settlement_terms',
        'settlement_parties',
        'settlement_witnesses',
        'settlement_signatures',
        'settlement_documents_notes',
        'certificate_status',
        'certificate_number',
        'certificate_issued_at',
        'certificate_remarks',
        'related_blotter_id',
        'documents_notes',
        'evidence_notes',
        'attachment_evidence_paths',
        'remarks',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'date_filed' => 'date',
            'next_hearing_at' => 'date',
            'date_closed' => 'date',
            'settlement_date' => 'date',
            'certificate_issued_at' => 'date',
            'attachment_evidence_paths' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function complainantResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'complainant_resident_id');
    }

    public function respondentResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'respondent_resident_id');
    }

    public function relatedBlotter(): BelongsTo
    {
        return $this->belongsTo(Blotter::class, 'related_blotter_id');
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(LuponHearing::class, 'case_id')->orderBy('hearing_date')->orderBy('hearing_time');
    }

    public function pangkats(): HasMany
    {
        return $this->hasMany(LuponPangkat::class, 'case_id')->orderByDesc('date_constituted')->orderByDesc('id');
    }

    public function luponMembers(): BelongsToMany
    {
        return $this->belongsToMany(LuponMember::class, 'lupon_case_member', 'case_id', 'lupon_member_id')
            ->withTimestamps()
            ->orderBy('position')
            ->orderBy('id');
    }

    public function getComplainantDisplayAttribute(): string
    {
        return (string) ($this->complainantResident?->full_name ?: $this->complainant_name ?: '—');
    }

    public function getRespondentDisplayAttribute(): string
    {
        return (string) ($this->respondentResident?->full_name ?: $this->respondent_name ?: '—');
    }

    public function getAttachmentEvidenceFilesAttribute(): array
    {
        return collect($this->attachment_evidence_paths ?? [])
            ->filter(fn ($file) => is_array($file) && ! empty($file['path']))
            ->map(function (array $file): array {
                $path = (string) $file['path'];

                return [
                    'path' => $path,
                    'name' => $file['name'] ?? basename($path),
                    'mime' => $file['mime'] ?? null,
                    'size' => $file['size'] ?? null,
                    'url' => Storage::disk('public')->url($path),
                ];
            })
            ->values()
            ->all();
    }

    public function replaceAttachmentEvidenceFiles(array $files): void
    {
        $storedPaths = collect($this->attachment_evidence_paths ?? [])
            ->map(fn ($file) => is_array($file) ? ($file['path'] ?? null) : null)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();

        if ($storedPaths->isNotEmpty()) {
            Storage::disk('public')->delete($storedPaths->all());
        }

        $nextFiles = collect($files)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file) => [
                'path' => $file->store('lupon-case-attachments', 'public'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ])
            ->values()
            ->all();

        $this->forceFill(['attachment_evidence_paths' => $nextFiles])->save();
    }
}
