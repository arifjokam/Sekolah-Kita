<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicContentReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsArticleController extends Controller
{
    public function index(Request $request, PublicContentReader $content): View
    {
        return view('public.news.index', [
            'articles' => $content->news(max(1, $request->integer('page', 1))),
        ]);
    }

    public function show(string $slug, PublicContentReader $content): View
    {
        $article = $content->newsArticle($slug);
        abort_unless($article !== null, 404);

        return view('public.news.show', [
            'article' => $article,
        ]);
    }
}
