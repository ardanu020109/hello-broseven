<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('landing.index', [
            'company' => Company::current(),
        ]);
    }
}
