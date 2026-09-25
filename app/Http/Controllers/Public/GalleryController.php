<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicContentReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(Request $request, PublicContentReader $content): View
    {
        return view('public.gallery.index', [
            'albums' => $content->galleryAlbums(max(1, $request->integer('page', 1))),
        ]);
    }

    public function show(string $slug, Request $request, PublicContentReader $content): View
    {
        $album = $content->galleryAlbum($slug);
        abort_unless($album !== null, 404);

        return view('public.gallery.show', [
            'album' => $album,
            'items' => $content->galleryItems($slug, max(1, $request->integer('page', 1))),
        ]);
    }
}
