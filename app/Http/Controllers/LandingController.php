<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsLandingData;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Landing pública. El diseño lo define el tema elegido por el SuperAdmin; el
 * contenido y las secciones visibles, el administrador del cliente.
 */
class LandingController extends Controller
{
    use BuildsLandingData;

    public function __invoke(Request $request): View
    {
        $data = $this->landingData($request);

        return view($data['appearance']->theme()['views'].'.index', $data);
    }
}
