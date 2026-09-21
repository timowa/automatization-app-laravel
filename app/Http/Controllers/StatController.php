<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetStatsAction;
use Illuminate\Http\JsonResponse;

class StatController extends Controller
{
    public function __construct(private GetStatsAction $getStatsAction)
    {
    }

    public function getStats(): JsonResponse
    {
        $result = $this->getStatsAction->execute();

        return response()->json($result);
    }
}