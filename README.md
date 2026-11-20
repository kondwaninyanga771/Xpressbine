# Xpressbine API

Lusaka marketplace API for booking local, in-person trades (plumbers, electricians, cleaners and others). Payments are mock mobile money held in an escrow ledger. Amounts are Zambian kwacha (K).

This is the full first API version: bookings, escrow, refunds, a 48-hour auto-release job, client-absent safeguards, a Business Wallet, and a Filament admin panel. A React + Inertia frontend sits on the same app and calls this API.

## JSON data file

The app does not need a database server. `XPRESSBINE_DATA_DRIVER=json` (the default) keeps every table in `storage/app/data/db.json`. Each request locks that file, loads it, serves the existing Eloquent controllers, and writes the file back with an atomic rename. Set `XPRESSBINE_DATA_DRIVER=database` to use the `DB_*` connection instead and leave the JSON file alone.

Create or replace the file from the demo seeder:

```bash
php artisan xpressbine:json-reset
```

`--no-seed` migrates an empty document. Delete `storage/app/data/db.json` and run the command again to start over. The file is gitignored.

## Web app

The screens at `/` are React, rendered by Inertia and built with Vite. They do not duplicate booking or escrow rules. Each screen calls the JSON API with the Sanctum bearer token from `POST /api/login` (kept in the browser). Filament stays at `/admin`.

```bash
npm install
npm run build
php artisan serve
```

During development, `npm run dev` as well as `php artisan serve`.

| Screen | API |
| --- | --- |
| Feed, discover, map, provider profile | `GET /api/providers`, `GET /api/providers/{id}`, `GET /api/providers/{id}/ratings`, `GET /api/categories` |
| Problem estimate | `POST /api/ai/estimate` |
| Register and login | `POST /api/register`, `POST /api/login`, `GET /api/me` |
| Book, pay, track, rate | `/api/bookings` and the pay, accept, start, complete, confirm, cancel, ratings, ledger, dispute and photo routes |
| Provider dashboard | `/api/provider/profile`, `/api/provider/packages`, `/api/provider/photos`, `/api/provider/earnings` |
| Business wallet | `GET /api/business`, wallet top-up and statement |
| Trust desk (`/ops`) | `GET /api/admin/providers`, verify, reject, `GET /api/admin/disputes`, resolve |

Also on the web app: real conversations (`/messages`), job requests and quotes (`/jobs`), alerts, appeals, a monthly business plan, and a FinOps summary computed from bookings. Mobile-money rows are stored, but the carrier call is still simulated. WhatsApp and PDF reports are not included.

SQLite search does not return `distance_km` (it uses a bounding box, and the provider show distance query is Haversine SQL). Cards calculate kilometres from the latitude and longitude already on the provider user. The show request does not send coordinates, so that SQL is not run on SQLite.

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

Run the test suite:

```bash
php artisan test
```

The scheduler releases escrow after the confirmation window (48 hours by default). For a demo, run it by hand:

```bash
php artisan escrow:auto-release
```

In production, call the scheduler every minute:

```bash
* * * * * php /path/to/artisan schedule:run
```

Filament admin (admins only) is at `http://127.0.0.1:8000/admin/login`. Sign in as Mutale Zulu.

### PostgreSQL

Distance search uses a Haversine SQL query on PostgreSQL. `SearchService::distanceSql()` is the only place that query lives, so it can be swapped for PostGIS `ST_DWithin` later without changing the API. On SQLite the same endpoint uses a bounding box.

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
| `XPRESSBINE_CLAIM_WINDOW_HOURS` | `48` | How long a customer has to open a claim |
| `XPRESSBINE_FULL_REFUND_CANCEL_HOURS` | `24` | Cancel earlier than this for a full refund |
| `XPRESSBINE_LATE_CANCEL_FEE_RATE` | `0.15` | Provider fee when the customer cancels inside that window |
| `XPRESSBINE_CALL_OUT_FEE_RATE` | `0.15` | Fee when the customer is absent |
| `XPRESSBINE_NO_SHOW_GRACE_MINUTES` | `60` | How late a provider can be before a no-show refund |
| `XPRESSBINE_FIX_WINDOW_HOURS` | `48` | Time the provider gets to fix poor work |
| `XPRESSBINE_AI_DRIVER` | `mock` | `mock` (no API key) or `llm` |
| `XPRESSBINE_AI_API_KEY` | empty | Required only when the driver is `llm` |
| `XPRESSBINE_AI_BASE_URL` | OpenAI | OpenAI-compatible chat completions URL |
| `XPRESSBINE_AI_MODEL` | `gpt-4o-mini` | Model used when the driver is `llm` |

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
| Loveness Bwalya | loveness.bwalya@example.com | Carpenter, Matero. Door repair K500 |
| Tina Mulenga | tina.mulenga@example.com | Event planner, Woodlands. Party setup K2,500 |
| Chanda Phiri | chanda.phiri@example.com | Business client. Wallet seeded with K20,000. Two campuses |
| Mutale Zulu | mutale.zulu@example.com | Admin. Also signs in to the Filament panel |
| Oliver Nkhoma | oliver.nkhoma@example.com | Plumber, not verified, hidden from search |

