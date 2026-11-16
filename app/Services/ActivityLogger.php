<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Registra un evento en el historial inmutable. Toma usuario, IP y agente
     * del request actual; el usuario puede indicarse (login, intento fallido).
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(
        ActivityEvent $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
    ): ActivityLog {
        $user ??= Auth::user();

        $log = new ActivityLog([
            'event' => $event,
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $log->user_id = $user?->getKey();

        if ($subject !== null) {
            $log->subject()->associate($subject);
        }

        $log->save();

        return $log;
    }
}
