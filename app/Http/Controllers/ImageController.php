<?php

namespace App\Http\Controllers;


use Illuminate\Support\Facades\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getImage(Media $media_id)
    {
        $response = new BinaryFileResponse($media_id->getPath());
        $response->headers->set('Content-Disposition', 'inline; filename="'.$media_id->file_name.'"');

        return $response;
    }

    public function removeImage($groupname, Media $media)
    {
        $media->delete();
        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Datei entfernt'
        ]);
    }
}
