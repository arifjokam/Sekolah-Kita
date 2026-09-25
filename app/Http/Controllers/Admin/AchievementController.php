<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAchievementRequest;
use App\Http\Requests\Admin\UpdateAchievementRequest;
use App\Models\Achievement;
use App\Models\User;
use App\Services\PublicContentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AchievementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Achievement::class);

        $achievements = Achievement::query()
            ->orderByDesc('achievement_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.achievements.index', compact('achievements'));
    }

    public function create(): View
    {
        Gate::authorize('create', Achievement::class);

        return view('admin.achievements.form', ['achievement' => null]);
    }

    public function store(StoreAchievementRequest $request, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->createAchievement(
            $actor,
            $request->validated(),
            $request->file('cover_image'),
        );

        return redirect()
            ->route('admin.achievements.index')
            ->with('status', 'Prestasi draf berhasil dibuat.');
    }

    public function edit(Achievement $achievement): View
    {
        Gate::authorize('update', $achievement);

        return view('admin.achievements.form', compact('achievement'));
    }

    public function update(
        UpdateAchievementRequest $request,
        Achievement $achievement,
        PublicContentManager $content,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $content->updateAchievement(
            $actor,
            $achievement,
            $request->validated(),
            $request->file('cover_image'),
        );

        return redirect()
            ->route('admin.achievements.index')
            ->with('status', 'Prestasi berhasil diperbarui.');
    }

    public function publish(Request $request, Achievement $achievement, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->publishAchievement($actor, $achievement);

        return back()->with('status', 'Prestasi berhasil diterbitkan.');
    }

    public function unpublish(Request $request, Achievement $achievement, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->unpublishAchievement($actor, $achievement);

        return back()->with('status', 'Prestasi dikembalikan menjadi draf.');
    }

    public function archive(Request $request, Achievement $achievement, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->archiveAchievement($actor, $achievement);

        return back()->with('status', 'Prestasi berhasil diarsipkan.');
    }
}
