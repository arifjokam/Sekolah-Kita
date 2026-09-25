<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicContentReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDirectoryController extends Controller
{
    public function index(Request $request, PublicContentReader $content): View
    {
        return view('public.teachers.index', [
            'teachers' => $content->teachers(max(1, $request->integer('page', 1))),
        ]);
    }

    public function show(string $slug, PublicContentReader $content): View
    {
        $teacher = $content->teacher($slug);
        abort_unless($teacher !== null, 404);

        return view('public.teachers.show', [
            'teacher' => $teacher,
        ]);
    }
}
