<?php

namespace App\Http\Controllers\Event;

use App\Domain\Guest\EventRules;
use App\Domain\Guest\GuestSession;
use App\Domain\Guest\MediaStore;
use App\Domain\Guest\MemoryPresenter;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Household;
use App\Models\Media;
use App\Models\Memory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GuestApiController extends Controller
{
    private function household(Event $event): Household
    {
        $h = GuestSession::household($event);
        abort_unless($h, response()->json(['error' => __('guest.err.not_logged_in')], 401));

        return $h;
    }

    public function me(Event $event): JsonResponse
    {
        $h = $this->household($event);
        $mine = $h->memories()->with(['media', 'household'])->latest()->get()->map(fn ($m) => MemoryPresenter::toArray($m));

        $visible = $event->memories()->where('status', 'visible');
        $stats = [
            'memories' => (clone $visible)->count(),
            'photos' => Media::whereIn('memory_id', (clone $visible)->select('id'))->where('kind', 'photo')->count(),
            'households' => (clone $visible)->distinct()->count('household_id'),
            'households_total' => $event->households()->count(),
        ];

        $wall = null;
        if ($event->wallOpen()) {
            $wall = array_values(array_map([MemoryPresenter::class, 'stripPrivateWish'],
                array_filter(MemoryPresenter::all($event), fn ($m) => $m['household']['id'] !== $h->id)));
        }

        return response()->json([
            'household' => ['label' => $h->label, 'names' => $h->names],
            'config' => [
                'type' => $event->type,
                'funeral' => $event->isFuneral(),
                'celebrant' => $event->recipient_name,
                'forms' => $event->recipient_forms ?? [],
                'birth_year' => $event->start_year ?? $event->refDate()->year,
                'timeline' => $event->timeline(),
                'party_date' => $event->event_date?->toIso8601String(),
                'party_place' => $event->event_place,
                'title' => $event->title,
                'tags' => EventRules::tags($event),
                'submissions_open' => $event->submissionsOpen(),
                'submissions_close' => $event->collect_until?->toIso8601String(),
                'max_photos' => config('gratulomat.memory_limits.files_per_memory'),
                'max_video_mb' => EventRules::maxVideoMb($event),
                'wishes' => EventRules::has($event, 'wishes'),
                'kino_open' => $event->showPublic(),
            ],
            'mine' => $mine,
            'stats' => $stats,
            'wall' => $wall,
            'media_base' => url($event->slug.'/m'),
        ]);
    }

    public function save(Request $request, Event $event, ?string $memoryId = null): JsonResponse
    {
        $h = $this->household($event);
        if (! $event->submissionsOpen()) {
            return response()->json(['error' => __('guest.err.closed')], 403);
        }

        $memory = null;
        if ($memoryId) {
            $memory = $h->memories()->find($memoryId);
            abort_unless($memory, response()->json(['error' => __('guest.err.not_found')], 404));
        }

        $authors = array_values(array_intersect($h->names, (array) $request->input('authors', [])));
        $authors = $authors ?: $h->names;
        $title = mb_substr(trim((string) $request->input('title')), 0, 140);
        $body = mb_substr(trim((string) $request->input('body')), 0, 6000);
        $year = (int) $request->input('year');
        $minYear = $event->start_year ?? 1900;
        $year = ($year >= $minYear && $year <= (int) date('Y')) ? $year : null;
        $tags = array_values(array_intersect(EventRules::tags($event), (array) $request->input('tags', [])));
        $wishesOn = EventRules::has($event, 'wishes');
        $wishPublic = $wishesOn && $request->boolean('wish_public');
        $wishText = $wishesOn ? mb_substr(trim((string) $request->input('wish_text')), 0, 3000) : '';

        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('files', []), fn ($f) => $f instanceof UploadedFile));
        $audio = $wishesOn && $request->file('audio') instanceof UploadedFile ? $request->file('audio') : null;
        $removeIds = array_map('intval', (array) $request->input('remove_media', []));

        $existing = $memory ? $memory->media()->count() - count($removeIds) : 0;
        if ($body === '' && $title === '' && $wishText === '' && ! $files && ! $audio && $existing <= 0) {
            return response()->json(['error' => __('guest.err.empty')], 422);
        }
        if (count($files) + max(0, $existing) > config('gratulomat.memory_limits.files_per_memory') + 2) {
            return response()->json(['error' => __('guest.err.too_many')], 422);
        }
        if (($limit = EventRules::photoLimit($event)) !== null) {
            $used = Media::whereIn('memory_id', $event->memories()->select('id'))->where('kind', 'photo')->count();
            if ($used - count($removeIds) + count($files) > $limit) {
                return response()->json(['error' => __('guest.err.photo_limit', ['n' => $limit])], 422);
            }
        }

        $isNew = $memory === null;
        DB::beginTransaction();
        try {
            $data = ['authors' => $authors, 'title' => $title ?: null, 'body' => $body ?: null, 'year' => $year,
                'tags' => $tags, 'wish_public' => $wishPublic, 'wish_text' => $wishText ?: null];
            if (! $event->timeline()) {
                // bez časové osy se rok nezadává; dříve zadaný rok zůstane pro případ, že ji organizátor zase zapne
                $data = $isNew ? [...$data, 'year' => null] : Arr::except($data, 'year');
            }
            if ($isNew) {
                $memory = Memory::create([...$data, 'event_id' => $event->id, 'household_id' => $h->id]);
            } else {
                $memory->update($data);
                foreach ($memory->media()->whereIn('id', $removeIds)->get() as $row) {
                    foreach ([$row->file, $row->thumb] as $f) {
                        if ($f) {
                            @unlink($memory->dir()."/$f");
                        }
                    }
                    $row->delete();
                }
            }
            $memory->setRelation('event', $event);
            $sort = (int) $memory->media()->max('sort') + ($memory->media()->exists() ? 1 : 0);
            $store = app(MediaStore::class);
            $uploads = array_map(fn ($f) => [$f, 'auto'], $files);
            if ($audio) {
                $uploads[] = [$audio, 'audio'];
            }
            foreach ($uploads as [$f, $kind]) {
                $r = $store->store($memory, $f, $kind, $sort, EventRules::maxVideoMb($event), config('gratulomat.memory_limits.audio_mb'));
                $memory->media()->create([...$r, 'sort' => $sort++]);
            }
            DB::commit();
        } catch (RuntimeException $e) {
            DB::rollBack();
            if ($isNew && $memory) {
                File::deleteDirectory($event->mediaDir($memory->id));
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        MemoryPresenter::writeSidecar($memory);

        return response()->json(MemoryPresenter::toArray($memory->fresh(['media', 'household'])));
    }

    public function destroy(Event $event, string $memoryId): JsonResponse
    {
        $h = $this->household($event);
        $memory = $h->memories()->find($memoryId);
        abort_unless($memory, response()->json(['error' => __('guest.err.not_found')], 404));
        if (! $event->submissionsOpen()) {
            return response()->json(['error' => __('guest.err.closed')], 403);
        }
        $dir = $event->mediaDir($memory->id);
        $memory->delete();
        File::deleteDirectory($dir);

        return response()->json(['ok' => true]);
    }

    public function show(Event $event): JsonResponse
    {
        if (! EventPageController::canWatch($event)) {
            return response()->json(['error' => __('guest.kino_locked')], 403);
        }

        // oslavenec (odkaz předání) dostane i soukromá přání – jsou určená jemu
        $recipient = GuestSession::isRecipient($event) && ! EventPageController::isOrganizer($event);

        return response()->json(MemoryPresenter::show($event, url($event->slug.'/m'), $recipient && $event->handoverIncludesPrivate()) + ['recipient' => $recipient]);
    }
}
