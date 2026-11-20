<?php

namespace App\Http\Controllers\Api;

use App\Enums\PhotoKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\RatingResource;
use App\Models\Booking;
use App\Models\Package;
use App\Models\ProviderProfile;
use App\Models\Rating;
use App\Services\BookingService;
use App\Services\EscrowService;
use App\Services\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Booking::query()
            ->with(['customer', 'provider.user', 'category', 'package'])
            ->withSum('ledgerEntries as escrow_balance', 'escrow_delta')
            ->latest();

        if ($user->isProvider()) {
            $query->where('provider_profile_id', $user->providerProfile?->id ?? 0);
        } elseif (! $user->isAdmin()) {
            $query->where('customer_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return BookingResource::collection($query->paginate(15));
    }

    public function store(Request $request, BookingService $bookings): BookingResource
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'scheduled_at' => ['required', 'date'],
            'address' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_absent' => ['sometimes', 'boolean'],
            'on_site_contact_name' => ['nullable', 'string', 'max:120'],
            'on_site_contact_phone' => ['nullable', 'string', 'max:20'],
            'business_site_id' => ['nullable', 'integer'],
        ]);

        $package = Package::query()->findOrFail($data['package_id']);
        $booking = $bookings->createFromPackage($request->user(), $package, $data);
        $booking->loadSum('ledgerEntries as escrow_balance', 'escrow_delta');

        return new BookingResource($booking);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        $this->authorize('view', $booking);
        $booking->load(['customer', 'provider.user', 'provider.categories', 'category', 'package', 'photos', 'ratings.rater']);
        $booking->loadSum('ledgerEntries as escrow_balance', 'escrow_delta');

        return new BookingResource($booking);
    }

    public function pay(Request $request, Booking $booking, EscrowService $escrow): BookingResource
    {
        $this->authorize('pay', $booking);

        $data = $request->validate([
            'method' => ['required', Rule::in(['mobile_money', 'wallet'])],
            'mobile_money_provider' => ['required_if:method,mobile_money', 'nullable', 'string'],
            'mobile_money_number' => ['required_if:method,mobile_money', 'nullable', 'string', 'max:20'],
        ]);

        $paid = $data['method'] === 'wallet'
            ? $escrow->payWithWallet($booking, $request->user())
            : $escrow->payWithMobileMoney(
                $booking,
                $request->user(),
                $data['mobile_money_provider'],
                $data['mobile_money_number'],
            );

        return new BookingResource($this->prepare($paid));
    }

    public function accept(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorize('accept', $booking);

        return new BookingResource($this->prepare($bookings->accept($booking, $request->user())));
    }

    public function start(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorize('start', $booking);

        return new BookingResource($this->prepare($bookings->start($booking, $request->user())));
    }

    public function complete(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorize('complete', $booking);

        return new BookingResource($this->prepare($bookings->complete($booking, $request->user())));
    }

    public function confirm(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorize('confirm', $booking);

        return new BookingResource($this->prepare($bookings->confirm($booking, $request->user())));
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorize('cancel', $booking);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        return new BookingResource($this->prepare($bookings->cancel($booking, $request->user(), $data['reason'])));
    }

    public function noShow(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorize('dispute', $booking);

        return new BookingResource($this->prepare($bookings->reportNoShow($booking, $request->user())));
    }

    public function customerAbsent(Request $request, Booking $booking, BookingService $bookings): BookingResource
    {
        $this->authorize('accept', $booking);

        return new BookingResource($this->prepare($bookings->reportCustomerAbsent($booking, $request->user())));
    }

    public function photo(Request $request, Booking $booking, BookingService $bookings): JsonResponse
    {
        $this->authorize('uploadPhoto', $booking);
        $data = $request->validate([
            'kind' => ['required', Rule::enum(PhotoKind::class)],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $photo = $bookings->addPhoto($booking, $request->user(), PhotoKind::from($data['kind']), $data['photo']);

        return response()->json([
            'data' => [
                'id' => $photo->id,
                'kind' => $photo->kind->value,
                'url' => $photo->url,
            ],
        ], 201);
    }

    public function rate(Request $request, Booking $booking, RatingService $ratings): RatingResource
    {
        $this->authorize('rate', $booking);
        $data = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $rating = $ratings->rate($booking, $request->user(), $data['stars'], $data['comment'] ?? null);
        $rating->load('rater');

        return new RatingResource($rating);
    }

    public function ledger(Request $request, Booking $booking): AnonymousResourceCollection
    {
        $this->authorize('view', $booking);

        return LedgerEntryResource::collection(
            $booking->ledgerEntries()->orderBy('id')->get()
        );
    }

    public function providerRatings(ProviderProfile $provider): AnonymousResourceCollection
    {
        $ratings = Rating::query()
            ->where('ratee_id', $provider->user_id)
            ->with('rater')
            ->latest()
            ->paginate(15);

        return RatingResource::collection($ratings);
    }

    private function prepare(Booking $booking): Booking
    {
        $booking->load(['customer', 'provider.user', 'category', 'package']);
        $booking->loadSum('ledgerEntries as escrow_balance', 'escrow_delta');

        return $booking;
    }
}
