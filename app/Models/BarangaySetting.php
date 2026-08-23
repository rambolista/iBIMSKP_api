<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BarangaySetting extends Model
{
    protected $appends = ['logo_url', 'seal_url'];

    protected $fillable = [
        'name',
        'code',
        'address',
        'latitude',
        'longitude',
        'city_municipality',
        'province',
        'zip_code',
        'region',
        'phone',
        'email',
        'office_hours',
        'website',
        'established_year',
        'land_area',
        'population',
        'logo_path',
        'seal_path',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getSealUrlAttribute(): ?string
    {
        return $this->seal_path ? Storage::disk('public')->url($this->seal_path) : null;
    }

    public function replaceLogo(UploadedFile $file): void
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
        }

        $this->forceFill(['logo_path' => $file->store('barangay-branding', 'public')])->save();
    }

    public function replaceSeal(UploadedFile $file): void
    {
        if ($this->seal_path) {
            Storage::disk('public')->delete($this->seal_path);
        }

        $this->forceFill(['seal_path' => $file->store('barangay-branding', 'public')])->save();
    }
}
