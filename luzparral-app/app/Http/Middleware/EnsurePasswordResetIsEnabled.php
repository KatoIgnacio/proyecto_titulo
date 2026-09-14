<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordResetIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('security.password_reset_enabled'), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
