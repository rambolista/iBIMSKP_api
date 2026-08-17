<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BarangayId extends Model
{
    protected $appends = ['photo_url'];

    protected $fillable = [
        'id_number', 'resident_id', 'applied_at', 'issued_at', 'expires_at',
        'status', 'verification_code', 'photo_path', 'remarks', 'archived_at', 'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'date',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function replacePhoto(UploadedFile $file): void
    {
        if ($this->photo_path) {
            Storage::disk('public')->delete($this->photo_path);
        }

        $this->forceFill(['photo_path' => $file->store('barangay-id-photos', 'public')])->save();
    }
}
