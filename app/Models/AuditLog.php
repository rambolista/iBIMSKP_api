<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'actor_id',
        'changes',
        'context',
    ];

    protected $casts = [
        'changes' => 'array',
        'context' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function recordCreated(?User $actor, EloquentModel $model, array $fields = []): void
    {
        $after = $model->getAttributes();
        $trackedFields = self::resolveTrackedFields($fields, $after, []);
        $changes = [];

        foreach ($trackedFields as $field) {
            $to = $after[$field] ?? null;
            if ($to === null) {
                continue;
            }
            $changes[$field] = ['from' => null, 'to' => $to];
        }

        self::write($actor, $model, 'created', $changes === [] ? null : $changes);
    }

    public static function recordUpdated(?User $actor, EloquentModel $model, array $before, array $fields = []): void
    {
        $after = $model->getAttributes();
        $trackedFields = self::resolveTrackedFields($fields, $after, $before);
        $changes = [];

        foreach ($trackedFields as $field) {
            $from = $before[$field] ?? null;
            $to = $after[$field] ?? null;
            if ($from === $to) {
                continue;
            }
            $changes[$field] = ['from' => $from, 'to' => $to];
        }

        if ($changes === []) {
            return;
        }

        self::write($actor, $model, 'updated', $changes);
    }

    public static function recordDeleted(?User $actor, EloquentModel $model, array $before, array $fields = []): void
    {
        $trackedFields = self::resolveTrackedFields($fields, [], $before);
        $changes = [];

        foreach ($trackedFields as $field) {
            $from = $before[$field] ?? null;
            if ($from === null) {
                continue;
            }
            $changes[$field] = ['from' => $from, 'to' => null];
        }

        self::write($actor, $model, 'deleted', $changes === [] ? null : $changes);
    }

    private static function write(?User $actor, EloquentModel $model, string $action, ?array $changes): void
    {
        self::create([
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'actor_id' => $actor?->id,
            'changes' => $changes,
        ]);
    }

    private static function resolveTrackedFields(array $fields, array $after, array $before): array
    {
        $ignored = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password'];
        $candidates = $fields === []
            ? array_keys(array_merge($before, $after))
            : $fields;

        return array_values(array_unique(array_filter($candidates, fn ($field) => ! in_array($field, $ignored, true))));
    }
}
