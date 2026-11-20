<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $booking->involves($user);
    }

    public function pay(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id && $user->booksAsCustomer();
    }

    public function accept(User $user, Booking $booking): bool
    {
        return $this->providerOwns($user, $booking);
    }

    public function start(User $user, Booking $booking): bool
    {
        return $this->providerOwns($user, $booking);
    }

    public function complete(User $user, Booking $booking): bool
    {
        return $this->providerOwns($user, $booking);
    }

    public function confirm(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id && $user->booksAsCustomer();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->involves($user);
    }

    public function dispute(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id && $user->booksAsCustomer();
    }

    public function rate(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id || $this->providerOwns($user, $booking);
    }

    public function uploadPhoto(User $user, Booking $booking): bool
    {
        return $booking->involves($user);
    }

    private function providerOwns(User $user, Booking $booking): bool
    {
        return $user->isProvider()
            && $user->providerProfile
            && $user->providerProfile->id === $booking->provider_profile_id;
    }
}
