<?php

namespace App\Jobs;

use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Services\EscrowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReleaseEscrowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $bookingId) {}

    public function handle(EscrowService $escrow): void
    {
        $booking = Booking::query()->find($this->bookingId);

        if (! $booking) {
            return;
        }

        try {
            $escrow->autoRelease($booking);
        } catch (DomainException $exception) {
            Log::info('Escrow auto-release skipped', [
                'booking_id' => $this->bookingId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
