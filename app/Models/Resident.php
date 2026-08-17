<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Resident extends Model
{
    protected $appends = ['full_name', 'photo_url'];

    protected $fillable = [
        'resident_number', 'household_id', 'purok_id', 'first_name', 'middle_name',
        'last_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'nationality',
        'place_of_birth', 'house_number', 'street', 'address', 'mobile_number',
        'email', 'occupation', 'employer', 'monthly_income', 'is_voter',
        'is_senior_citizen', 'is_pwd', 'is_4ps', 'is_solo_parent',
        'is_household_head', 'status', 'registered_at', 'remarks', 'photo_path', 'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'registered_at' => 'date',
            'monthly_income' => 'decimal:2',
            'is_voter' => 'boolean',
            'is_senior_citizen' => 'boolean',
            'is_pwd' => 'boolean',
            'is_4ps' => 'boolean',
            'is_solo_parent' => 'boolean',
            'is_household_head' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter()
            ->implode(' ');
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

        $this->forceFill(['photo_path' => $file->store('resident-photos', 'public')])->save();
    }
}
