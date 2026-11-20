<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Business;
use App\Models\BusinessSite;
use App\Models\Category;
use App\Models\Package;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BookingService;
use App\Services\EscrowService;
use App\Services\RatingService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->user([
            'name' => 'Natasha Mwila',
            'email' => 'natasha.mwila@example.com',
            'phone' => '0961111001',
            'role' => UserRole::Customer,
            'mobile_money_provider' => 'mtn_momo',
            'mobile_money_number' => '0961111001',
            'area' => 'Chilenje',
            'latitude' => -15.4455,
            'longitude' => 28.3345,
        ]);

        $this->user([
            'name' => 'Mutale Zulu',
            'email' => 'mutale.zulu@example.com',
            'phone' => '0971111004',
            'role' => UserRole::Admin,
            'area' => 'Northmead',
            'latitude' => -15.4100,
            'longitude' => 28.3000,
        ]);

        $chanda = $this->user([
            'name' => 'Chanda Phiri',
            'email' => 'chanda.phiri@example.com',
            'phone' => '0961111003',
            'role' => UserRole::BusinessClient,
            'mobile_money_provider' => 'mtn_momo',
            'mobile_money_number' => '0961111003',
            'area' => 'Kabulonga',
            'latitude' => -15.4195,
            'longitude' => 28.3495,
        ]);

        $business = Business::query()->create([
            'user_id' => $chanda->id,
            'name' => 'Kabulonga Academy',
        ]);
        $wallet = Wallet::query()->create(['business_id' => $business->id]);
        app(EscrowService::class)->topUp($wallet, $chanda, '20000', 'bank_transfer');
        BusinessSite::query()->create([
            'business_id' => $business->id,
            'name' => 'Kabulonga Campus',
            'area' => 'Kabulonga',
            'address' => 'Kabulonga Road, Lusaka',
            'latitude' => -15.4195,
            'longitude' => 28.3495,
            'spending_limit' => 8000,
        ]);
        BusinessSite::query()->create([
            'business_id' => $business->id,
            'name' => 'Woodlands Campus',
            'area' => 'Woodlands',
            'address' => 'Woodlands, Lusaka',
            'latitude' => -15.4310,
            'longitude' => 28.3310,
            'spending_limit' => 8000,
        ]);

        $this->provider(
            user: [
                'name' => 'Grace Tembo',
                'email' => 'grace.tembo@example.com',
                'phone' => '0972222001',
                'mobile_money_provider' => 'airtel_money',
                'mobile_money_number' => '0972222001',
                'area' => 'Chilenje',
                'latitude' => -15.4460,
                'longitude' => 28.3360,
            ],
            profile: [
                'bio' => 'Plumber in Chilenje. Sinks, taps and geyser call-outs.',
                'nrc_number' => '123456/11/1',
                'years_experience' => 8,
                'hourly_rate' => 150,
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
            trades: ['plumbing'],
            package: ['Sink and tap repair', 'plumbing', 350, 'Fix a leaking kitchen sink or tap.'],
        );

        $this->provider(
            user: [
                'name' => 'Joseph Banda',
                'email' => 'joseph.banda@example.com',
                'phone' => '0971111002',
                'mobile_money_provider' => 'airtel_money',
                'mobile_money_number' => '0971111002',
                'area' => 'Matero',
                'latitude' => -15.3830,
                'longitude' => 28.2670,
            ],
            profile: [
                'bio' => 'Qualified electrician. Socket installs and fault finding.',
                'nrc_number' => '234567/11/1',
                'years_experience' => 6,
                'hourly_rate' => 180,
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
            trades: ['electrical'],
            package: ['Socket installation', 'electrical', 280, 'Supply and fit a double socket.'],
        );

        $this->provider(
            user: [
                'name' => 'Mary Zulu',
                'email' => 'mary.zulu@example.com',
                'phone' => '0952222003',
                'mobile_money_provider' => 'zamtel_kwacha',
                'mobile_money_number' => '0952222003',
                'area' => 'Woodlands',
                'latitude' => -15.4320,
                'longitude' => 28.3280,
            ],
            profile: [
                'bio' => 'House cleaning across Woodlands and Kabulonga.',
                'hourly_rate' => 120,
                'is_featured' => true,
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
            trades: ['cleaning'],
            package: ['Three-bedroom clean', 'cleaning', 400, 'Full clean of a three-bedroom house.'],
        );

        $this->provider(
            user: [
                'name' => 'Samuel Daka',
                'email' => 'samuel.daka@example.com',
                'phone' => '0972222004',
                'area' => 'Kabulonga',
                'latitude' => -15.4210,
                'longitude' => 28.3480,
                'mobile_money_provider' => 'airtel_money',
                'mobile_money_number' => '0972222004',
            ],
            profile: [
                'bio' => 'Weekly pool cleaning for homes and lodges.',
                'hourly_rate' => 200,
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
            trades: ['pool-cleaning'],
            package: ['Pool service', 'pool-cleaning', 650, 'Vacuum, balance and clean one pool.'],
        );

        $this->provider(
            user: [
                'name' => 'Brian Sakala',
                'email' => 'brian.sakala@example.com',
                'phone' => '0962222007',
                'area' => 'Chilenje',
                'latitude' => -15.4480,
                'longitude' => 28.3320,
                'mobile_money_provider' => 'mtn_momo',
                'mobile_money_number' => '0962222007',
            ],
            profile: [
                'bio' => 'UNZA student. Furniture assembly and errands.',
                'hourly_rate' => 80,
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
            trades: ['student-help'],
            package: ['Furniture assembly', 'student-help', 150, 'Assemble one flat-pack item.'],
        );

        $this->provider(
            user: [
                'name' => 'Loveness Bwalya',
                'email' => 'loveness.bwalya@example.com',
                'phone' => '0962222005',
                'area' => 'Matero',
                'latitude' => -15.3850,
                'longitude' => 28.2620,
                'mobile_money_provider' => 'mtn_momo',
                'mobile_money_number' => '0962222005',
            ],
            profile: [
                'bio' => 'Carpenter. Doors, cupboards and small furniture repairs.',
                'hourly_rate' => 160,
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
            trades: ['carpentry'],
            package: ['Door repair', 'carpentry', 500, 'Repair or rehang one door.'],
        );

        $this->provider(
            user: [
                'name' => 'Tina Mulenga',
                'email' => 'tina.mulenga@example.com',
                'phone' => '0972222006',
                'area' => 'Woodlands',
                'latitude' => -15.4280,
                'longitude' => 28.3330,
                'mobile_money_provider' => 'airtel_money',
                'mobile_money_number' => '0972222006',
            ],
            profile: [
                'bio' => 'Event planner for parties and small weddings in Lusaka.',
                'hourly_rate' => 250,
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
            trades: ['event-planning'],
            package: ['Backyard party setup', 'event-planning', 2500, 'Setup for a home party of about 30 people.'],
        );

        $this->provider(
            user: [
                'name' => 'Oliver Nkhoma',
                'email' => 'oliver.nkhoma@example.com',
                'phone' => '0972222008',
                'area' => 'Roma',
                'latitude' => -15.3900,
                'longitude' => 28.3200,
            ],
            profile: [
                'bio' => 'Plumber waiting for NRC verification.',
                'nrc_number' => '345678/11/1',
                'hourly_rate' => 140,
                'verification_status' => VerificationStatus::Pending,
            ],
            trades: ['plumbing'],
            package: null,
        );

        $this->seedCompletedJob();
    }

    private function seedCompletedJob(): void
    {
        $customer = User::query()->where('email', 'natasha.mwila@example.com')->firstOrFail();
        $provider = User::query()->where('email', 'grace.tembo@example.com')->firstOrFail();
        $package = Package::query()->where('title', 'Sink and tap repair')->firstOrFail();

        $bookings = app(BookingService::class);
        $booking = $bookings->createFromPackage($customer, $package, [
            'scheduled_at' => now()->subDays(12),
            'address' => 'House 12, Chilenje South',
            'area' => 'Chilenje',
            'notes' => 'Earlier visit for the same kitchen sink.',
        ]);
        $booking = app(EscrowService::class)->payWithMobileMoney($booking, $customer, 'mtn_momo', '0961111001');
        $booking = $bookings->accept($booking, $provider);
        $booking = $bookings->start($booking, $provider);
        $booking = $bookings->complete($booking, $provider);
        $booking = $bookings->confirm($booking, $customer);

        $ratings = app(RatingService::class);
        $ratings->rate($booking, $customer, 5, 'Arrived on time and fixed the leak.');
        $ratings->rate($booking, $provider, 5, 'Clear instructions and the house was easy to work in.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(array $attributes): User
    {
        return User::query()->create(array_merge([
            'password' => 'password',
            'email_verified_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $profile
     * @param  list<string>  $trades
     * @param  array{0: string, 1: string, 2: int|float, 3: string}|null  $package
     */
    private function provider(array $user, array $profile, array $trades, ?array $package): void
    {
        $account = $this->user(array_merge($user, ['role' => UserRole::Provider]));

        $record = ProviderProfile::query()->create(array_merge([
            'user_id' => $account->id,
            'service_radius_km' => 15,
            'verification_status' => VerificationStatus::Pending,
        ], $profile));

        $ids = Category::query()->whereIn('slug', $trades)->pluck('id');
        $record->categories()->sync($ids);

        if ($package) {
            Package::query()->create([
                'provider_profile_id' => $record->id,
                'category_id' => Category::query()->where('slug', $package[1])->value('id'),
                'title' => $package[0],
                'price' => $package[2],
                'description' => $package[3],
                'duration_minutes' => 120,
            ]);
        }
    }
}
