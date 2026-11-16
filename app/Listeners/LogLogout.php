<?php

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->logger->log(
            ActivityEvent::Logout,
            'Salida del sistema: '.$event->user->name,
            $event->user,
            user: $event->user,
        );
    }
}
