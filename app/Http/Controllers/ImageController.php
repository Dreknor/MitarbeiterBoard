<?php

namespace App\Http\Controllers;


use App\Models\Post;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    public function __construct()
    {

    }

    public function getImage(Media $media_id)
    {
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

    public function removeImage($groupname, Media $media)
    {
        Log::debug('Dateien: Datei entfernen', [
            'media' => $media,
            'user' => auth()->user()
        ]);

        $media->delete();

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Datei entfernt'
        ]);
    }

    public function removeImageFromPost(Request $request, Media $media)
    {
        if (auth()->user()->can('create posts')) {
            $post = Post::find($request->input('post_id'));
            $post_media = $post->getMedia('files')->where('id', $media->id)->first();
            if (is_null($post_media)) {
                $post_media = $post->getMedia('images')->where('id', $media->id)->first();
            }
            if (!is_null($post_media)) {
                $media->delete();
                return redirect()->back()->with([
                    'type' => 'success',
                    'Meldung' => 'Datei entfernt'
                ]);
            }

            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Datei nicht gefunden oder gehört nicht zum Post'
            ]);
        }

        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => 'Berechtigung fehlt'
        ]);
    }
}
