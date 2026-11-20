<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobRequestResource;
use App\Http\Resources\QuoteResource;
use App\Models\Category;
use App\Models\JobRequest;
use App\Services\JobRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobRequestController extends Controller
{
    public function index(Request $request, JobRequestService $jobs): AnonymousResourceCollection
    {
        return JobRequestResource::collection($jobs->visibleTo($request->user()));
    }

    public function store(Request $request, JobRequestService $jobs): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'business_site_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'preferred_at' => ['nullable', 'date'],
            'client_absent' => ['sometimes', 'boolean'],
            'on_site_contact_name' => ['nullable', 'string', 'max:120'],
            'on_site_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        if (! empty($data['category']) && empty($data['category_id'])) {
            $data['category_id'] = Category::query()->where('slug', $data['category'])->value('id');
        }

        $job = $jobs->create($request->user(), $data);
        $job->load(['category', 'customer']);

        return (new JobRequestResource($job))->response()->setStatusCode(201);
    }

    public function show(Request $request, JobRequest $jobRequest): JobRequestResource
    {
        $this->authorize('view', $jobRequest);
        $jobRequest->load(['category', 'customer', 'quotes.provider.user']);

        return new JobRequestResource($jobRequest);
    }

    public function quote(Request $request, JobRequest $jobRequest, JobRequestService $jobs): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'message' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:1440'],
        ]);

        $quote = $jobs->quote($jobRequest, $request->user(), $data);

        return (new QuoteResource($quote))->response()->setStatusCode(201);
    }
}
