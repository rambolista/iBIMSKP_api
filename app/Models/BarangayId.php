<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BarangayId extends Model
{
    protected $appends = ['photo_url', 'signature_url'];

    protected $fillable = [
        'id_number', 'resident_id', 'replacement_of_id', 'applied_at', 'issued_at', 'expires_at',
        'validity_years', 'status', 'payment_status', 'or_number', 'paid_at', 'verified_at', 'verified_by', 'verification_code',
        'photo_path', 'signature_path', 'lost_reported_at', 'lost_remarks', 'cancel_reason',
        'remarks', 'archived_at', 'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'date',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
            'lost_reported_at' => 'date',
            'paid_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function replacementOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_of_id');
    }

    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'replacement_of_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BarangayIdLog::class)->orderByDesc('created_at');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_path ? Storage::disk('public')->url($this->signature_path) : null;
    }

    public function replacePhoto(UploadedFile $file): void
    {
        if ($this->photo_path) {
            Storage::disk('public')->delete($this->photo_path);
        }

        $this->forceFill(['photo_path' => $file->store('barangay-id-photos', 'public')])->save();
    }

    public function replaceSignature(UploadedFile $file): void
    {
        if ($this->signature_path) {
            Storage::disk('public')->delete($this->signature_path);
        }

        $this->forceFill(['signature_path' => $file->store('barangay-id-signatures', 'public')])->save();
    }
}
