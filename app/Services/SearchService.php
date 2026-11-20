<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\ProviderProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Provider search by trade and distance.
 *
 * Distance is the Haversine formula in SQL (the hackathon shortcut from
 * section 9.7). To move to PostGIS later, replace distanceSql() and the
 * radius filter with ST_Distance / ST_DWithin and keep this method's
 * arguments the same. Coordinates stay on users.latitude / users.longitude.
 */
class SearchService
{
    public function __construct(private PlatformConfig $config) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function providers(array $filters): LengthAwarePaginator
    {
        $query = ProviderProfile::query()
            ->with([
                'user',
                'categories',
                'packages' => fn ($q) => $q->where('is_active', true),
                'photos',
            ])
            ->where('verification_status', VerificationStatus::Verified)
            ->whereHas('user', function ($q) {
                $q->whereNull('banned_at')
                    ->where(function ($q) {
                        $q->whereNull('suspended_until')
                            ->orWhere('suspended_until', '<=', now());
                    });
            });

        if (! empty($filters['category'])) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (! empty($filters['area'])) {
            $area = $filters['area'];
            $query->whereHas('user', fn ($q) => $q->whereRaw('lower(area) = lower(?)', [$area]));
        }

        if (! empty($filters['featured'])) {
            $query->where('is_featured', true);
        }

        $lat = $filters['latitude'] ?? null;
        $lng = $filters['longitude'] ?? null;
        $sort = $filters['sort'] ?? ($lat !== null && $lng !== null ? 'distance' : 'rating');

        if ($lat !== null && $lng !== null) {
            $radius = (float) ($filters['radius_km'] ?? $this->config->get('default_search_radius_km'));

            if (DB::connection()->getDriverName() === 'pgsql') {
                $distance = $this->distanceSql();

                $query->join('users', 'users.id', '=', 'provider_profiles.user_id')
                    ->whereNotNull('users.latitude')
                    ->whereNotNull('users.longitude')
                    ->select('provider_profiles.*')
                    ->selectRaw("{$distance} as distance_km", [$lat, $lng, $lat])
                    ->whereRaw("{$distance} <= ?", [$lat, $lng, $lat, $radius]);
            } else {
                // SQLite demo fallback: bounding box. PostgreSQL uses Haversine above.
                $deltaLat = $radius / 111;
                $deltaLng = $radius / (111 * max(0.2, cos(deg2rad((float) $lat))));
                $query->whereHas('user', function ($q) use ($lat, $lng, $deltaLat, $deltaLng) {
                    $q->whereBetween('latitude', [(float) $lat - $deltaLat, (float) $lat + $deltaLat])
                        ->whereBetween('longitude', [(float) $lng - $deltaLng, (float) $lng + $deltaLng]);
                });
                if ($sort === 'distance') {
                    $sort = 'rating';
                }
            }
        }

        $query->orderByDesc('provider_profiles.is_featured');

        if ($sort === 'price') {
            $query->orderBy('provider_profiles.hourly_rate');
        } elseif ($sort === 'distance' && $lat !== null && $lng !== null) {
            $query->orderBy('distance_km');
        } else {
            $query->orderByDesc('provider_profiles.rating_average');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function distanceSql(string $latitudeColumn = 'users.latitude', string $longitudeColumn = 'users.longitude'): string
    {
        return '(6371 * acos(least(1.0, greatest(-1.0, '
            ."cos(radians(?)) * cos(radians({$latitudeColumn})) * cos(radians({$longitudeColumn}) - radians(?)) "
            ."+ sin(radians(?)) * sin(radians({$latitudeColumn}))"
            .'))))';
    }
}
