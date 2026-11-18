<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FindUserForGoogleLogin;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Login con Google (módulo Login con Google, panel `admin`). No registra usuarios: solo entra
 * quien ya existe y está activo (ver FindUserForGoogleLogin). Todo fallo vuelve al login con un
 * aviso y queda en la actividad.
 */
class GoogleLoginController extends Controller
{
    public function redirect(): RedirectResponse|SymfonyRedirectResponse
    {
        $response = $this->provider()->redirect();

        // Siempre se pide elegir la cuenta: en una computadora compartida no entra la que quedó abierta.
        $url = $response->getTargetUrl();
        $response->setTargetUrl($url.(str_contains($url, '?') ? '&' : '?').'prompt=select_account');

        return $response;
    }

    public function callback(Request $request, FindUserForGoogleLogin $find, ActivityLogger $logger): RedirectResponse
    {
        try {
            $google = $this->provider()->user();
        } catch (Throwable) {
            // Estado inválido, permiso denegado en Google o falla del proveedor.
            return $this->fail($logger, 'No pudimos completar el ingreso con Google.', null);
        }

        try {
            $user = $find->handle($google);
        } catch (ValidationException $e) {
            return $this->fail($logger, $e->validator->errors()->first(), (string) $google->getEmail());
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(Filament::getPanel('admin')->getUrl());
    }

    /**
     * Sin `GOOGLE_REDIRECT_URI`, el callback es la ruta de esta aplicación. Se fija antes de crear
     * el proveedor: tiene que ser la misma URI al redirigir y al canjear el código.
     */
    private function provider(): Provider
    {
        if (blank(config('services.google.redirect'))) {
            config(['services.google.redirect' => route('auth.google.callback')]);
        }

        return Socialite::driver('google');
    }

    /**
     * Registra el intento y vuelve al login con el aviso. Si el usuario existe queda como autor,
     * así un intento sobre un SuperAdmin se oculta al cliente igual que los demás (como LogFailedLogin).
     */
    private function fail(ActivityLogger $logger, string $message, ?string $email): RedirectResponse
    {
        $email = trim((string) $email);
        $user = $email === '' ? null : User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();

        $logger->log(
            ActivityEvent::LoginFailed,
            'Intento de ingreso con Google fallido'.($email !== '' ? ': '.$email : ''),
            properties: ['email' => $email, 'method' => 'google'],
            user: $user,
        );

        return redirect()->to(Filament::getPanel('admin')->getLoginUrl() ?? '/')->with('google_login_error', $message);
    }
}
