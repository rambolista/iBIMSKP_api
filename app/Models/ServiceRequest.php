<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    protected $fillable = [
        'request_number', 'verification_code', 'resident_id', 'service_type_id', 'purpose',
        'requirements_notes', 'requested_at', 'released_at', 'status',
        'payment_status', 'approval_status', 'remarks', 'rendered_document_html', 'archived_at', 'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'date',
            'released_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
