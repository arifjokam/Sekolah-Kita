<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTeacherPublicProfileRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Services\PublicContentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeacherDirectoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Teacher::class);

        $teachers = Teacher::query()
            ->orderBy('legal_name')
            ->orderBy('id')
            ->paginate(20);

        return view('admin.teacher-directory.index', compact('teachers'));
    }

    public function edit(Teacher $teacher): View
    {
        Gate::authorize('managePublicProfile', $teacher);

        return view('admin.teacher-directory.form', compact('teacher'));
    }

    public function update(
        UpdateTeacherPublicProfileRequest $request,
        Teacher $teacher,
        PublicContentManager $content,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $content->updateTeacherPublicProfile(
            $actor,
            $teacher,
            $request->validated(),
            $request->file('photo'),
        );

        return redirect()
            ->route('admin.teacher-directory.index')
            ->with('status', 'Profil publik guru berhasil diperbarui.');
    }

    public function publish(Request $request, Teacher $teacher, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->publishTeacher($actor, $teacher);

        return back()->with('status', 'Profil guru berhasil diterbitkan.');
    }

    public function unpublish(Request $request, Teacher $teacher, PublicContentManager $content): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $content->unpublishTeacher($actor, $teacher);

        return back()->with('status', 'Profil guru dikeluarkan dari direktori publik.');
    }
}
