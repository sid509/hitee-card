<?php

namespace App\Http\Controllers\CardManagement;

use App\Http\Controllers\Controller;
use App\Services\CardManagement\CardsService;
use App\Services\CardManagement\ResponseEnvelope;
use Illuminate\Http\Request;

final class CardsController extends Controller
{
    public function __construct(private readonly CardsService $service) {}

    public function check(Request $request)
    {
        $result = $this->service->checkCard($request->input('uid', ''));
        return ResponseEnvelope::success($result);
    }

    public function register(Request $request)
    {
        $card = $this->service->registerCard($request->all());
        return ResponseEnvelope::created(['card' => $card->toPublicArray()]);
    }

    public function get(string $uid)
    {
        $card = $this->service->getCard($uid);
        return ResponseEnvelope::success(['card' => $card->toPublicArray()]);
    }

    public function assignLabProfile(string $uid)
    {
        $card = $this->service->assignLabProfile($uid);
        return ResponseEnvelope::success(['card' => $card->toPublicArray()]);
    }

    public function getInitializationOperation(string $uid)
    {
        $result = $this->service->getInitializationOperation($uid);
        return ResponseEnvelope::success($result);
    }

    public function createInitializationOperation(Request $request, string $uid)
    {
        $operation = $this->service->createInitializationOperation($uid, $request->all());
        return ResponseEnvelope::created(['operation' => $operation->toPublicArray()]);
    }
}
