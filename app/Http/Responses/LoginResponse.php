<?php

namespace App\Http\Responses;

use App\Filament\Pages\PointOfSale;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Adónde va cada uno al ingresar al panel: el vendedor, directo a la Caja; el resto, al
 * escritorio. Si venía de una página puntual (sesión vencida), vuelve a esa.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        return redirect()->intended(self::homeFor($request->user()));
    }

    public static function homeFor(mixed $user): string
    {
        if ($user instanceof User && $user->isSeller()) {
            return PointOfSale::getUrl(panel: 'admin');
        }

        return Filament::getPanel('admin')->getUrl() ?? '/';
    }
}
