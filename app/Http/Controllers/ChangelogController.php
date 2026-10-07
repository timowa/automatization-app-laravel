<?php

namespace App\Http\Controllers;

use App\Services\ChangelogService;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(ChangelogService $changelog): View
    {
        return view('changelog', [
            'releases' => $changelog->all(),
            'currentVersion' => $changelog->currentLabel(),
        ]);
    }
}
