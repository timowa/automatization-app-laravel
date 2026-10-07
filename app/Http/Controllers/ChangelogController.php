<?php

namespace App\Http\Controllers;

use App\Services\ChangelogService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(ChangelogService $changelog): View
    {
        $changelog->markCurrentVersionSeen();

        return view('changelog', [
            'releases' => $changelog->all(),
            'currentVersion' => $changelog->currentLabel(),
        ]);
    }

    public function markSeen(ChangelogService $changelog): JsonResponse
    {
        $changelog->markCurrentVersionSeen();

        return response()->json([
            'success' => true,
            'version' => $changelog->current(),
        ]);
    }
}
