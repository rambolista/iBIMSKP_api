<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = [
        'label',
        'slug',
        'url',
        'icon',
        'parent_id',
        'sort_order',
        'is_title',
        'is_active',
        'is_disabled',
        'is_special',
        'badge_text',
        'badge_class',
    ];

    protected $casts = [
        'is_title'   => 'boolean',
        'is_active'  => 'boolean',
        'is_disabled' => 'boolean',
        'is_special' => 'boolean',
        'sort_order' => 'integer',
        'parent_id'  => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_order');
    }
}
