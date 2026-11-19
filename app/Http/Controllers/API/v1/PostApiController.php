<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\v1\PostResource;
use App\Models\Group;
use App\Models\Post;
use App\Services\Api\ApiTokenService;
use App\Services\Posts\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * API v1 – Nachrichten (Pädagogen-App und Dienstplan-App): lesen, als gelesen markieren, bestätigen.
 *
 * Kein Fachrecht: sichtbar ist, was in den eigenen Gruppen veröffentlicht ist (PostPolicy).
 * Erstellen/Bearbeiten und Bestätigungslisten bleiben im Web; Verfassende sehen nur die Zahlen.
 */
class PostApiController extends Controller
{
    public function __construct(private readonly PostService $posts, private readonly ApiTokenService $tokens)
    {
    }

    /**
     * GET /api/v1/posts – Nachrichten aus den eigenen Gruppen (offene Bestätigungen zuerst).
     * Filter: group_id, archived (0|1), unread (1), pending (1).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->nutzer($request);
        $daten = $request->validate([
            'group_id' => ['nullable', 'integer'],
            'archived' => ['nullable', 'boolean'],
            'unread' => ['nullable', 'boolean'],
            'pending' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $offen = $this->posts->offeneBestaetigungIds($user);
        $query = Post::query()
            ->sichtbarFuer($user)
            ->where('archived', (bool) ($daten['archived'] ?? false))
            ->with(['author', 'groups', 'media']);

        if (! empty($daten['group_id'])) {
            $group = Group::query()->find($daten['group_id']);
            abort_unless($group && $user->groups()->contains('id', $group->id), 404);
            $query->whereHas('groups', fn ($q) => $q->where('groups.id', $group->id));
        }

        if (! empty($daten['pending'])) {
            $query->whereIn('posts.id', $offen);
        }

        if (! empty($daten['unread'])) {
            $query->where('author_id', '!=', $user->id)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('post_lesestatus')
                    ->whereColumn('post_lesestatus.post_id', 'posts.id')
                    ->where('post_lesestatus.user_id', $user->id)
                    ->whereNotNull('post_lesestatus.gelesen_at'));
        }

        $ids = $offen === [] ? '0' : implode(',', array_map('intval', $offen));
        $seite = $query
            ->orderByRaw("CASE WHEN posts.id IN ($ids) THEN 0 ELSE 1 END")
            ->orderByDesc(DB::raw('COALESCE(veroeffentlicht_at, created_at)'))
            ->paginate((int) ($daten['per_page'] ?? 20));

        $this->posts->mitStatus($seite->getCollection(), $user);

        return response()->json([
            'data' => PostResource::collection($seite->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $seite->currentPage(),
                'last_page' => $seite->lastPage(),
                'per_page' => $seite->perPage(),
                'total' => $seite->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/posts/counts – ungelesen (letzte 14 Tage) und zu bestätigen.
     */
    public function counts(Request $request): JsonResponse
    {
        $zaehler = $this->posts->zaehler($this->nutzer($request));

        return response()->json(['data' => [
            'unread' => $zaehler['ungelesen'],
            'pending_confirmations' => $zaehler['unbestaetigt'],
        ]]);
    }

    /**
     * GET /api/v1/posts/{post} – Detail ohne Nebenwirkung (gelesen markieren: POST read).
     */
    public function show(Request $request, Post $post): JsonResponse
    {
        $user = $this->nutzer($request);
        $this->authorize('view', $post);

        $post->load(['author', 'groups', 'media']);
        $this->posts->mitStatus(collect([$post]), $user);

        return response()->json(['data' => (new PostResource($post))->detail()->resolve($request)]);
    }

    /**
     * POST /api/v1/posts/{post}/read
     */
    public function read(Request $request, Post $post): JsonResponse
    {
        $user = $this->nutzer($request);
        $this->authorize('view', $post);

        $status = $this->posts->alsGelesen($post, $user);

        return response()->json(['data' => ['read_at' => $status?->gelesen_at?->toIso8601String()]]);
    }

    /**
     * POST /api/v1/posts/{post}/confirm – „Gelesen und verstanden“.
     */
    public function confirm(Request $request, Post $post): JsonResponse
    {
        $user = $this->nutzer($request);
        $this->authorize('view', $post);

        if (! $user->can('confirm', $post)) {
            return response()->json(['message' => 'Für diese Nachricht ist keine Bestätigung möglich.'], 422);
        }

        $status = $this->posts->bestaetigen($post, $user);

        return response()->json(['data' => [
            'read_at' => $status->gelesen_at?->toIso8601String(),
            'confirmed_at' => $status->bestaetigt_at?->toIso8601String(),
        ]]);
    }

    /**
     * GET /api/v1/posts/{post}/attachments/{media}
     */
    public function attachment(Request $request, Post $post, Media $media): BinaryFileResponse
    {
        $this->nutzer($request);
        $this->authorize('view', $post);
        abort_unless($media->model_type === Post::class && (int) $media->model_id === $post->id, 404);
        abort_unless(is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.\Illuminate\Support\Str::ascii($media->file_name).'"',
        ]);
    }

    private function nutzer(Request $request): \App\Models\User
    {
        $user = $request->user();
        abort_unless($this->tokens->hasAnyAppAccess($user), 403, 'Kein Zugang zu den Apps.');

        return $user;
    }
}
