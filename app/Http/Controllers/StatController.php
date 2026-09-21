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
        $offerId = $request->query('offer_id');

        if ($offerId === null || $offerId === '') {
            return response()->json(['error' => 'offer_id is required'], 400);
        }

        $exists = DB::table('offers')->where('offer_id', $offerId)->exists();
        if (!$exists) {
            return response()->json(['error' => 'Offer not found'], 404);
        }

        $result = $this->getStatsAction->execute($offerId);

        return response()->json($result);
    }
}
