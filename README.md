# Bumpa Senior Backend Engineer Assessment

An event-driven achievements, badges, and cashback service for an e-commerce
store. Every completed purchase can unlock achievements; unlocking enough
achievements unlocks a badge; unlocking a badge triggers an automatic cashback
payout through a local payment provider.

## Contents

- [Architecture & design choices](#architecture--design-choices)
- [Cashback payment provider](#cashback-payment-provider)
- [Running the project](#running-the-project)
- [API](#api)
- [Running the tests](#running-the-tests)
- [Known limitations](#known-limitations)

## Architecture & design choices

The domain is modeled as a chain of events rather than one long service
method, so each concern can be tested, retried, and reasoned about on its
own:

```
PurchaseService::createCompletedPurchase()
        │
        ▼
  PurchaseCompleted  ──▶  ProcessPurchaseAchievements  ──▶  AchievementService::process()
                                                                     │
                                                     (unlocks 0+ achievements)
                                                                     ▼
                                                          AchievementUnlocked  ──▶  ProcessBadgeUnlock
                                                                                          │
                                                                          (unlocks 0+ badges)
                                                                                          ▼
                                                                                   BadgeUnlocked  ──▶  ProcessCashback
                                                                                                              │
                                                                                                              ▼
                                                                                                    PaymentService → PaymentProvider
```

A few choices worth calling out:

- **Events are dispatched only after their triggering transaction commits.**
  `PurchaseService` and `AchievementService` write inside `DB::transaction()`
  and dispatch their event *after* the closure returns. This avoids a
  listener acting on a purchase or achievement that later gets rolled back.
- **`ProcessCashback` is queued (`ShouldQueue`)**, unlike the other listeners.
  It's the one step that talks to an external-ish payment provider, so it's
  the one that should be retried independently of the HTTP request that
  triggered the purchase — that's what the `worker` container in
  `docker-compose.yml` is for. The achievement/badge listeners stay
  synchronous because they're cheap, purely internal, and the achievements
  endpoint should reflect state immediately after a purchase.
- **Idempotency is enforced at the database level, not just in application
  code.** `user_achievements` is unique on `(user_id, achievement_id)`,
  `user_badges` on `(user_id, badge_id)`, and `cashback_payments` on
  `(user_id, badge_id)`. `AchievementService::unlock()` and
  `ProcessBadgeUnlock` both write through `createOrFirst()` rather than
  `create()`, so if two purchases for the same user are ever processed
  concurrently and both pass the initial "already unlocked?" check before
  either has inserted, the loser's insert hits the unique constraint and
  falls back to the winner's row (via Laravel's portable
  `UniqueConstraintViolationException` handling) instead of throwing a
  500 or silently double-dispatching the unlock event —
  `wasRecentlyCreated` is what decides whether *this* process is the one
  that gets to dispatch `AchievementUnlocked`/`BadgeUnlocked`.
  `ProcessCashback` goes one step further and takes an explicit row lock
  (`lockForUpdate()`) for the rest of its transaction, because unlike a
  single insert it also has to serialize the "is this still pending?
  then actually call the payment provider" decision — two workers
  handling the same (retried or duplicated) `BadgeUnlocked` event must not
  both observe `status = "pending"` and both send the payout.
- **`PaymentProvider` is an interface** (`App\Contracts\PaymentProvider`),
  with two implementations: `LocalPaymentProvider` (simulated — logs and
  returns true) and `PaystackPaymentProvider` (a real integration — see
  [Cashback payment provider](#cashback-payment-provider)). `AppServiceProvider`
  picks between them based on config, so nothing else in the
  achievement/badge/cashback chain needs to know which one — or that a real
  payment API exists — at all.
- **Money is stored as integers in the currency's minor unit** (kobo, not
  naira) throughout — `products.price`, `purchases.total_amount`,
  `cashback_payments.amount` — to avoid floating point rounding on money.
- **Achievements are grouped** (`achievement_groups`) so the "next available
  achievement" logic in the achievements endpoint only has to return the
  next *unlocked-in-order* achievement per group, rather than every locked
  achievement.
- **The payout destination lives directly on `users`** (`bank_account_number`,
  `bank_code`, `bank_account_name`, plus a cached `paystack_recipient_code`),
  not a separate `payment_accounts` table. An earlier draft modeled that as
  its own table/model/relation for a hypothetical one-to-many that doesn't
  exist in this assessment — every user has exactly one payout destination,
  so four columns on `users` is the whole feature, actually wired into
  `ProcessCashback` → `CashbackPayout` → `PaystackPaymentProvider`, and
  seeded for every factory-made user so the flow is exercisable end to end.

## Cashback payment provider

The assessment allows choosing any local payment provider for the 300 Naira
badge cashback. This project uses **Paystack**, with a simulated fallback so
the whole system still runs — and demonstrably works end to end — with zero
external accounts required:

- Leave `PAYSTACK_SECRET_KEY` unset (the default in `.env.example`) and
  `AppServiceProvider` binds `LocalPaymentProvider`, which logs the payout
  and returns `true`. This is what `docker compose up --build` gives you out
  of the box.
- Set `PAYSTACK_SECRET_KEY` to a Paystack **test** secret key (from your own
  Paystack dashboard, in Test Mode — no real money moves) and
  `PaystackPaymentProvider` is bound instead. It makes two real calls per
  first-time payout: `POST /transferrecipient` to register the user's bank
  account as a recipient (cached afterwards on `users.paystack_recipient_code`
  so repeat payouts for the same user don't re-register one), then
  `POST /transfer` to actually send the money, using the same
  `cashback-{user}-{badge}` reference `ProcessCashback` already generates so
  Paystack itself also rejects a duplicate.
- Either way, a user with no `bank_account_number`/`bank_code` on file simply
  can't be paid — the provider logs a warning and returns `false`, which
  `ProcessCashback` turns into `status = "failed"` (retryable, not an
  exception). Seeded/factory-made users always have these set (see
  `UserFactory`) so the flow is testable without a manual account-onboarding
  step this assessment doesn't ask for.

Paystack's test mode accepts any 10-digit account number against a real
Nigerian bank code (044 / Access Bank is what's seeded) — see
[Paystack's transfer docs](https://paystack.com/docs/transfers/) if you want
to point this at your own test account.

## Running the project

Requires Docker and Docker Compose v2.20+ (for
`depends_on: condition: service_completed_successfully`).

```bash
git clone <this-repo>
cd bumpa-assessment
docker compose up --build
```

That's it — no manual `.env` setup, `composer install`, `key:generate`, or
`migrate` step required. On first run:

1. The `migrate` service builds the image, generates `.env` from
   `.env.example` and an `APP_KEY` if one isn't already present, waits for
   Postgres, then runs migrations and seeders once and exits.
2. `app` and `worker` wait for `migrate` to finish successfully before they
   start, so there's no race between two containers trying to apply the
   same migration.
3. The API is available at `http://localhost:8000` (override with
   `APP_PORT` in `.env`). `worker` runs `queue:work` against Redis for
   queued listeners (currently just `ProcessCashback`).

A named `vendor` Docker volume is used (instead of relying on the bind-mounted
`vendor/` from the host) specifically so a fresh clone — which has no local
`vendor/` directory, since it's gitignored — doesn't have the host bind mount
shadow the `vendor/` that was installed inside the image during `docker build`.

Seeded data includes: an NGN currency, five sample products, a "Purchases"
achievement group with four achievements (First Purchase, 5/10/20 Purchases),
three badges (Starter/Advanced/Expert), a `badge_cashback_amount` setting
(₦300 in kobo), and three demo users (`test@example.com` / `password`, plus
two random ones), each with a fake bank account on file so their cashback
payouts are payable from the moment they unlock a badge. Re-running
`docker compose up` is safe — seeders use `updateOrCreate`/`firstOrCreate`
and won't duplicate rows.

To use real Paystack test-mode transfers instead of the simulated provider,
set `PAYSTACK_SECRET_KEY` in `.env` before `docker compose up` (see
[Cashback payment provider](#cashback-payment-provider)).

### Troubleshooting

- **`role "bumpa" does not exist` / `migrate` container exits immediately.**
  Postgres only runs its `POSTGRES_USER`/`POSTGRES_DB` initialization the
  *first* time its data volume is created — if `postgres_data` already
  exists from an earlier `docker compose up` (with different credentials, or
  from an interrupted first run), Postgres skips init and the configured
  user never gets created. Fix: `docker compose down -v` (the `-v` removes
  the named volumes, including `postgres_data`) and run
  `docker compose up --build` again. This only bites repeat local runs —
  a genuinely fresh `git clone` has no pre-existing volume to collide with.
- **`exec ./docker-entrypoint.sh: permission denied`.** Fixed as of this
  commit — see the comment on `ENTRYPOINT` in the `Dockerfile`. If you're on
  an older checkout: `chmod +x docker-entrypoint.sh` and rebuild.

### Running locally without Docker

```bash
composer install
cp .env.example .env
php artisan key:generate
# point DB_CONNECTION/DB_HOST/etc at a Postgres instance, or switch to sqlite
php artisan migrate --seed
php artisan serve
```

## API

### `GET /users/{user}/achievements`

Returns the user's achievement and badge progress.

```json
{
  "unlocked_achievements": ["First Purchase", "5 Purchases"],
  "next_available_achievements": ["10 Purchases"],
  "current_badge": "Starter",
  "next_badge": "Advanced",
  "remaining_to_unlock_next_badge": 1
}
```

Returns `404` for a nonexistent user.

### `POST /users/{user}/purchases`

Records a completed purchase for the user, which synchronously unlocks any
achievements the purchase count now qualifies for, which in turn synchronously
unlocks any badges, and queues the resulting cashback payout(s).

Request:

```json
{
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 3, "quantity": 1 }
  ]
}
```

Response (`201`):

```json
{
  "reference": "PUR-AB12CD34EF56GH78",
  "status": "completed",
  "currency": "NGN",
  "total_amount": 3200000,
  "purchased_at": "2026-08-24T10:00:00.000000Z",
  "items": [
    { "product": "Nike Air Max", "quantity": 2, "unit_price": 8500000, "total_price": 17000000 }
  ]
}
```

`422` is returned both for request-shape validation failures (missing items,
unknown `product_id`, `quantity < 1`) and for domain-rule violations that
can't be expressed as simple field validation — an inactive product, or mixing
products priced in different currencies in one purchase.

Try it end to end:

```bash
curl -X POST http://localhost:8000/users/1/purchases \
  -H "Content-Type: application/json" \
  -d '{"items": [{"product_id": 1, "quantity": 1}]}'

curl http://localhost:8000/users/1/achievements
```

## Running the tests

```bash
docker compose exec app php artisan test
# or, without Docker:
php artisan test
```

The suite uses an in-memory SQLite database (see `phpunit.xml`) and runs the
queue synchronously (`QUEUE_CONNECTION=sync`), so `ShouldQueue` listeners
still execute inline during tests without needing `Queue::fake()`.

Coverage includes: achievement unlock rules (first purchase, purchase-count
thresholds, double-processing is a no-op, pending purchases don't count),
badge unlock thresholds and idempotency, the `createOrFirst()` race-safety
of achievement/badge unlocking under concurrent attempts, cashback
amount/reference generation, cashback idempotency under repeated event
handling, cashback provider *failure* (payment marked `failed`, and a failed
payment can be retried and later succeed without a duplicate row), the
`PaystackPaymentProvider` HTTP integration against a faked Paystack API
(recipient creation, recipient-code caching/reuse, transfer failure, a user
with no bank account on file) and which provider `AppServiceProvider` binds
under which config, the full purchase → achievement → badge → cashback chain
via `PurchaseService`, the same chain end-to-end over HTTP via the purchase
endpoint, and the achievements endpoint's response shape including the 404
case.

## Known limitations

These are deliberate scope cuts for the assessment, not oversights:

- **No authentication/authorization.** Both endpoints are open — anyone can
  create a purchase for, or read the achievement progress of, any user ID.
  A real deployment would put both behind Sanctum (or similar) and scope
  `users/{user}/*` to the authenticated user (or an admin).
- **`php artisan serve` in the container**, not php-fpm + nginx or Octane.
  Fine for this assessment's scale; not how I'd run this in production.
- **No rate limiting** on the purchase endpoint.
