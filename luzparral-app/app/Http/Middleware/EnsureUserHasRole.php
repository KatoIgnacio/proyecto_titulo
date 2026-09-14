<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(
            static fn (string $role): ?UserRole => UserRole::tryFrom($role),
            $roles,
        );

        if (in_array(null, $allowedRoles, true)) {
            throw new LogicException('La ruta contiene un rol de usuario desconocido.');
        }

        $user = $request->user();

        abort_unless(
            $user !== null && $user->hasRole(...$allowedRoles),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