Grace already has one completed sink repair for Natasha, with a 5-star rating, so search is not empty of reviews.

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

Open a claim before confirming. `evidence` is a photo:

```bash
curl -s -X POST $BASE/bookings/BOOKING_ID/dispute \
  -H "Authorization: Bearer $TOKEN" \
  -F 'reason=The sink is still leaking after the visit' \
  -F 'evidence=@leak.jpg'
```

An admin refunds it:

```bash
ATOKEN=$(curl -s -X POST $BASE/login -H 'Content-Type: application/json' \
  -d '{"email":"mutale.zulu@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s -X POST $BASE/admin/disputes/DISPUTE_ID/resolve \
  -H "Authorization: Bearer $ATOKEN" -H 'Content-Type: application/json' \
  -d '{"outcome":"full_refund","notes":"Work was not finished."}'
```

`outcome` is `full_refund`, `partial_refund` (send `amount`), `release`, or `fix_first`.

Client-absent booking (entry PIN is on the booking once it is created):

```bash
curl -s -X POST $BASE/bookings \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"package_id":PACKAGE_ID,"scheduled_at":"2026-11-22T09:00:00+02:00","address":"House 12, Chilenje South","area":"Chilenje","client_absent":true,"on_site_contact_name":"Auntie Banda","on_site_contact_phone":"0973333444"}'
```

The provider must upload a `before` photo before starting and an `after` photo before completing (`POST /api/bookings/BOOKING_ID/photos`, multipart `kind` and `photo`).

Business wallet top-up and payment (Chanda is already topped up):

```bash
curl -s -X POST $BASE/business/wallet/topup \
  -H "Authorization: Bearer $BTOKEN" -H 'Content-Type: application/json' \
  -d '{"amount":5000,"method":"mtn_momo"}'
curl -s -X POST $BASE/bookings/BOOKING_ID/pay \
  -H "Authorization: Bearer $BTOKEN" -H 'Content-Type: application/json' \
  -d '{"method":"wallet"}'
```

Mock AI estimate (keyword rules, no API key). Set `XPRESSBINE_AI_DRIVER=llm` and `XPRESSBINE_AI_API_KEY` to call a vision model instead:

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
| POST | `/api/bookings/{id}/cancel` | participant. full refund, or a late-cancel fee inside 24 hours |
| POST | `/api/bookings/{id}/no-show` | customer, after the grace period |
| POST | `/api/bookings/{id}/customer-absent` | provider. call-out fee, rest refunded |
| POST | `/api/bookings/{id}/photos` | participant. multipart `kind` = `before`, `after`, or `evidence` |
| POST | `/api/bookings/{id}/dispute` | customer, with `evidence` photo, inside the claim window |
| POST | `/api/disputes/{id}/respond` | provider |
| POST | `/api/admin/disputes/{id}/resolve` | admin. `full_refund`, `partial_refund`, `release`, `fix_first` |
| POST | `/api/admin/providers/{id}/verify` | admin |
| POST | `/api/admin/users/{id}/moderate` | admin. `warning`, `suspend`, `ban`, `reinstate` |
| GET | `/api/admin/analytics` | admin |
| GET | `/api/admin/ledger` | admin |
| GET | `/api/business` | business client. wallet balance and site spend |
| POST | `/api/business/wallet/topup` | business client |
| GET | `/api/business/wallet/statement` | business client |
| POST | `/api/provider/appeals` | banned or suspended provider, one appeal |

Booking states: `requested` → `accepted` → `in_progress` → `completed`. From `completed` a claim moves it to `disputed`, then an admin can refund it (`refunded`), send it back for a fix (`in_progress`), or release the escrow (`completed`). Cancel before the job starts ends in `cancelled` or `refunded`.

Escrow balance is the sum of `escrow_delta` on the ledger. A payment or wallet-payment row records where the money came from. A hold row puts that amount in escrow. Release, commission and payout rows are written when the customer confirms the job or the scheduler does it after the confirmation window. Refunds, partial refunds and call-out fees are their own rows.

Not in this version: a live MTN, Airtel or Zamtel network call (payments are stored as `simulated_success` transactions), WhatsApp or SMS, PDF reports, and the PostGIS extension itself. Chat, monthly plans and the FinOps summary are stored.
