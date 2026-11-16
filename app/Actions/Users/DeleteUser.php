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

        if ($user->hasOperations()) {
            throw ValidationException::withMessages([
                'user' => 'Tiene operaciones registradas: desactivalo en lugar de borrarlo.',
            ]);
        }

        $user->delete();
    }
}
