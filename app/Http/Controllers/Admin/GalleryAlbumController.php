<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryAlbumRequest;
use App\Http\Requests\Admin\UpdateGalleryAlbumRequest;
use App\Models\GalleryAlbum;
use App\Models\User;
use App\Services\PublicContentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GalleryAlbumController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', GalleryAlbum::class);

        $albums = GalleryAlbum::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.gallery-albums.index', compact('albums'));
    }

    public function create(): View
    {
        Gate::authorize('create', GalleryAlbum::class);

        return view('admin.gallery-albums.form', ['galleryAlbum' => null]);
    }

    public function store(StoreGalleryAlbumRequest $request, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $album = $content->createGalleryAlbum($actor, $request->validated());

        return redirect()
            ->route('admin.gallery-albums.items.index', $album)
            ->with('status', 'Album berhasil dibuat. Tambahkan media ke album.');
    }

    public function edit(GalleryAlbum $galleryAlbum): View
    {
        Gate::authorize('update', $galleryAlbum);

        return view('admin.gallery-albums.form', compact('galleryAlbum'));
    }

    public function update(
        UpdateGalleryAlbumRequest $request,
        GalleryAlbum $galleryAlbum,
        PublicContentManager $content,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $content->updateGalleryAlbum($actor, $galleryAlbum, $request->validated());

        return redirect()
            ->route('admin.gallery-albums.index')
            ->with('status', 'Album berhasil diperbarui.');
    }

    public function publish(Request $request, GalleryAlbum $galleryAlbum, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->publishGalleryAlbum($actor, $galleryAlbum);

        return back()->with('status', 'Album berhasil diterbitkan.');
    }

    public function unpublish(Request $request, GalleryAlbum $galleryAlbum, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->unpublishGalleryAlbum($actor, $galleryAlbum);

        return back()->with('status', 'Album dikembalikan menjadi draf.');
    }

    public function archive(Request $request, GalleryAlbum $galleryAlbum, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->archiveGalleryAlbum($actor, $galleryAlbum);

        return back()->with('status', 'Album berhasil diarsipkan.');
    }
}
