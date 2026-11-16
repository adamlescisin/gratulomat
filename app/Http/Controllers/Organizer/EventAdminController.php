<?php

namespace App\Http\Controllers\Organizer;

use App\Domain\Guest\EventRules;
use App\Domain\Guest\GuestImport;
use App\Domain\Guest\MemoryPresenter;
use App\Domain\Guest\Themes;
use App\Domain\Show\OfflinePackage;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/** Administrace organizátora: /{slug}/admin (specifikace „Administrace organizátora“). */
class EventAdminController extends Controller
{
    public function page(Event $event)
    {
        return view('organizer.admin', ['event' => $event]);
    }

    public function state(Event $event): JsonResponse
    {
        $counts = $event->memories()->selectRaw('household_id, count(*) as n')->groupBy('household_id')->pluck('n', 'household_id');

        return response()->json([
            'event' => [
                'slug' => $event->slug, 'title' => $event->title, 'type' => $event->type, 'plan' => $event->plan,
                'celebrant' => $event->recipient_name, 'funeral' => $event->isFuneral(),
                'event_date' => $event->event_date->toIso8601String(),
                'collect_until' => $event->collect_until?->toIso8601String(),
                'archive_until' => $event->archive_until?->toDateString(),
                'guest_limit' => $event->limit('guests'),
                'invite_text' => $event->setting('invite_text') ?: $this->defaultInvite($event),
                'game_available' => EventRules::has($event, 'game'),
                'offline_available' => EventRules::has($event, 'offline'),
                'import_available' => EventRules::has($event, 'import'),
                'theme' => $event->themeKey(), 'mode' => $event->themeMode(), 'themes' => Themes::forEvent($event),
                'anon_available' => EventRules::has($event, 'anonymous_link'),
                'anon_link' => EventRules::has($event, 'anonymous_link') && $event->setting('anon_token') ? route('event.anon', [$event, $event->setting('anon_token')]) : null,
            ],
            'show' => [
                'public_from' => $event->show?->public_from?->toIso8601String(),
                'is_public' => $event->showPublic(),
                'auto_share' => (bool) $event->setting('auto_share'),
                'auto_share_at' => ($event->collect_until ?? $event->event_date)?->toIso8601String(),
                'game_enabled' => (bool) $event->show?->game_enabled,
            ],
            'households' => $event->households()->orderBy('label')->get()->map(fn (Household $h) => [
                'id' => $h->id, 'label' => $h->label, 'source' => $h->source, 'email' => $h->email, 'names' => $h->names, 'relation' => $h->relation,
                'last_seen' => $h->last_seen_at?->toIso8601String(), 'memories' => (int) ($counts[$h->id] ?? 0),
                'link' => $h->link(),
            ]),
            'memories' => MemoryPresenter::all($event, true),
            'media_base' => url($event->slug.'/m'),
        ]);
    }

