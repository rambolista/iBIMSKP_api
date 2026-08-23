<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BarangayOfficial extends Model
{
    protected $appends = ['photo_url'];

    protected $fillable = [
        'name',
        'resident_id',
        'position',
        'status',
        'term_start',
        'term_end',
        'phone',
        'email',
        'photo_path',
        'is_lupon_member',
        'is_signatory',
        'remarks',
        'created_by',
        'updated_by',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'term_start' => 'date',
            'term_end' => 'date',
            'is_lupon_member' => 'boolean',
            'is_signatory' => 'boolean',
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

        $this->forceFill(['photo_path' => $file->store('barangay-official-photos', 'public')])->save();
    }
}
