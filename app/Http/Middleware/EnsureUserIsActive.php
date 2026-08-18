<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Bloquea el acceso al resto de la app mientras el usuario no esté
     * aprobado (status=active) — panel de aprobación del Módulo 2, ver
     * docs/14-Modulos-y-Funcionalidades.md.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exempt = $request->routeIs('account.pending', 'logout');

        if ($user !== null && $user->status !== UserStatus::Active && ! $exempt) {
            return redirect()->route('account.pending');
        }

        return $next($request);
    }
}
