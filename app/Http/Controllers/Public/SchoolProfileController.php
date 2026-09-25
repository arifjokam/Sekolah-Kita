<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicContentReader;
use Illuminate\View\View;

class SchoolProfileController extends Controller
{
    public function show(PublicContentReader $content): View
    {
        return view('public.school-profile', [
            'profile' => $content->schoolProfile(),
        ]);
    }
}
