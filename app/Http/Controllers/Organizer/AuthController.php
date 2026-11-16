<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Mail\LoginLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/** Přihlášení organizátora odkazem z e-mailu (bez hesla). */
class AuthController extends Controller
{
    public function form()
    {
        return view('organizer.login', ['sent' => session('sent'), 'expired' => request()->boolean('vyprsel')]);
    }

    public function send(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => 'required|email'])['email']));
        $user = User::where('email', $email)->first();
        // Stejná odpověď, ať účet existuje, nebo ne (žádné zjišťování e-mailů).
        if ($user && $user->events()->exists()) {
            Mail::to($user)->send(new LoginLink($user, $user->loginLink()));
        }

        return redirect()->route('login')->with('sent', $email);
    }

    public function link(Request $request, User $user)
    {
        if (! $request->hasValidSignature()) {
            return redirect()->to(route('login').'?vyprsel=1');
        }
        // jednorázovost: podpis odkazu si pamatujeme do jeho vypršení
        $key = 'login-link:'.hash('sha256', (string) $request->query('signature'));
        if (! Cache::add($key, true, now()->addHours(2)) && ! app()->environment('local')) {
            return redirect()->to(route('login').'?vyprsel=1');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $events = $user->events()->get();

        return $events->count() === 1
            ? redirect()->route('organizer.admin', $events->first())
            : redirect()->route('organizer.events');
    }

    public function events()
    {
        return view('organizer.events', ['events' => Auth::user()->events()->orderBy('event_date')->get()]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
