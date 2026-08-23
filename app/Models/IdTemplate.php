<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class IdTemplate extends Model
{
    protected $appends = ['background_image_url', 'logo_url'];

    protected $fillable = [
        'name',
        'fee',
        'code',
        'background_type',
        'background_color',
        'background_image_path',
        'logo_path',
        'text_color',
        'orientation',
        'show_contact_number',
        'show_barangay_address',
        'is_default',
        'status',
        'created_by',
        'updated_by',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'is_default' => 'boolean',
            'show_contact_number' => 'boolean',
            'show_barangay_address' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->whereNull('archived_at')->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->background_image_path ? Storage::disk('public')->url($this->background_image_path) : null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function replaceBackgroundImage(UploadedFile $file): void
    {
        if ($this->background_image_path) {
            Storage::disk('public')->delete($this->background_image_path);
        }

        $this->forceFill(['background_image_path' => $file->store('id-templates', 'public')])->save();
    }

    public function replaceLogo(UploadedFile $file): void
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
        }

        $this->forceFill(['logo_path' => $file->store('id-templates', 'public')])->save();
    }
}
