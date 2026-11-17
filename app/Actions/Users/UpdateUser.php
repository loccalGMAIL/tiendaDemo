<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $withSuperAdmin  Solo el panel de la plataforma puede asignar el rol SuperAdmin.
     */
    public function handle(User $user, array $data, bool $withSuperAdmin = false): User
    {
        if (isset($data['role']) && ! array_key_exists($data['role'], Role::assignable($withSuperAdmin))) {
            throw ValidationException::withMessages(['role' => 'El rol indicado no es válido.']);
        }

        // Contraseña vacía = no cambiarla.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return DB::transaction(function () use ($user, $data): User {
            if ($data['is_default_seller'] ?? false) {
                User::query()
                    ->whereKeyNot($user->getKey())
                    ->where('is_default_seller', true)
                    ->update(['is_default_seller' => false]);
            }

            $user->update($data);

            return $user;
        });
    }
}
