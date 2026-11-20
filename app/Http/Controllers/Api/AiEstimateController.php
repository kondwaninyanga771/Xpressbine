<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EstimateRecord;
use App\Services\Ai\ProblemEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AiEstimateController extends Controller
{
    public function store(Request $request, ProblemEstimator $estimator): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:8', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $photoPath = null;

        if ($request->file('photo')) {
            $stored = $request->file('photo')->store('ai-estimates', 'public');
            $photoPath = Storage::disk('public')->path($stored);
        }

        $estimate = $estimator->estimate($data['description'], $photoPath);
        $record = EstimateRecord::query()->create([
            'user_id' => $request->user()->id,
            'description' => $data['description'],
            'trade' => $estimate->trade,
            'trade_label' => $estimate->tradeLabel,
            'urgency' => $estimate->urgency,
            'price_min' => $estimate->priceMin,
            'price_max' => $estimate->priceMax,
            'currency' => $estimate->currency,
            'confidence' => $estimate->confidence,
            'rationale' => $estimate->rationale,
            'driver' => $estimate->driver,
            'photo_provided' => $estimate->photoProvided,
        ]);

        return response()->json([
            'data' => array_merge($estimate->toArray(), ['id' => $record->id, 'created_at' => $record->created_at]),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $estimates = EstimateRecord::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['data' => $estimates]);
    }
}
