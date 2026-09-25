<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicContentReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AchievementController extends Controller
{
    public function index(Request $request, PublicContentReader $content): View
    {
        return view('public.achievements.index', [
            'achievements' => $content->achievements(max(1, $request->integer('page', 1))),
        ]);
    }

    public function show(string $slug, PublicContentReader $content): View
    {
        $achievement = $content->achievement($slug);
        abort_unless($achievement !== null, 404);

        return view('public.achievements.show', [
            'achievement' => $achievement,
        ]);
    }
}
