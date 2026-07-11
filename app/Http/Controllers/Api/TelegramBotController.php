<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramBotController extends Controller
{
    public function __construct(private readonly TelegramBotService $telegramBotService)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = config('telegram.webhook_secret');
        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $this->telegramBotService->handle($request->all());

        return response()->json(['ok' => true]);
    }
}
