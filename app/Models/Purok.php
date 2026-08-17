<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purok extends Model
{
    protected $fillable = ['code', 'name', 'description', 'status', 'archived_at', 'archived_by'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }
}
