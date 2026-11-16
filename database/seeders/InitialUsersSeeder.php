<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class InitialUsersSeeder extends Seeder
{
    /**
     * Crea el administrador y el superadmin desde config/platform.php.
     * Se puede correr varias veces: no duplica ni pisa contraseñas ya cambiadas.
     */
    public function run(): void
    {
        $this->seed(config('platform.admin'), Role::Admin, isDefaultSeller: true);
        $this->seed(config('platform.superadmin'), Role::SuperAdmin);
    }

    /**
     * @param  array{name?: ?string, email?: ?string, password?: ?string}  $data
     */
    private function seed(array $data, Role $role, bool $isDefaultSeller = false): void
    {
        if (blank($data['email'] ?? null)) {
            return;
        }

        $user = User::firstOrNew(['email' => $data['email']]);

        if (! $user->exists) {
            $user->name = $data['name'] ?: $role->getLabel();
            $user->password = $data['password'] ?: str()->random(24);
            $user->is_default_seller = $isDefaultSeller;
        }

        $user->role = $role;
        $user->active = true;
        $user->save();
    }
}
