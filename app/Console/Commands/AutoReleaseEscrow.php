<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Jobs\ReleaseEscrowJob;
use App\Models\Booking;
use Illuminate\Console\Command;

class AutoReleaseEscrow extends Command
{
    protected $signature = 'escrow:auto-release';

    protected $description = 'Queue escrow release for completed bookings whose confirmation window has passed';

    public function handle(): int
    {
        $ids = Booking::query()
            ->where('status', BookingStatus::Completed)
            ->whereNull('escrow_released_at')
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            ReleaseEscrowJob::dispatchSync($id);
        }

        $this->info('Released escrow for '.$ids->count().' booking(s).');

        return self::SUCCESS;
    }
}
