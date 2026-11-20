<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Login con Google (módulo `google-login`, solo panel admin). No registra usuarios:
 * entra únicamente quien ya existe, está activo y puede usar el panel.
 */
class GoogleLoginController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->backToLogin('No se pudo iniciar sesión con Google. Probá de nuevo.');
        }

        $user = $this->findUser((string) $googleUser->getId(), (string) $googleUser->getEmail());

        if (! $user instanceof User || ! $user->canAccessPanel($this->panel())) {
            return $this->backToLogin('Esa cuenta de Google no tiene acceso al sistema.');
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => (string) $googleUser->getId()])->save();
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended($this->panel()->getUrl() ?? '/');
    }

    private function findUser(string $googleId, string $email): ?User
    {
        if ($googleId === '') {
            return null;
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user !== null || $email === '') {
            return $user;
        }

        return User::query()->where('email', $email)->whereNull('google_id')->first();
    }

    private function panel(): Panel
    {
        return Filament::getPanel('admin');
    }

    private function backToLogin(string $message): RedirectResponse
    {
        return redirect()
            ->to($this->panel()->getLoginUrl() ?? '/')
            ->with('google_login_error', $message);
    }
}
