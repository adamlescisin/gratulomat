<?php

namespace App\Http\Controllers\Event;

use App\Domain\Guest\EventRules;
use App\Domain\Guest\GuestSession;
use App\Domain\Guest\MemoryPresenter;
use App\Domain\Show\OfflinePackage;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Household;
use App\Models\Media;
use Illuminate\Http\Request;

/** Stránka události pro hosty, přihlášení pozvánkou, Kino a média s kontrolou oprávnění. */
class EventPageController extends Controller
{
    public function show(Event $event)
    {
        $household = GuestSession::household($event);
        if (! $household) {
            return response()->view('event.landing', ['event' => $event, 'invalid' => request()->boolean('neplatny')]);
        }

        return response()->view('event.guest', [
            'event' => $event,
            'household' => $household,
            'videoMb' => EventRules::maxVideoMb($event),
            'filesPerMemory' => config('gratulomat.memory_limits.files_per_memory'),
            'i18n' => trans('guest'),
        ]);
    }

    public function invite(Event $event, string $token)
    {
        $ok = GuestSession::login($event, $token);

        return redirect()->to('/'.$event->slug.($ok ? '' : '?neplatny=1'));
    }

    public function login(Request $request, Event $event)
    {
        $ok = GuestSession::login($event, (string) $request->input('kod'));

        return redirect()->to('/'.$event->slug.($ok ? '' : '?neplatny=1'));
    }

    /** Anonymní pozvánka (od Premiéry): jeden společný odkaz, host se představí sám. */
    public function anonForm(Event $event, string $token)
    {
        if (! self::anonValid($event, $token)) {
            return redirect()->to('/'.$event->slug.'?neplatny=1');
        }
        // Hromadný odkaz = vždy nový host. Sdílený počítač či telefon nesmí převzít přihlášení předchozího hosta.
        if (GuestSession::household($event)) {
            GuestSession::logout($event);
            session()->regenerate();
        }

        return response()->view('event.anon', ['event' => $event, 'token' => $token, 'full' => self::anonFull($event)])
            ->header('Cache-Control', 'no-store'); // ani tlačítko Zpět nesmí ukázat formulář předchozího hosta
    }

    public function anonJoin(Request $request, Event $event, string $token)
    {
        if (! self::anonValid($event, $token)) {
            return redirect()->to('/'.$event->slug.'?neplatny=1');
        }
        if (GuestSession::household($event)) {
            GuestSession::logout($event); // odesláno z formuláře hromadného odkazu → nový host
        }
        if (self::anonFull($event)) {
            return back();
        }
        $v = $request->validate([
            'name' => 'required|string|min:2|max:80',
            'relation' => 'nullable|string|max:120',
            'email' => 'nullable|email:rfc|max:191',
        ], ['name.*' => __('guest.anon.name_error'), 'email.*' => __('guest.anon.email_error')]);

        $label = trim(preg_replace('/\s+/u', ' ', strip_tags($v['name'])));
        $household = $event->households()->create([
            'token' => Household::newToken(), 'label' => $label, 'names' => Household::splitNames($label) ?: [$label],
            'relation' => isset($v['relation']) ? trim(strip_tags($v['relation'])) ?: null : null,
            'email' => isset($v['email']) ? mb_strtolower($v['email']) : null,
            'source' => 'anonymous', 'preferred_locale' => app()->getLocale(),
        ]);
        GuestSession::login($event, $household->token);

        return redirect()->to('/'.$event->slug);
    }

    private static function anonValid(Event $event, string $token): bool
    {
        $stored = (string) $event->setting('anon_token');

        return $stored !== '' && hash_equals($stored, $token) && EventRules::has($event, 'anonymous_link');
    }

    private static function anonFull(Event $event): bool
    {
        $limit = $event->limit('guests');

        return $limit !== null && $event->households()->count() >= $limit;
    }

    /** Odhlášení e-mailů k události (odkaz v patičce pozvánky / List-Unsubscribe). */
    public function unsubscribe(Event $event, int $household)
    {
        $event->households()->whereKey($household)->update(['email_opt_out' => true]);

        return response()->view('event.landing', ['event' => $event, 'notice' => __('guest.unsubscribed')]);
    }

    public function logout(Event $event)
    {
        GuestSession::logout($event);

        return redirect()->to('/'.$event->slug);
    }

