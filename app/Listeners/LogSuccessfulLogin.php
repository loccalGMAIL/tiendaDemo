<?php

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->logger->log(
            ActivityEvent::Login,
            'Ingreso al sistema: '.$event->user->name,
            $event->user,
            user: $event->user,
        );
    }
}
