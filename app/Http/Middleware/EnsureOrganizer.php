<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');
        $user = $request->user();
        if (! $user) {
            return $request->expectsJson() ? response()->json(['error' => 'Nepřihlášeno'], 401) : redirect()->guest(route('login'));
        }
        abort_unless($event instanceof Event && $user->organizes($event), 404);

        return $next($request);
    }
}
