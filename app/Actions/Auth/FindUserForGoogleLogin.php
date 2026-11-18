<?php

namespace App\Actions\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Busca al usuario que quiere entrar con Google (módulo Login con Google). No registra a nadie:
 * solo entra quien ya existe, está activo y tiene acceso al panel del cliente (administrador o
 * vendedor). Se busca por `google_id` y, si no hay, por correo sin distinguir mayúsculas; en ese
 * caso Google tiene que decir que el correo está verificado, y se guarda el `google_id`.
 *
 * Cualquier otro caso (inexistente, inactivo, SuperAdmin, correo sin verificar, cuenta ya
 * vinculada a otra de Google) lanza el mismo error que las credenciales incorrectas, para no
 * revelar quién existe.
 */
class FindUserForGoogleLogin
{
    /**
     * @throws ValidationException
     */
    public function handle(SocialiteUser $google): User
    {
        $googleId = (string) $google->getId();
        $email = mb_strtolower(trim((string) $google->getEmail()));

        if ($googleId === '') {
            throw $this->rejected();
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user === null) {
            $user = $this->findByVerifiedEmail($google, $email, $googleId);
        }

        // Inactivo y SuperAdmin no entran al panel del cliente: mismo error que cualquier otro rechazo.
        if (! $user->canAccessPanel(Filament::getPanel('admin'))) {
            throw $this->rejected();
        }

        if ($user->google_id === null) {
            $user->update(['google_id' => $googleId]);
        }

        return $user;
    }

    /**
     * @throws ValidationException
     */
    private function findByVerifiedEmail(SocialiteUser $google, string $email, string $googleId): User
    {
        if ($email === '' || ! $this->emailVerified($google)) {
            throw $this->rejected();
        }

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        // Una cuenta ya vinculada a otra de Google no se pisa.
        if ($user === null || ($user->google_id !== null && $user->google_id !== $googleId)) {
            throw $this->rejected();
        }

        return $user;
    }

    /**
     * Google informa si verificó el correo (`email_verified`, a veces como texto).
     */
    private function emailVerified(SocialiteUser $google): bool
    {
        $raw = property_exists($google, 'user') && is_array($google->user) ? $google->user : [];

        return filter_var(Arr::get($raw, 'email_verified', Arr::get($raw, 'verified_email', false)), FILTER_VALIDATE_BOOLEAN);
    }

    private function rejected(): ValidationException
    {
        return ValidationException::withMessages(['email' => trans('auth.failed')]);
    }
}
