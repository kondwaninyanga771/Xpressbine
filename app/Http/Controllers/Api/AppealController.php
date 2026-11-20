<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appeal;
use App\Models\ModerationAction;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Appeal::query()->where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function actions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ModerationAction::query()->where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function store(Request $request, ModerationService $moderation): JsonResponse
    {
        $data = $request->validate([
            'moderation_action_id' => ['required', 'integer', 'exists:moderation_actions,id'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $action = ModerationAction::query()->findOrFail($data['moderation_action_id']);
        $appeal = $moderation->appeal($request->user(), $action, $data['message']);

        return response()->json(['data' => $appeal], 201);
    }
}
