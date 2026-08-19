<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropdownSetting extends Model
{
    protected $fillable = [
        'category',
        'name',
        'sort_order',
        'status',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
