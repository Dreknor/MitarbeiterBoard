<?php

namespace App\Http\Controllers;

use App\Exports\PostBestaetigungenExport;
use App\Http\Requests\Posts\PostRequest;
use App\Mail\newPostsMail;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use App\Services\Posts\PostService;
use App\Support\PdfLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nachrichten (Dashboard-Karte, Gruppen-Seite „Nachrichten“, Detail, Lesebestätigung).
 * Logik: PostService, Zugriff: PostPolicy.
 */
class PostsController extends Controller
{
    public function __construct(private readonly PostService $posts)
    {
    }

    public function index(): RedirectResponse
    {
        return redirect(url('/'));
    }

    /**
     * GET {groupname}/nachrichten – Nachrichten einer Gruppe (?archiv=1 für das Archiv).
     */
    public function gruppe(Request $request, string $groupname): View|RedirectResponse
    {
        $group = Group::query()->where('name', $groupname)->first();
        $user = $request->user();

        if (! $group || ! $user->groups()->contains('id', $group->id)) {
            return redirect(url('/'))->with(['type' => 'warning', 'Meldung' => 'Kein Zugriff auf diese Gruppe']);
        }

        $archiv = $request->boolean('archiv');

        return view('posts.gruppe', [
            'group' => $group,
            'archiv' => $archiv,
            'posts' => $this->posts->gruppenNachrichten($group, $user, $archiv),
            'darfPosten' => $this->posts->zielGruppen($user)->contains('id', $group->id),
            'zaehler' => $this->posts->zaehler($user),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $this->posts->darfPosten($user)) {
            return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Berechtigung fehlt']);
        }

        $vorauswahl = array_filter([(int) $request->query('gruppe')]);

        return view('posts.form', [
            'post' => new Post(['auf_dashboard' => true]),
            'gruppen' => $this->posts->zielGruppen($user),
            'ausgewaehlt' => $vorauswahl,
            'darfBestaetigung' => $this->posts->darfBestaetigungAnfordern($user),
            'zurueck' => $this->zurueckUrl($request),
        ]);
    }

    public function store(PostRequest $request): RedirectResponse|JsonResponse
    {
        $post = $this->posts->speichern($request->user(), $request->daten(), $request->file('files', []));

        if ($request->expectsJson()) {
            return response()->json(['id' => $post->id, 'url' => $post->url()], 201);
        }

        return redirect($post->url())->with([
            'type' => 'success',
            'Meldung' => $post->released ? 'Nachricht veröffentlicht' : 'Entwurf gespeichert',
        ]);
    }

    /**
     * GET posts/{post} – Detail; markiert die Nachricht als gelesen.
     */
    public function show(Request $request, Post $post): View
    {
        $this->authorize('view', $post);
        $user = $request->user();

        $post->load(['author', 'groups', 'media']);
        $status = $this->posts->alsGelesen($post, $user);

        return view('posts.show', [
            'post' => $post,
            'status' => $status,
            'mussBestaetigen' => $user->can('confirm', $post) && $status?->bestaetigt_at === null,
            'statistik' => $user->can('viewConfirmations', $post) ? $this->posts->statistik($post) : null,
            'darfHeuteErinnern' => $this->posts->darfHeuteErinnern($post),
            'zurueck' => $this->zurueckUrl($request),
        ]);
    }

    public function edit(Request $request, Post $post): View
    {
        $this->authorize('update', $post);
        $user = $request->user();

        $gruppen = $this->posts->zielGruppen($user)
            ->concat($post->groups)
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('posts.form', [
            'post' => $post->load('groups', 'media'),
            'gruppen' => $gruppen,
            'ausgewaehlt' => $post->groups->pluck('id')->all(),
            'darfBestaetigung' => $this->posts->darfBestaetigungAnfordern($user),
            'zurueck' => $this->zurueckUrl($request, $post->url()),
        ]);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $post = $this->posts->speichern(
            $request->user(),
            $request->daten(),
            $request->file('files', []),
            $post,
            $request->boolean('bestaetigungen_zuruecksetzen')
        );

        return redirect($post->url())->with(['type' => 'success', 'Meldung' => 'Nachricht gespeichert']);
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $geloescht = $this->posts->loeschen($post);

        return redirect(url('/'))->with([
            'type' => 'success',
            'Meldung' => $geloescht ? 'Entwurf gelöscht' : 'Veröffentlichte Nachricht archiviert',
        ]);
    }

    public function release(Post $post): RedirectResponse
    {
        $this->authorize('release', $post);
        $this->posts->veroeffentlichen($post);

        return redirect()->back()->with(['type' => 'success', 'Meldung' => 'Nachricht veröffentlicht']);
    }

    public function archive(Post $post): RedirectResponse
    {
        $this->authorize('archive', $post);
        $this->posts->archivieren($post);

        return redirect()->back()->with(['type' => 'success', 'Meldung' => 'Nachricht archiviert']);
    }

    public function restore(Post $post): RedirectResponse
    {
        $this->authorize('restore', $post);
        $this->posts->wiederherstellen($post);

        return redirect()->back()->with(['type' => 'success', 'Meldung' => 'Nachricht wiederhergestellt']);
    }

    public function confirm(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $this->authorize('confirm', $post);
        $status = $this->posts->bestaetigen($post, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['bestaetigt_at' => $status->bestaetigt_at->toIso8601String()]);
        }

        return redirect()->back()->with(['type' => 'success', 'Meldung' => 'Kenntnisnahme bestätigt']);
    }

