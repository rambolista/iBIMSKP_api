<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'fee', 'requirements', 'processing_days',
        'approval_required', 'status', 'archived_at', 'archived_by', 'document_template_id',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'approval_required' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }
}
