<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolProfileRequest;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\SchoolProfileContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SchoolProfileController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('viewAny', SchoolProfile::class);

        return view('admin.school-profile', [
            'profile' => SchoolProfile::query()->where('is_singleton', true)->first(),
        ]);
    }

    public function update(
        StoreSchoolProfileRequest $request,
        SchoolProfileContentService $content,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $content->save(
            $actor,
            SchoolProfile::query()->where('is_singleton', true)->first(),
            $request->validated(),
            $request->file('logo_image'),
            $request->file('hero_image'),
        );

        return redirect()
            ->route('admin.school-profile.edit')
            ->with('status', 'Profil sekolah berhasil diperbarui.');
    }
}
