<?php

namespace App\Http\Controllers;

use App\Actions\ReceiveOfferWebhookAction;
use App\Http\Requests\OfferWebhookRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class OfferController extends Controller
{

    public function __construct(private ReceiveOfferWebhookAction $receiveOfferWebhookAction)
    {

    }
    public function offer(OfferWebhookRequest $request)
    {
        try {
            $this->receiveOfferWebhookAction->execute($request->validated());

            return response('OK', 200);
        } catch (Throwable $th) {
            Log::channel('job')->error($th->getMessage(), ['trace' => $th->getTraceAsString()]);
            return response('Error', 502);
        }
    }

    public function index()
    {
        return redirect('/agents');
    }
}
