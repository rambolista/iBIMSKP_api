<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    protected $fillable = [
        'household_number', 'name', 'purok_id', 'house_number', 'street', 'address',
        'monthly_income', 'status', 'archived_at', 'archived_by',
    ];

    protected function casts(): array
    {
        return ['monthly_income' => 'decimal:2', 'archived_at' => 'datetime'];
    }

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }
}
