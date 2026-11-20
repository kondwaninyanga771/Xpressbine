<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json(['data' => $estimate->toArray()]);
    }
}
