<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryItemRequest;
use App\Http\Requests\Admin\UpdateGalleryItemRequest;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\User;
use App\Services\PublicContentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GalleryItemController extends Controller
{
    public function index(GalleryAlbum $galleryAlbum): View
    {
        Gate::authorize('viewAny', GalleryItem::class);

        $items = GalleryItem::query()
            ->whereBelongsTo($galleryAlbum)
            ->with('mediaAsset')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.gallery-albums.items.index', compact('galleryAlbum', 'items'));
    }

    public function create(GalleryAlbum $galleryAlbum): View
    {
        Gate::authorize('create', GalleryItem::class);

        return view('admin.gallery-albums.items.form', [
            'galleryAlbum' => $galleryAlbum,
            'galleryItem' => null,
        ]);
    }

    public function store(
        StoreGalleryItemRequest $request,
        GalleryAlbum $galleryAlbum,
        PublicContentManager $content,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $content->createGalleryItem(
            $actor,
            $galleryAlbum,
            $request->validated(),
            $request->file('image'),
        );

        return back()->with('status', 'Media galeri berhasil ditambahkan sebagai draf.');
    }

    public function edit(GalleryAlbum $galleryAlbum, GalleryItem $galleryItem): View
    {
        Gate::authorize('update', $galleryItem);

        return view('admin.gallery-albums.items.form', compact('galleryAlbum', 'galleryItem'));
    }

    public function update(
        UpdateGalleryItemRequest $request,
        GalleryAlbum $galleryAlbum,
        GalleryItem $galleryItem,
        PublicContentManager $content,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $content->updateGalleryItem(
            $actor,
            $galleryAlbum,
            $galleryItem,
            $request->validated(),
            $request->file('image'),
        );

        return back()->with('status', 'Media galeri berhasil diperbarui.');
    }

    public function publish(Request $request, GalleryAlbum $galleryAlbum, GalleryItem $galleryItem, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->publishGalleryItem($actor, $galleryItem);

        return back()->with('status', 'Media galeri berhasil diterbitkan.');
    }

    public function unpublish(Request $request, GalleryAlbum $galleryAlbum, GalleryItem $galleryItem, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->unpublishGalleryItem($actor, $galleryItem);

        return back()->with('status', 'Media galeri dikembalikan menjadi draf.');
    }

    public function hide(Request $request, GalleryAlbum $galleryAlbum, GalleryItem $galleryItem, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->hideGalleryItem($actor, $galleryItem);

        return back()->with('status', 'Media galeri disembunyikan.');
    }

    public function archive(Request $request, GalleryAlbum $galleryAlbum, GalleryItem $galleryItem, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->archiveGalleryItem($actor, $galleryItem);

        return back()->with('status', 'Media galeri berhasil diarsipkan.');
    }
}