    /** Import ze souboru (CSV, Excel) – od tarifu Premiéra. */
    public function import(Request $request, Event $event): JsonResponse
    {
        if (! EventRules::has($event, 'import')) {
            return response()->json(['error' => 'Import seznamu hostů je od tarifu Premiéra.'], 403);
        }
        $request->validate(['file' => 'required|file|max:2048|mimes:csv,txt,xlsx'], [
            'file.mimes' => 'Nahrajte soubor CSV nebo Excel (.xlsx).', 'file.max' => 'Soubor může mít nejvýš 2 MB.',
        ]);
        try {
            $rows = GuestImport::rows($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        if (! $rows) {
            return response()->json(['error' => 'V souboru jsme nenašli žádné hosty.'], 422);
        }

        return response()->json([...GuestImport::import($event, $rows), 'limit' => $event->limit('guests')]);
    }

    /** Anonymní pozvánka – zapnout / nový odkaz / vypnout (od Premiéry). */
    public function anonLink(Request $request, Event $event): JsonResponse
    {
        if (! EventRules::has($event, 'anonymous_link')) {
            return response()->json(['error' => 'Anonymní pozvánka je od tarifu Premiéra.'], 403);
        }
        $settings = $event->settings ?? [];
        $settings['anon_token'] = $request->input('action') === 'disable' ? null : Household::newToken(16);
        $event->settings = $settings;
        $event->save();

        return response()->json(['anon_link' => $settings['anon_token'] ? route('event.anon', [$event, $settings['anon_token']]) : null]);
    }

    public function addHouseholds(Request $request, Event $event): JsonResponse
    {
        $limit = $event->limit('guests');
        $added = 0;
        $skipped = 0;
        foreach (preg_split('/\R/u', (string) $request->input('lines')) as $line) {
            [$label, $rel] = array_map('trim', explode(';', $line, 2) + [1 => '']);
            if ($label === '') {
                continue;
            }
            if ($limit !== null && $event->households()->count() >= $limit) {
                $skipped++;

                continue;
            }
            $event->households()->create([
                'token' => Household::newToken(), 'label' => mb_substr($label, 0, 160),
                'names' => Household::splitNames($label), 'relation' => $rel ? mb_substr($rel, 0, 160) : null,
            ]);
            $added++;
        }

        return response()->json(['added' => $added, 'skipped' => $skipped, 'limit' => $limit]);
    }

    public function updateHousehold(Request $request, Event $event, int $household): JsonResponse
    {
        $h = $event->households()->findOrFail($household);
        if ($request->boolean('delete')) {
            foreach ($h->memories as $m) {
                File::deleteDirectory($event->mediaDir($m->id));
            }
            $h->delete();
        } elseif ($request->boolean('new_token')) {
            $h->update(['token' => Household::newToken()]);
        } else {
            $label = trim((string) $request->input('label'));
            abort_if($label === '', 422);
            $h->update(['label' => mb_substr($label, 0, 160), 'names' => Household::splitNames($label),
                'relation' => trim((string) $request->input('relation')) ?: null]);
        }

        return response()->json(['ok' => true]);
    }

    public function updateMemory(Request $request, Event $event, string $memory): JsonResponse
    {
        $m = $event->memories()->findOrFail($memory);
        if ($request->boolean('delete')) {
            $dir = $event->mediaDir($m->id);
            $m->delete();
            File::deleteDirectory($dir);
        } elseif ($request->has('status')) {
            $m->update(['status' => $request->input('status') === 'hidden' ? 'hidden' : 'visible']);
            MemoryPresenter::writeSidecar($m);
        }

        return response()->json(['ok' => true]);
    }

    /** Zpřístupnění videoprezentace hostům: hned, k datu, nebo zavřít. */
    public function updateShow(Request $request, Event $event): JsonResponse
    {
        $show = $event->show()->firstOrCreate([]);
        $data = [];
        if ($request->has('public_from')) {
            $v = $request->input('public_from');
            $data['public_from'] = match (true) {
                $v === 'now' => now(),
                $v === null || $v === '' => null,
                default => Carbon::parse($v, 'Europe/Prague')->utc(),
            };
        }
        if ($request->has('game_enabled')) {
            $data['game_enabled'] = $request->boolean('game_enabled') && EventRules::has($event, 'game');
        }
        $show->update($data);
        // „Zavřít pro hosty“ vypne i automatické nasdílení po uzávěrce
        if (array_key_exists('public_from', $data) && $data['public_from'] === null && $event->setting('auto_share')) {
            $event->settings = [...($event->settings ?? []), 'auto_share' => false];
            $event->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateSettings(Request $request, Event $event): JsonResponse
    {
        $v = $request->validate([
            'invite_text' => 'nullable|string|max:2000',
            'collect_until' => 'nullable|date',
            'title' => 'sometimes|required|string|max:140',
            'theme' => 'sometimes|string|in:'.implode(',', array_keys(Themes::ALL)),
            'mode' => 'sometimes|in:light,dark',
            'auto_share' => 'sometimes|boolean',
            'recipient_name' => 'sometimes|required|string|max:120',
        ], ['title.required' => 'Název události nesmí být prázdný.', 'recipient_name.required' => 'Jméno nesmí být prázdné.']);
        if (isset($v['title'])) {
            $event->title = trim($v['title']);
        }
        if (isset($v['theme']) || isset($v['mode'])) {
            $theme = $v['theme'] ?? $event->themeKey();
            $mode = $v['mode'] ?? $event->themeMode();
            if (! Themes::allowed($event, $theme, $mode)) {
                return response()->json(['error' => 'Tuto šablonu nebo tmavý režim váš tarif neobsahuje (od tarifu Párty).'], 422);
            }
            $event->theme = $theme;
        }
        if (isset($v['recipient_name']) && trim($v['recipient_name']) !== $event->recipient_name) {
            $event->recipient_name = trim($v['recipient_name']);
            $event->refreshRecipientForms();
        }
        $settings = $event->settings ?? [];
        if (isset($mode)) {
            $settings['mode'] = $mode;
        }
        if (array_key_exists('auto_share', $v)) {
            $settings['auto_share'] = (bool) $v['auto_share'];
        }
        if (array_key_exists('invite_text', $v)) {
            $settings['invite_text'] = $v['invite_text'];
        }
        $event->settings = $settings;
        if (! empty($v['collect_until'])) {
            $until = Carbon::parse($v['collect_until'], 'Europe/Prague')->utc();
            $maxDays = $event->limit('collect_days');
            $max = $event->isFuneral() ? null : ($maxDays ? $event->created_at->copy()->addDays($maxDays)->max($event->event_date) : null);
            abort_if($max && $until->gt($max), response()->json(['error' => 'Delší sběr tarif neumožňuje.'], 422));
            $event->collect_until = $until;
        }
        $event->save();

        return response()->json(['ok' => true]);
    }

    public function offline(Event $event, OfflinePackage $package)
    {
        abort_unless(EventRules::has($event, 'offline'), 403, 'Offline balíček je od tarifu Párty.');
        @set_time_limit(600);
        $file = $package->build($event, sys_get_temp_dir().'/gratulomat-'.$event->slug.'-'.date('Ymd-His').'.zip');

        return response()->download($file)->deleteFileAfterSend();
    }

    private function defaultInvite(Event $event): string
    {
        $key = 'organizer.invite.'.(trans()->has('organizer.invite.'.$event->type, $event->locale) ? $event->type : 'default');

        return __($key, $event->textVars(), $event->locale);
    }
}
