<?php

namespace App\Http\Controllers;


use App\Models\Post;
use App\Models\Theme;
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
        if ($media_id->model->share != null or ($media_id->model instanceof Theme and auth()->check() and auth()->user()->groups()->contains($media_id->model->group) )) {
            $response = new BinaryFileResponse($media_id->getPath());
            $response->headers->set('Content-Disposition', 'inline; filename="'.$media_id->file_name.'"');

            return $response;
        }

        abort(404);
    }

    public function removeImage($groupname, Media $media)
    {
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
