<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Po konci viditelnosti (archivu) hosté stránku, Kino ani média nevidí.
 * Organizátor a provozovatel mají přístup dál (administrace, prodloužení archivu).
 */
class EventNotArchived
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');
        if (! $event instanceof Event || ! $event->isArchived() || $request->user()?->organizes($event)) {
            return $next($request);
        }
        if ($request->expectsJson() || $request->is('*/api/*')) {
            return response()->json(['error' => __('guest.archived')], 410);
        }

        return response()->view('event.landing', ['event' => $event, 'notice' => __('guest.archived')], 410);
    }
}
