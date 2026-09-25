<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNewsArticleRequest;
use App\Http\Requests\Admin\UpdateNewsArticleRequest;
use App\Models\NewsArticle;
use App\Models\User;
use App\Services\PublicContentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NewsArticleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', NewsArticle::class);

        $articles = NewsArticle::query()
            ->with(['author:id,name'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.news.index', compact('articles'));
    }

    public function create(): View
    {
        Gate::authorize('create', NewsArticle::class);

        return view('admin.news.form', ['newsArticle' => null]);
    }

    public function store(StoreNewsArticleRequest $request, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->createNews(
            $actor,
            $request->validated(),
            $request->file('cover_image'),
        );

        return redirect()
            ->route('admin.news.index')
            ->with('status', 'Berita draf berhasil dibuat.');
    }

    public function edit(NewsArticle $newsArticle): View
    {
        Gate::authorize('update', $newsArticle);

        return view('admin.news.form', compact('newsArticle'));
    }

    public function update(
        UpdateNewsArticleRequest $request,
        NewsArticle $newsArticle,
        PublicContentManager $content,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $content->updateNews(
            $actor,
            $newsArticle,
            $request->validated(),
            $request->file('cover_image'),
        );

        return redirect()
            ->route('admin.news.index')
            ->with('status', 'Berita berhasil diperbarui.');
    }

    public function publish(Request $request, NewsArticle $newsArticle, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->publishNews($actor, $newsArticle);

        return back()->with('status', 'Berita berhasil diterbitkan.');
    }

    public function unpublish(Request $request, NewsArticle $newsArticle, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->unpublishNews($actor, $newsArticle);

        return back()->with('status', 'Berita dikembalikan menjadi draf.');
    }

    public function archive(Request $request, NewsArticle $newsArticle, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->archiveNews($actor, $newsArticle);

        return back()->with('status', 'Berita berhasil diarsipkan.');
    }
}
