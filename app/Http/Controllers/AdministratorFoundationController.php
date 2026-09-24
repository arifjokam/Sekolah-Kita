<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AdministratorFoundationController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.foundation', [
            'user' => $request->user(),
        ]);
    }
}
