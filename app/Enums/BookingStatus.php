<?php

namespace App\Enums;

use App\Exceptions\DomainException;

enum BookingStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowed(): array
    {
        return match ($this) {
            self::Requested => [self::Accepted, self::Cancelled, self::Refunded],
            self::Accepted => [self::InProgress, self::Cancelled, self::Refunded],
            self::InProgress => [self::Completed, self::Cancelled, self::Disputed, self::Refunded],
            self::Completed => [self::Disputed, self::Refunded],
            self::Disputed => [self::InProgress, self::Completed, self::Refunded],
            self::Cancelled => [self::Refunded],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowed(), true);
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! $this->canTransitionTo($to)) {
            throw new DomainException("Cannot move a booking from {$this->value} to {$to->value}.");
        }
    }
}
