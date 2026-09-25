<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicContentReader;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(PublicContentReader $content): View
    {
        return view('public.home', [
            'latestNews' => $content->homepageNews(),
            'latestAchievements' => $content->homepageAchievements(),
            'latestAlbums' => $content->homepageGalleryAlbums(),
        ]);
    }
}
