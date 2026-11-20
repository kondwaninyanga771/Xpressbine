<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\Rating;
use App\Models\User;

class RatingService
{
    public function __construct(private NotificationService $notifications) {}

    public function rate(Booking $booking, User $rater, int $stars, ?string $comment): Rating
    {
        if (! in_array($booking->status, [BookingStatus::Completed, BookingStatus::Refunded], true)) {
            throw new DomainException('Ratings open after the job is completed or refunded.');
        }

        $providerUserId = $booking->provider->user_id;
        $isCustomer = $rater->id === $booking->customer_id;
        $isProvider = $rater->id === $providerUserId;

        if (! $isCustomer && ! $isProvider) {
            throw new DomainException('Only the customer and the provider on this booking can rate it.');
        }

        if (Rating::query()->where('booking_id', $booking->id)->where('rater_id', $rater->id)->exists()) {
            throw new DomainException('You have already rated this booking.');
        }

        $rateeId = $isCustomer ? $providerUserId : $booking->customer_id;

        $rating = Rating::query()->create([
            'booking_id' => $booking->id,
            'rater_id' => $rater->id,
            'ratee_id' => $rateeId,
            'stars' => $stars,
            'comment' => $comment,
        ]);

        if ($isCustomer) {
            $this->refreshProviderRating($booking->provider);
        }

        $this->notifications->send(
            User::query()->findOrFail($rateeId),
            'rating',
            'New rating',
            "{$rater->name} left {$stars} stars on {$booking->reference}.",
            ['booking_id' => $booking->id, 'rating_id' => $rating->id],
        );

        return $rating;
    }

    public function refreshProviderRating(ProviderProfile $profile): void
    {
        $stats = Rating::query()
            ->where('ratee_id', $profile->user_id)
            ->selectRaw('count(*) as rating_count, coalesce(avg(stars), 0) as rating_average')
            ->first();

        $profile->rating_count = (int) $stats->rating_count;
        $profile->rating_average = number_format((float) $stats->rating_average, 2, '.', '');
        $profile->save();
    }
}
