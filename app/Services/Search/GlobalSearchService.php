<?php

namespace App\Services\Search;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\Protocol;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Globale Suche über Nachrichten, Gruppen-Themen, Meetings sowie Themen und
 * Protokolle aus Meetings, an denen der Nutzer teilnimmt – auch wenn er nicht
 * Mitglied der Gruppe ist, zu der ein Thema gehört (freie Meetings).
 */
class GlobalSearchService
{
    private const LIMIT_PER_SECTION = 30;

    /**
     * @return array<int, array{key: string, title: string, icon: string, items: array}>
     */
    public function search(User $user, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 3) {
            return [];
        }

        $sections = [];
        $seenThemeIds = collect();

        if ($posts = $this->posts($user, $term)) {
            $sections[] = ['key' => 'posts', 'title' => 'Nachrichten', 'icon' => 'far fa-newspaper', 'items' => $posts];
        }

        if ($meetings = $this->meetings($user, $term)) {
            $sections[] = ['key' => 'meetings', 'title' => 'Meetings', 'icon' => 'far fa-calendar-alt', 'items' => $meetings];
        }

        foreach ($user->groups_rel as $group) {
            $items = $this->groupThemes($group, $term);
            if (! empty($items)) {
                $seenThemeIds = $seenThemeIds->merge(array_column($items, 'id'));
                $sections[] = ['key' => 'group-' . $group->id, 'title' => $group->name, 'icon' => 'fas fa-users', 'items' => $items];
            }
        }

        $meetingThemes = $this->meetingThemes($user, $term, $seenThemeIds);
        if (! empty($meetingThemes)) {
            $sections[] = ['key' => 'meeting-themes', 'title' => 'Themen & Protokolle aus Meetings', 'icon' => 'fas fa-comments', 'items' => $meetingThemes];
        }

