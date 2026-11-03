<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetExclusivePublicationDetailsAction;
use App\Actions\RetryExclusivePublicationTasksAction;
use App\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ExclusivePublicationController extends Controller
{
    public function show(Offer $offer, GetExclusivePublicationDetailsAction $action): View
    {
        return view('exclusive-publications', $action->execute($offer));
    }

    public function retry(Offer $offer, Request $request, RetryExclusivePublicationTasksAction $action): RedirectResponse
    {
        $data = $request->validate(['publication_id' => ['nullable', 'integer']]);
        $count = $action->execute($offer, isset($data['publication_id']) ? (int) $data['publication_id'] : null);

        return back()->with('message', 'Задач отправлено на повтор: '.$count);
    }
}
