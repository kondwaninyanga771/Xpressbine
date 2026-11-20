<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Quote;
use App\Services\BookingService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function accept(Request $request, Quote $quote, BookingService $bookings): BookingResource
    {
        $quote->load('jobRequest');
        $this->authorize('accept', $quote);

        $booking = $bookings->createFromQuote($request->user(), $quote);
        $booking->loadSum('ledgerEntries as escrow_balance', 'escrow_delta');

        return new BookingResource($booking);
    }
}