    public function kino(Event $event)
    {
        if (! self::canWatch($event)) {
            return response()->view('event.landing', ['event' => $event, 'notice' => __('guest.kino_locked')], 403);
        }

        app()->setLocale($event->locale); // Kino běží v primárním jazyce události

        return view('event.kino', ['event' => $event, 'showUrl' => url($event->slug.'/api/show'), 'offline' => false]);
    }

    /** Média jen přes aplikaci; BinaryFileResponse umí HTTP Range (Safari bez něj nepřehraje audio/video). */
    public function media(Event $event, string $memoryId, string $file)
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $memoryId) && preg_match('/^[\w.-]+$/', $file), 404);

        $memory = $event->memories()->find($memoryId);
        abort_unless($memory, 404);

        $household = GuestSession::household($event);
        $organizer = self::isOrganizer($event);
        $recipient = GuestSession::isRecipient($event) && $memory->status === 'visible';
        // oslavenec vidí i soukromá přání (u smutečního rozloučení ne)
        $own = $organizer || ($household && $household->id === $memory->household_id) || ($recipient && $event->handoverIncludesPrivate());
        $canView = $own || $recipient || ($household && $memory->status === 'visible' && $event->wallOpen());
        abort_unless($canView, 404);

        $media = Media::where('memory_id', $memoryId)->where(fn ($q) => $q->where('file', $file)->orWhere('thumb', $file))->first();
        abort_unless($media, 404);
        // soukromé hlasové přání: jen autoři a organizátor
        abort_if($media->kind === 'audio' && ! $memory->wish_public && ! $own, 404);

        $path = $memory->dir().'/'.$file;
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($file, '.thumb.jpg') ? 'image/jpeg' : $media->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    /** Odkaz předání: oslavenec (rodina) vidí prezentaci, všechny vzpomínky včetně soukromých přání a offline balíček. */
    public function handover(Event $event, string $token)
    {
        if (! $event->handoverTokenValid($token)) {
            return response()->view('event.landing', ['event' => $event, 'notice' => __('guest.handover.invalid')], 404);
        }
        if (! GuestSession::isRecipient($event)) {
            GuestSession::loginRecipient($event, $token);
            if (! $event->handover()['opened_at'] && ! self::isOrganizer($event)) {
                $event->update(['settings' => array_replace_recursive($event->settings ?? [], ['handover' => ['opened_at' => now()->toIso8601String()]])]);
            }
        }
        app()->setLocale($event->locale);
        $memories = MemoryPresenter::all($event);
        if (! $event->handoverIncludesPrivate()) {
            $memories = array_map([MemoryPresenter::class, 'stripPrivateWish'], $memories);
        }
        $private = fn ($m) => ! $m['wish_public'] && ($m['wish_text'] || collect($m['media'])->contains('kind', 'audio'));

        return view('event.handover', [
            'event' => $event, 'h' => $event->handover(), 'token' => $token,
            'memories' => $memories,
            'privateCount' => count(array_filter($memories, $private)),
            'people' => count(array_unique(array_merge(...array_map(fn ($m) => $m['authors'], $memories ?: [['authors' => []]])))),
            'canDownload' => EventRules::has($event, 'offline'), // ne 'offline' – to je příznak layoutu pro offline balíček
            'owner' => $event->organizers()->wherePivot('role', 'owner')->first(),
        ]);
    }

    public function handoverOffline(Event $event, string $token, OfflinePackage $package)
    {
        abort_unless($event->handoverTokenValid($token), 404);
        abort_unless(EventRules::has($event, 'offline'), 403);
        @set_time_limit(600);
        $file = $package->build($event, sys_get_temp_dir().'/gratulomat-'.$event->slug.'-'.date('Ymd-His').'.zip', forRecipient: true);

        return response()->download($file, $event->slug.'.zip')->deleteFileAfterSend();
    }

    public static function isOrganizer(Event $event): bool
    {
        return (bool) auth()->user()?->organizes($event);
    }

    /** Videoprezentaci vidí organizátor vždy, hosté až po zpřístupnění. */
    public static function canWatch(Event $event): bool
    {
        return self::isOrganizer($event) || GuestSession::isRecipient($event) || ($event->showPublic() && GuestSession::household($event));
    }
}