    public function remind(Post $post): RedirectResponse
    {
        $this->authorize('remind', $post);
        $anzahl = $this->posts->erinnern($post);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => $anzahl === 0 ? 'Alle haben bereits bestätigt' : $anzahl.' '.($anzahl === 1 ? 'Person' : 'Personen').' erinnert',
        ]);
    }

    /**
     * GET posts/{post}/bestaetigungen – Bestätigungsliste.
     */
    public function confirmations(Request $request, Post $post): View
    {
        $this->authorize('viewConfirmations', $post);

        return view('posts.bestaetigungen', [
            'post' => $post->load('groups', 'author'),
            'liste' => $this->posts->bestaetigungsliste($post),
            'statistik' => $this->posts->statistik($post),
            'darfHeuteErinnern' => $this->posts->darfHeuteErinnern($post),
        ]);
    }

    public function confirmationsExcel(Post $post): BinaryFileResponse
    {
        $this->authorize('viewConfirmations', $post);

        return Excel::download(
            new PostBestaetigungenExport($post, $this->posts->bestaetigungsliste($post)),
            $this->dateiname($post).'.xlsx'
        );
    }

    public function confirmationsPdf(Post $post, PdfLayout $layout): Response
    {
        $this->authorize('viewConfirmations', $post);

        $pdf = Pdf::loadView('pdf.post_bestaetigungen', [
            'post' => $post->load('groups', 'author'),
            'liste' => $this->posts->bestaetigungsliste($post),
            'statistik' => $this->posts->statistik($post),
            'kopf' => $layout->schulkopf(),
            'stand' => now(),
        ])->setPaper('a4');

        return response($layout->rendern($pdf), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->dateiname($post).'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * GET posts/{post}/dateien/{media} – Anhang ausliefern (nur mit Lesezugriff auf die Nachricht).
     */
    public function file(Post $post, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $post);
        abort_unless($media->model_type === Post::class && (int) $media->model_id === $post->id, 404);
        abort_unless(is_file($media->getPath()), 404);

        $inline = str_starts_with((string) $media->mime_type, 'image/') || $media->mime_type === 'application/pdf';

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.Str::ascii($media->file_name).'"',
        ]);
    }

    public function removeFile(Post $post, Media $media): RedirectResponse
    {
        $this->authorize('update', $post);
        abort_unless($media->model_type === Post::class && (int) $media->model_id === $post->id, 404);

        $media->delete();

        return redirect()->back()->with(['type' => 'success', 'Meldung' => 'Datei entfernt']);
    }

    /**
     * Abendliche Sammelmail mit den heute veröffentlichten Nachrichten (ohne Lesebestätigung –
     * diese werden sofort verschickt).
     */
    public function dailyMail(): void
    {
        $heute = Carbon::today()->startOfDay();
        $einstellungen = app(BenachrichtigungsService::class);

        $filter = fn ($q) => $q
            ->where('posts.released', 1)
            ->where('posts.archived', 0)
            ->where('posts.bestaetigung_erforderlich', 0)
            ->where('posts.veroeffentlicht_at', '>=', $heute);

        $users = User::query()->whereHas('posts', $filter)->get();

        foreach ($users as $user) {
            if (! $einstellungen->mailErlaubt($user, 'nachrichten')) {
                continue;
            }

            $posts = $user->posts()->where($filter)->where('posts.author_id', '!=', $user->id)->get()->unique('id');
            if ($posts->isEmpty()) {
                continue;
            }

            Mail::to($user)->queue(new newPostsMail($posts));
        }
    }

    private function dateiname(Post $post): string
    {
        $titel = trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', Str::ascii($post->header)), '-') ?: 'Nachricht';

        return 'Lesebestaetigungen_'.Str::limit($titel, 60, '').'_'.now()->format('Y-m-d');
    }

    /** Nur interne Rücksprung-Adressen übernehmen. */
    private function zurueckUrl(Request $request, ?string $standard = null): string
    {
        $ziel = (string) $request->query('zurueck', '');

        if ($ziel !== '' && str_starts_with($ziel, url('/'))) {
            return $ziel;
        }

        return $standard ?? url('/');
    }
}
