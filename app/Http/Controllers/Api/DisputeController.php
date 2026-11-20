<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DisputeResource;
use App\Models\Booking;
use App\Models\Dispute;
use App\Services\DisputeService;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function store(Request $request, Booking $booking, DisputeService $disputes): DisputeResource
    {
        $this->authorize('dispute', $booking);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'evidence' => ['required', 'image', 'max:5120'],
        ]);

        $dispute = $disputes->open($booking, $request->user(), $data['reason'], $data['evidence']);

        return new DisputeResource($dispute);
    }

    public function respond(Request $request, Dispute $dispute, DisputeService $disputes): DisputeResource
    {
        $dispute->load('booking');
        $this->authorize('respond', $dispute);

        $data = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        return new DisputeResource($disputes->respond($dispute, $request->user(), $data['message']));
    }
}