        return $sections;
    }

    private function posts(User $user, string $term): array
    {
        $like = '%' . $term . '%';

        return $user->posts()
            ->where(fn ($q) => $q->where('header', 'like', $like)->orWhere('text', 'like', $like))
            ->latest()
            ->limit(self::LIMIT_PER_SECTION)
            ->get()
            ->unique('id')
            ->map(fn ($post) => [
                'id'      => $post->id,
                'title'   => $post->header,
                'date'    => optional($post->created_at)->format('d.m.Y'),
                'url'     => null,
                'meta'    => null,
                'snippet' => $this->snippet($post->text, $term),
            ])
            ->values()
            ->all();
    }

    private function meetings(User $user, string $term): array
    {
        $like = '%' . $term . '%';

        return Meeting::query()
            ->visibleTo($user)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))
            ->with('group')
            ->orderByDesc('date')
            ->limit(self::LIMIT_PER_SECTION)
            ->get()
            ->map(fn (Meeting $m) => [
                'id'      => $m->id,
                'title'   => $m->title,
                'date'    => $m->date->format('d.m.Y'),
                'url'     => route('meetings.show', $m),
                'meta'    => $m->contextLabel() . ($m->cancelled ? ' · abgesagt' : ''),
                'snippet' => $this->snippet($m->description, $term),
            ])
            ->all();
    }

    private function groupThemes(Group $group, string $term): array
    {
        $themes = $this->matchingThemes(Theme::query()->where('group_id', $group->id), $term)
            ->orderByDesc('date')
            ->limit(self::LIMIT_PER_SECTION)
            ->get();

        return $themes->map(fn (Theme $t) => $this->themeItem(
            $t,
            url($group->name . '/themes/' . $t->id),
            $t->completed ? 'abgeschlossen' : null,
            $term
        ))->all();
    }

    /**
     * Themen aus sichtbaren Meetings (freie Themen und Themen fremder Gruppen).
     */
    private function meetingThemes(User $user, string $term, Collection $excludeIds): array
    {
        $meetingIds = Meeting::query()->visibleTo($user)->pluck('id');
        if ($meetingIds->isEmpty()) {
            return [];
        }

        $themes = $this->matchingThemes(
            Theme::query()
                ->whereNotIn('id', $excludeIds->unique()->all())
                ->whereHas('meetings', fn ($q) => $q->whereIn('meetings.id', $meetingIds)),
            $term
        )
            ->with(['group', 'meetings' => fn ($q) => $q->whereIn('meetings.id', $meetingIds)->orderByDesc('date')])
            ->orderByDesc('date')
            ->limit(self::LIMIT_PER_SECTION)
            ->get();

        return $themes->map(function (Theme $t) use ($term) {
            $meeting = $t->meetings->first();

            return $this->themeItem(
                $t,
                route('meetings.themes.show', [$meeting, $t]),
                $meeting->title . ' · ' . ($t->group?->name ?? 'freies Thema'),
                $term
            );
        })->all();
    }

    /**
     * Themen, deren Titel/Ziel/Information oder eines ihrer Protokolle den Begriff enthält.
     */
    private function matchingThemes(Builder $query, string $term): Builder
    {
        $like = '%' . $term . '%';

        return $query
            ->where(function (Builder $q) use ($like, $term) {
                $q->where('theme', 'like', $like)
                    ->orWhere('goal', 'like', $like)
                    ->orWhere('information', 'like', $like)
                    ->orWhereHas('protocols', fn (Builder $p) => $this->matchProtocol($p, $term));
            })
            ->with(['protocols' => fn ($p) => $this->matchProtocol($p, $term)->latest()]);
    }

    /**
     * Volltextsuche in Protokollen (MySQL FULLTEXT), sonst LIKE.
     */
    private function matchProtocol($query, string $term)
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return $query->whereRaw('MATCH (protocol) AGAINST (? IN BOOLEAN MODE)', [$this->booleanTerm($term)]);
        }

        return $query->where('protocol', 'like', '%' . $term . '%');
    }

    private function booleanTerm(string $term): string
    {
        return collect(preg_split('/\s+/', $term))
            ->map(fn ($word) => preg_replace('/[+\-><()~*"@]+/', '', $word))
            ->filter(fn ($word) => mb_strlen($word) >= 2)
            ->map(fn ($word) => '+' . $word . '*')
            ->implode(' ') ?: $term;
    }

    private function themeItem(Theme $theme, string $url, ?string $meta, string $term): array
    {
        /** @var Protocol|null $protocol */
        $protocol = $theme->relationLoaded('protocols') ? $theme->protocols->first() : null;

        $snippet = $this->snippet($theme->theme . ' ' . $theme->goal . ' ' . $theme->information, $term, false);
        $source  = null;
        if ($protocol && ! Str::contains(Str::lower($theme->theme . ' ' . $theme->goal), Str::lower($term))) {
            $snippet = $this->snippet($protocol->protocol, $term);
            $source  = 'Protokoll vom ' . $protocol->created_at->format('d.m.Y');
        }

        return [
            'id'      => $theme->id,
            'title'   => $theme->theme,
            'date'    => optional($theme->date)->format('d.m.Y'),
            'url'     => $url,
            'meta'    => collect([$meta, $source])->filter()->implode(' · ') ?: null,
            'snippet' => $snippet,
        ];
    }

    /**
     * Kurzer Textauszug rund um den ersten Treffer (ohne HTML).
     */
    private function snippet(?string $text, string $term, bool $fallbackToStart = true): ?string
    {
        $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $text))));
        if ($plain === '') {
            return null;
        }

        $pos = mb_stripos($plain, $term);
        if ($pos === false) {
            return $fallbackToStart ? Str::limit($plain, 160) : null;
        }

        $start  = max(0, $pos - 60);
        $excerpt = mb_substr($plain, $start, 180);

        return ($start > 0 ? '… ' : '') . $excerpt . (mb_strlen($plain) > $start + 180 ? ' …' : '');
    }
}
