<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Registro de actividad: inmutable. Sin updated_at; no se modifica ni se borra.
 */
#[Fillable(['event', 'description', 'properties', 'ip_address', 'user_agent'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El registro de actividad es inmutable.'));
        static::deleting(fn () => throw new LogicException('El registro de actividad es inmutable.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Oculta al cliente todo lo hecho por un SuperAdmin o sobre un SuperAdmin.
     *
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    public function scopeVisibleToClient(Builder $query): Builder
    {
        $superadmins = User::query()->where('role', Role::SuperAdmin->value)->select('id');

        return $query
            ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $superadmins))
            ->where(fn (Builder $q) => $q
                ->whereNull('subject_type')
                ->orWhere('subject_type', '!=', (new User)->getMorphClass())
                ->orWhereNotIn('subject_id', $superadmins));
    }
}
