<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetStatsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatController extends Controller
{
    public function __construct(private GetStatsAction $getStatsAction)
    {
    }

    public function getStats(Request $request): JsonResponse
    {
        $code = $request->query('code');

        if ($code === null || $code === '') {
            return response()->json(['error' => 'code is required'], 400);
        }

        $exists = DB::table('offers')->where('code', $code)->exists();
        if (!$exists) {
            return response()->json(['error' => 'Offer not found'], 404);
        }

        $result = $this->getStatsAction->execute($code);

        return response()->json($result);
    }
}