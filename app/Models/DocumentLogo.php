<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentLogo extends Model
{
    protected $appends = ['photo_url'];

    protected $fillable = [
        'name',
        'description',
        'photo_path',
        'status',
        'created_by',
        'updated_by',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
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

        $this->forceFill(['photo_path' => $file->store('document-logos', 'public')])->save();
    }
}
