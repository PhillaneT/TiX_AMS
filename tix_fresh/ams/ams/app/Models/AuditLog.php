<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'payload',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $entity = null, array $payload = []): void
    {
        static::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id'   => $entity?->getKey(),
            'payload'     => $payload ?: null,
            'ip'          => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
