# Xpressbine API

Lusaka marketplace API for booking local, in-person trades (plumbers, electricians, cleaners and others). Payments are mock mobile money held in an escrow ledger. Amounts are Zambian kwacha (K).

This hackathon build is a JSON API. A React or Inertia frontend can sit on top later.

## Setup

Requires PHP 8.2+ and Composer. SQLite is the default so you can demo without a database server.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

API base URL: `http://127.0.0.1:8000/api`

Run the happy-path test:

```bash
php artisan test --filter=HappyPathTest
```

### PostgreSQL

Distance search uses a Haversine SQL query on PostgreSQL (easy to swap for PostGIS later). On SQLite the same endpoint uses a bounding box.

```bash
# create the database, then in .env:
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=xpressbine
DB_USERNAME=xpressbine
DB_PASSWORD=xpressbine
```

Then `php artisan migrate:fresh --seed`.

### Useful env vars

| Variable | Default | Meaning |
| --- | --- | --- |
| `XPRESSBINE_COMMISSION_RATE` | `0.12` | Platform cut when escrow is released |
| `XPRESSBINE_AUTO_RELEASE_HOURS` | `48` | Confirmation window before auto-release |
| `XPRESSBINE_AI_DRIVER` | `mock` | `mock` (no API key) or `llm` |
| `XPRESSBINE_AI_API_KEY` | empty | Required only when the driver is `llm` |

Every percentage and time limit from the spec is a config value in `config/xpressbine.php`.

## Demo logins

Password for every seeded account: `password`

| Person | Email | Role |
| --- | --- | --- |
| Natasha Mwila | natasha.mwila@example.com | Customer, Chilenje |
| Grace Tembo | grace.tembo@example.com | Verified plumber, Chilenje. Package "Sink and tap repair" K350 |
| Joseph Banda | joseph.banda@example.com | Verified electrician, Matero. Socket installation K280 |
| Mary Zulu | mary.zulu@example.com | Featured cleaner, Woodlands. House clean K400 |
| Samuel Daka | samuel.daka@example.com | Pool cleaner, Kabulonga. Pool service K650 |
| Brian Sakala | brian.sakala@example.com | Skilled student, Chilenje. Furniture assembly K150 |
| Chanda Phiri | chanda.phiri@example.com | Business client, Kabulonga Academy (two campuses) |
| Mutale Zulu | mutale.zulu@example.com | Admin |
| Oliver Nkhoma | oliver.nkhoma@example.com | Plumber, not verified, hidden from search |

## Demo script

Natasha's kitchen sink is leaking. She searches plumbers near Chilenje, books Grace's fixed package, pays MTN MoMo into escrow, Grace does the job, Natasha confirms, and both leave 5 stars. Commission default is 12%, so K350 becomes a K42 commission and a K308 payout.

```bash
BASE=http://127.0.0.1:8000/api

TOKEN=$(curl -s -X POST $BASE/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"natasha.mwila@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s "$BASE/providers?category=plumbing&latitude=-15.4455&longitude=28.3345&radius_km=15" \
  -H "Authorization: Bearer $TOKEN"

curl -s -X POST $BASE/bookings \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"package_id":PACKAGE_ID,"scheduled_at":"2026-11-22T09:00:00+02:00","address":"House 12, Chilenje South","area":"Chilenje","notes":"Kitchen sink is leaking"}'

curl -s -X POST $BASE/bookings/BOOKING_ID/pay \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"method":"mobile_money","mobile_money_provider":"mtn_momo","mobile_money_number":"0961111001"}'

GTOKEN=$(curl -s -X POST $BASE/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"grace.tembo@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s -X POST $BASE/bookings/BOOKING_ID/accept -H "Authorization: Bearer $GTOKEN"
curl -s -X POST $BASE/bookings/BOOKING_ID/start -H "Authorization: Bearer $GTOKEN"
curl -s -X POST $BASE/bookings/BOOKING_ID/complete -H "Authorization: Bearer $GTOKEN"

curl -s -X POST $BASE/bookings/BOOKING_ID/confirm -H "Authorization: Bearer $TOKEN"
curl -s $BASE/bookings/BOOKING_ID/ledger -H "Authorization: Bearer $TOKEN"

curl -s -X POST $BASE/bookings/BOOKING_ID/ratings \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"stars":5,"comment":"Fixed the leak."}'
curl -s -X POST $BASE/bookings/BOOKING_ID/ratings \
  -H "Authorization: Bearer $GTOKEN" -H 'Content-Type: application/json' \
  -d '{"stars":5,"comment":"Easy access."}'
```

Register a new customer:

```bash
curl -s -X POST $BASE/register -H 'Content-Type: application/json' -d '{
  "name":"New Customer","email":"new.customer@example.com","phone":"0960000099",
  "password":"password","password_confirmation":"password","role":"customer","area":"Chilenje"
}'
```

Mock AI estimate (keyword rules, no API key):

```bash
curl -s -X POST $BASE/ai/estimate \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"description":"My kitchen sink is leaking badly under the cupboard"}'
```

Send `Authorization: Bearer <token>` on every route below except register, login and categories.

## Endpoints

| Method | Path | Who |
| --- | --- | --- |
| POST | `/api/register` | public. role: `customer`, `provider`, `business_client` |
| POST | `/api/login` | public |
| POST | `/api/logout` | any |
| GET | `/api/me` | any |
| GET | `/api/categories` | public |
| GET | `/api/providers?category=&area=&latitude=&longitude=&radius_km=&sort=distance\|rating\|price` | any authenticated |
| GET | `/api/providers/{id}` | any authenticated |
| GET | `/api/providers/{id}/ratings` | any authenticated |
| POST | `/api/bookings` | customer, business. body: `package_id`, `scheduled_at`, `address`, `area` |
| GET | `/api/bookings` | own bookings (admin sees all) |
| GET | `/api/bookings/{id}` | participants |
| POST | `/api/bookings/{id}/pay` | customer. `method` `mobile_money` or `wallet` |
| POST | `/api/bookings/{id}/accept` | provider. escrow must already be funded |
| POST | `/api/bookings/{id}/start` | provider |
| POST | `/api/bookings/{id}/complete` | provider |
| POST | `/api/bookings/{id}/confirm` | customer. releases escrow |
| POST | `/api/bookings/{id}/ratings` | customer and provider. `stars` 1-5 |
| GET | `/api/bookings/{id}/ledger` | participants. payment, hold, release, commission, payout |
| POST | `/api/ai/estimate` | customer, business. `description`, optional `photo` |
| GET | `/api/provider/profile` | provider |
| PUT | `/api/provider/profile` | provider |
| GET | `/api/provider/earnings` | provider |
| POST | `/api/job-requests` | customer posts a job |
| GET | `/api/job-requests` | own jobs, or open jobs for a provider |
| POST | `/api/job-requests/{id}/quotes` | provider |
| POST | `/api/quotes/{id}/accept` | customer. creates a booking |
| POST | `/api/bookings/{id}/cancel` | participant |
| POST | `/api/bookings/{id}/dispute` | customer, with `evidence` photo |
| POST | `/api/admin/providers/{id}/verify` | admin |
| GET | `/api/admin/analytics` | admin |
| GET | `/api/business` | business client |
| POST | `/api/business/wallet/topup` | business client |

Booking states: `requested`, `accepted`, `in_progress`, `completed`, `disputed`, `cancelled`, `refunded`.

Escrow balance is the sum of `escrow_delta` on the ledger. A payment row records the mock mobile-money receipt. A hold row puts that amount in escrow. Release, commission and payout rows are written when the customer confirms the job.
