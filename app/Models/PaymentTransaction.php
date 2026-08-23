<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'transaction_number',
        'resident_id',
        'payor_name',
        'transaction_type',
        'amount',
        'status',
        'or_number',
        'paid_at',
        'processed_by_user_id',
        'remarks',
        'void_reason',
        'refund_reason',
        'source_module',
        'source_id',
        'source_ref',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentTransactionLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public static function generateTransactionNumber(): string
    {
        $year = now()->year;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = sprintf('TXN-%d-%06d', $year, random_int(0, 999999));
            if (! static::query()->where('transaction_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('TXN-%d-%06d', $year, now()->timestamp % 1000000);
    }

    public static function generateOrNumber(): string
    {
        $highest = static::query()
            ->whereNotNull('or_number')
            ->get(['or_number'])
            ->map(fn ($row) => is_numeric($row->or_number) ? (int) $row->or_number : null)
            ->filter()
            ->max();

        return str_pad((string) ((int) ($highest ?? 0) + 1), 7, '0', STR_PAD_LEFT);
    }
}
