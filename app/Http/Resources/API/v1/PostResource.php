<?php

namespace App\Http\Resources\API\v1;

use App\Models\Post;
use App\Services\Posts\PostService;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Nachricht für die Apps. Erwartet die Attribute mein_status / muss_bestaetigen (PostService::mitStatus()).
 * Mit `detail` zusätzlich Text (bereinigtes HTML) und Anhänge.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    public bool $detail = false;

    public function detail(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray($request): array
    {
        /** @var Post $post */
        $post = $this->resource;
        $user = $request->user();
        $status = $post->mein_status;
        $frist = $status?->frist ?? $post->bestaetigung_frist;
        $darfListe = $user->can('viewConfirmations', $post);

        $daten = [
            'id' => $post->id,
            'title' => $post->header,
            'summary' => $post->kurztext,
            'has_more' => $post->hatMehrText(),
            'groups' => $post->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values(),
            'author' => ['id' => $post->author_id, 'name' => $post->author->name],
            'is_author' => (int) $post->author_id === (int) $user->id,
            'published' => (bool) $post->released,
            'published_at' => $post->veroeffentlicht_at?->toIso8601String() ?? ($post->released ? $post->created_at?->toIso8601String() : null),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'archived' => (bool) $post->archived,
            'on_dashboard' => (bool) $post->auf_dashboard,
            'visible_until' => $post->sichtbar_bis?->toDateString(),
            'read_at' => $status?->gelesen_at?->toIso8601String(),
            'attachments_count' => $post->media->count(),
            'confirmation' => $post->bestaetigung_erforderlich ? [
                'required' => true,
                'deadline' => $frist?->toDateString(),
                'pending' => (bool) $post->muss_bestaetigen,
                'confirmed_at' => $status?->bestaetigt_at?->toIso8601String(),
                'overdue' => (bool) $post->muss_bestaetigen && $frist !== null && $frist->lt(today()),
                'stats' => $darfListe ? app(PostService::class)->statistik($post) : null,
            ] : null,
        ];

        if ($this->detail) {
            $daten['text_html'] = $post->text_html;
            $daten['attachments'] = $post->media
                ->sortBy('name')
                ->map(fn (Media $m) => [
                    'id' => $m->id,
                    'name' => $m->file_name,
                    'mime_type' => $m->mime_type,
                    'size' => (int) $m->size,
                    'is_image' => $m->collection_name === 'images',
                    'url' => route('api.v1.posts.attachments', [$post, $m]),
                ])
                ->values();
        }

        return $daten;
    }
}
