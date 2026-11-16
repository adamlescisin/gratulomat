<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.worker.token');
        abort_unless($token !== '' && hash_equals($token, (string) $request->bearerToken()), 401);

        return $next($request);
    }
}
