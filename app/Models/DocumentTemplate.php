<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'content_html',
        'variables',
        'status',
        'created_by',
        'updated_by',
        'archived_by',
        'archived_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'archived_at' => 'datetime',
    ];

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }
}
