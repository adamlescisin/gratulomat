<?php

namespace App\Http\Controllers\Organizer;

use App\Domain\Guest\EventRules;
use App\Domain\Guest\MemoryPresenter;
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
            ],
            'show' => [
                'public_from' => $event->show?->public_from?->toIso8601String(),
                'is_public' => $event->showPublic(),
                'game_enabled' => (bool) $event->show?->game_enabled,
            ],
            'households' => $event->households()->orderBy('label')->get()->map(fn (Household $h) => [
                'id' => $h->id, 'label' => $h->label, 'names' => $h->names, 'relation' => $h->relation,
                'last_seen' => $h->last_seen_at?->toIso8601String(), 'memories' => (int) ($counts[$h->id] ?? 0),
                'link' => $h->link(),
            ]),
            'memories' => MemoryPresenter::all($event, true),
            'media_base' => url($event->slug.'/m'),
        ]);
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
                default => Carbon::parse($v, 'Europe/Prague'),
            };
        }
        if ($request->has('game_enabled')) {
            $data['game_enabled'] = $request->boolean('game_enabled') && EventRules::has($event, 'game');
        }
        $show->update($data);

        return response()->json(['ok' => true]);
    }

    public function updateSettings(Request $request, Event $event): JsonResponse
    {
        $v = $request->validate([
            'invite_text' => 'nullable|string|max:2000',
            'collect_until' => 'nullable|date',
        ]);
        $settings = $event->settings ?? [];
        if (array_key_exists('invite_text', $v)) {
            $settings['invite_text'] = $v['invite_text'];
        }
        $event->settings = $settings;
        if (! empty($v['collect_until'])) {
            $until = Carbon::parse($v['collect_until'], 'Europe/Prague');
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
