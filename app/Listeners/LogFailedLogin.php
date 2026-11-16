<?php

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');
        $user = $event->user instanceof User ? $event->user : null;

        // Solo se guarda el email intentado: nunca la contraseña.
        // Si el usuario existe queda como autor, así un intento sobre un
        // SuperAdmin se oculta igual que el resto de su actividad.
        $this->logger->log(
            ActivityEvent::LoginFailed,
            'Intento de ingreso fallido'.($email !== '' ? ': '.$email : ''),
            properties: ['email' => $email],
            user: $user,
        );
    }
}
