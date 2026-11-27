<?php

namespace App\Http\Controllers;


use App\Models\Post;
use App\Models\Theme;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\WikiSeite;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    public function __construct()
    {

    }

    public function getImage(Media $media_id)
    {
        // Ticket-Anhänge nur über die Ticket-Route mit Berechtigungsprüfung ausliefern
        if (in_array($media_id->model_type, [Ticket::class, TicketComment::class], true)) {
            $ticket = $media_id->model_type === Ticket::class
                ? $media_id->model
                : $media_id->model?->ticket;

            abort_unless(auth()->check() && $ticket, 404);

            return redirect()->route('tickets.files', [$ticket, $media_id]);
        }

        // Nachrichten-Anhänge nur mit Lesezugriff auf die Nachricht (PostPolicy)
        if ($media_id->model_type === Post::class) {
            abort_unless(auth()->check() && $media_id->model && auth()->user()->can('view', $media_id->model), 404);

            return redirect()->route('posts.files', [$media_id->model, $media_id]);
        }

        // Wiki-Anhänge nur über die Wiki-Route mit Lesezugriff auf die Seite (WikiSeitePolicy)
        if ($media_id->model_type === WikiSeite::class) {
            abort_unless(auth()->check() && $media_id->model && auth()->user()->can('view', $media_id->model), 404);

            return redirect()->route('wiki.anhang', $media_id);
        }

        // Anhänge des alten Wikis (vor der Umstellung) nur mit `view wiki`
        if ($media_id->model_type === 'App\Models\WikiSite') {
            abort_unless(auth()->check() && auth()->user()->can('view wiki'), 404);
        }

        // Eingeloggte Nutzer dürfen immer; sonst nur wenn öffentlich geteilt.
        // optional() verhindert Fatal Error wenn model null ist.
        if (auth()->check() || optional($media_id->model)->share !== null) {
            $path = $media_id->getPath();

            // Datei fehlt auf Dateisystem → sauberes 404 statt 500
            if (!file_exists($path)) {
                abort(404, 'Datei nicht gefunden');
            }

            $response = new BinaryFileResponse($path);
            $response->headers->set('Content-Disposition', 'inline; filename="'.$media_id->file_name.'"');

            return $response;
        }

        abort(404);
    }

    public function removeImageFromPost(Request $request, Media $media)
    {
        $post = Post::find($request->input('post_id'));

        if (! $post || ! auth()->user()->can('update', $post)) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Berechtigung fehlt'
            ]);
        }

        if ($media->model_type !== Post::class || (int) $media->model_id !== $post->id) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Datei nicht gefunden oder gehört nicht zum Post'
            ]);
        }

        $media->delete();

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Datei entfernt'
        ]);
    }
}
