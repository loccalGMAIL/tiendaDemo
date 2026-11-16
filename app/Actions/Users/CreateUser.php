<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateUser
{
    /**
     * @param  array{name: string, email: string, password: string, role: string, active?: bool, is_default_seller?: bool}  $data
     */
    public function handle(array $data): User
    {
        if (! array_key_exists($data['role'], Role::assignable())) {
            throw ValidationException::withMessages(['role' => 'El rol indicado no es válido.']);
        }

        return DB::transaction(function () use ($data): User {
            if ($data['is_default_seller'] ?? false) {
                User::query()->where('is_default_seller', true)->update(['is_default_seller' => false]);
            }

            return User::create($data);
        });
    }
}
