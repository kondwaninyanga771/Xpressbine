<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Exceptions\DomainException;
use PHPUnit\Framework\TestCase;

class BookingStatusTest extends TestCase
{
    public function test_completed_can_be_disputed_but_not_started_again(): void
    {
        $this->assertTrue(BookingStatus::Completed->canTransitionTo(BookingStatus::Disputed));
        $this->assertTrue(BookingStatus::Completed->canTransitionTo(BookingStatus::Refunded));
        $this->assertFalse(BookingStatus::Completed->canTransitionTo(BookingStatus::InProgress));
        $this->assertFalse(BookingStatus::Refunded->canTransitionTo(BookingStatus::Completed));
    }

    public function test_disputed_can_go_back_to_work_for_a_fix(): void
    {
        $this->assertTrue(BookingStatus::Disputed->canTransitionTo(BookingStatus::InProgress));
        $this->assertTrue(BookingStatus::Disputed->canTransitionTo(BookingStatus::Refunded));
        $this->assertTrue(BookingStatus::Requested->canTransitionTo(BookingStatus::Accepted));
        $this->assertFalse(BookingStatus::Requested->canTransitionTo(BookingStatus::Completed));
    }

    public function test_invalid_transition_explains_itself(): void
    {
        $this->expectException(DomainException::class);
        BookingStatus::Cancelled->assertCanTransitionTo(BookingStatus::Accepted);
    }
}
