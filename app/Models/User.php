<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active', 'is_default_seller', 'google_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
            'is_default_seller' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->active) {
            return false;
        }

        return match ($panel->getId()) {
            'superadmin' => $this->role === Role::SuperAdmin,
            'admin' => in_array($this->role, [Role::Admin, Role::Seller], true),
            default => false,
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isSeller(): bool
    {
        return $this->role === Role::Seller;
    }

    /**
     * Único punto donde el SuperAdmin se oculta del cliente: todas las consultas
     * de usuarios del panel `admin` (listados, selectores, filtros) pasan por acá.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleToClient(Builder $query): Builder
    {
        return $query->where('role', '!=', Role::SuperAdmin->value);
    }

    /**
     * Un usuario con operaciones registradas (ventas, cobros o anulaciones) no
     * se borra: se desactiva.
     */
    public function hasOperations(): bool
    {
        return Sale::query()->where('seller_id', $this->getKey())->orWhere('cancelled_by', $this->getKey())->exists()
            || Payment::query()->where('collected_by', $this->getKey())->orWhere('cancelled_by', $this->getKey())->exists();
    }

    protected function activitySubjectLabel(): string
    {
        return 'usuario';
    }
}
