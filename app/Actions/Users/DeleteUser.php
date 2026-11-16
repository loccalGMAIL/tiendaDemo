<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function handle(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'user' => 'No podés eliminar tu propio usuario.',
            ]);
        }

        // Fase 3: si el usuario tiene operaciones registradas, no se borra: se desactiva.

        $user->delete();
    }
}
