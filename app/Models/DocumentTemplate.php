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
        'logo_placements',
        'document_type',
        'is_default',
        'kp_stage',
        'paper_size',
        'status',
        'created_by',
        'updated_by',
        'archived_by',
        'archived_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'logo_placements' => 'array',
        'is_default' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }
}
