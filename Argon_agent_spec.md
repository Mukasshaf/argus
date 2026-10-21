# StayBridge Agent Build Spec

Audience: autonomous coding agent running unattended. This document is the single source of truth for what to build and how.

## 0. Execution protocol

1. Run unattended. Never ask the user questions. Where the spec is ambiguous, choose the simplest option consistent with section 2 and record it in `DECISIONS.md`.
2. Maintain `PROGRESS.md`: current phase, status per phase, last test results.
3. Commit after every phase with a clear message. Never leave the main branch with failing tests: fix before moving on, or revert the phase.
4. If a task still fails after 3 distinct fix attempts, write it to `BLOCKERS.md` (symptom, attempts, suspected cause), stub it behind its interface, and continue with the next task.
5. Never delete, skip, or weaken a test to make it pass.
6. Verify current versions and APIs marked "verify" (Laravel, PHP, Sail, queue middleware names) against official documentation or installed vendor code before using them.
7. Never commit secrets. Use `.env` (git ignored) and `.env.example`. Use fake data only.
8. Execute the AWS stage (section 12) only if AWS CLI credentials and a region are already configured in the environment. Otherwise generate the scripts and docs, mark the stage "not executed" in `PROGRESS.md`, and continue.
9. Code style: `declare(strict_types=1);`, PHP 8.3 enums, readonly DTOs, Laravel Pint. English only, no localization layer.
10. If time or budget runs short, follow the priority order in section 15.

## 1. Product definition

StayBridge is a simulated hotel channel manager. A hotel manages room inventory and rates once in a central extranet. StayBridge keeps three simulated online travel agencies (OTAs) in sync with that data, and ingests reservations coming back from them, while preventing or detecting overbooking.

Two deployable apps in one repository:
* `core`: the channel manager (extranet UI, inbound API, sync engine, ingestion, scheduler, worker).
* `otasim`: a simulator of three OTAs with a control panel and fault injection.

The project is a simulation. README must say so and list what real OTA integrations add (section 17).

Seed data, all fake and Indian:
* Hotel: "Lotus Residency", Bengaluru, code `LOTUS01`, timezone `Asia/Kolkata`, currency `INR`.
* Room types: Standard (`STD`, 10 rooms), Deluxe (`DLX`, 6 rooms), Suite (`STE`, 2 rooms).
* Rate plans per room type: `EP` (room only) and `CP` (breakfast included). Base prices in rupees: STD 3500 and 4000, DLX 5200 and 5700, STE 9800 and 10500.
* Channels: `raahi` (XML, "Raahi Stays", markup 1000 bps), `sarai` (JSON, "Sarai Direct", markup 1200 bps), `mela` (CSV, "Mela Bulk", markup 800 bps).
* External codes: rooms `STD`, `DLX`, `STE`; rates `EP`, `CP` on every channel.
* Guest names: random Indian names from a fixed fake list.

## 2. Locked design decisions

* D1. The central DB is the source of truth. Channels are replicas that converge. Push model.
* D2. Availability is stored per room type per date (`room_inventory`). Price is stored per rate plan per date (`rate_prices`). Each table has a surrogate `id` plus a unique key.
* D3. `room_inventory` has `capacity` (staff editable), `booked` (written only by reservation logic), and `available` as a generated stored column `capacity - booked`, signed. Negative `available` means oversold. Pushes use `max(0, available)`, and 0 when `stop_sell`.
* D4. Two reservation modes. Notification mode (the OTA already sold the room): always record, `available` may go negative, raise an oversold alert, no per reservation flag. Alerts auto resolve when `available >= 0` again. An "oversold nights" view lists dates with `available < 0` joined to holders ordered by `received_at`. Request mode (the OTA asks first): conditional decrement, reject when sold out.
* D5. Level triggered sync. Every inventory or price write bumps `version`. `channel_sync_state.pushed_version` records what each channel has. Dirty means `version > pushed_version`. A job pushes dirty rows as absolute values read at execution time, then advances `pushed_version` only to the version it actually pushed.
* D6. Inbound idempotency: `inbound_events` unique `(channel_id, external_event_id)`; `reservations` unique `(channel_id, external_id)` plus `external_version` compare. Every inbound message is a full reservation snapshot with a monotonic integer version. A modify for an unknown reservation is an upsert. A cancel for an unknown reservation creates a cancelled tombstone so a late lower version is ignored.
* D7. Error classes: `TransientChannelException` (timeouts, connection errors, 5xx, 429) retried by the job with backoff. `PermanentChannelException` (other 4xx, schema errors, unmapped codes) quarantined, never retried automatically. Per channel circuit breaker: open after 5 consecutive failures, half open after 2 minutes. The HTTP client does not retry. The job is the only retry layer.
* D8. Inventory rows are pre generated 540 days ahead by a scheduled command. Lock order is ascending by `room_type_id`, then `stay_date`.
* D9. Money is an unsigned integer in whole rupees. Channel markup in basis points (integer). Timestamps UTC. `stay_date` is a DATE in hotel local time. Nights are `[check_in, check_out)`.
* D10. Frontend: Blade, jQuery 3, plain CSS or Bootstrap 5. No React, Vue, Livewire, Inertia.
* D11. MySQL 8 in all environments including CI and tests. No SQLite for `core`.
* D12. Queue driver `database` locally, `sqs` on AWS. Queue names: `staybridge_ingest`, `staybridge_sync`. Cache store `database` (supports locks).
* D13. No Eloquent model events that dispatch jobs or call remote systems. Inventory rows are written only by `InventoryService`, `ReservationIngestService`, and `RealtimeBookingService`. `markPushed` writes only `channel_sync_state`.

## 3. Tech stack and where each piece is used

* PHP 8.3 and Laravel current stable (verify via `composer create-project laravel/laravel`). Do not select a starter kit using React, Vue, or Livewire. Authentication: Breeze Blade stack if still offered, else minimal session login (one `users` table, one seeded user, login throttling).
* Laravel Sail on Docker. Used for PHP, MySQL, worker, scheduler, simulator. On a Windows host use WSL2 and keep the repo in the Linux filesystem. Add `.gitattributes` with `* text=auto eol=lf`.
* MySQL 8 InnoDB: all state, row locking, generated column.
* Laravel Queues: `SyncChannelJob`, `IngestInboundEventJob`.
* Laravel Scheduler (`routes/console.php`): sync sweeper, polling, inventory extension, reconcile, pruning.
* `Http` facade (Guzzle): JSON and XML adapters. `Http::fake()` in tests.
* PHP `DOMDocument` or `SimpleXMLElement`: XML adapter. No extra library.
* `fgetcsv` or `SplFileObject` plus `Storage` local disk: CSV adapter emulating SFTP drop folders.
* PHPUnit (Laravel default), Pint, Larastan level 5. Do not mix Pest and PHPUnit.
* `symfony/process` (ships with Laravel): parallel race tests.
* AWS (optional stage): EC2, RDS MySQL, SQS plus DLQ, CloudWatch Logs, SSM Parameter Store, IAM instance profile. `aws/aws-sdk-php` for the SQS driver.
* GitHub Actions: CI.

## 4. Architecture and connections

Components:
* `core` (Laravel): extranet UI, inbound API, sync engine, ingestion, scheduler, worker.
* `otasim` (separate small Laravel app, own SQLite file, own port 8081): three fake OTAs plus control panel.
* MySQL is owned by `core` only. `otasim` has no access to it.
* Connections: `core` calls `otasim` over HTTP (Raahi, Sarai) and a shared filesystem volume (Mela). `otasim` calls `core` over HTTP (signed webhooks for Raahi, real time booking requests for Sarai). Both directions use base URLs from env (`SIM_BASE_URL` in `core`, `CORE_BASE_URL` in `otasim`).

Outbound flow (central change to channels):
1. Staff edits capacity, stop sell, or price, or a reservation changes `booked`. The write bumps `version` in the same transaction.
2. After commit, code dispatches `SyncChannelJob` per active channel with a 3 second delay (debounce; uniqueness drops duplicates). The scheduler also dispatches every minute as a safety net.
3. The job (unique per channel) loops: ask `SyncPlanner` for dirty rows, coalesce consecutive dates with equal values into ranges, call the adapter, log to `sync_logs`, advance `pushed_version` with seen versions. The loop is bounded by a time budget.
4. Transient failure: release with backoff. Permanent failure: quarantine rows. Repeated failure: circuit opens and the channel shows degraded.

Inbound flow (notification channels, Raahi webhook, Mela files, Sarai polling):
1. The simulator sends a signed webhook, or the poller fetches new items.
2. The controller verifies the HMAC, stores `inbound_events`, dispatches `IngestInboundEventJob`, returns 202.
3. The job parses via the adapter into `ReservationEvent`, calls `ReservationIngestService::apply`, then triggers outbound sync for all channels (source channel included).

Inbound flow (request mode, Sarai new bookings only): synchronous HTTP call, conditional decrement, immediate accept or reject, then outbound sync. Sarai modifications and cancellations arrive by polling.

README must include a Mermaid diagram of these flows.

## 5. Repository layout

```
staybridge/                      (repo root)
  core/                          (Laravel app)
    app/Channels/{ChannelAdapter.php, ChannelAdapterFactory.php, Dto/, Exceptions/, Raahi/RaahiAdapter.php, Sarai/SaraiAdapter.php, Mela/MelaAdapter.php, Mela/RemoteFileStore.php}
    app/Services/{InventoryService.php, RateService.php, ReservationIngestService.php, RealtimeBookingService.php, SyncPlanner.php}
    app/Jobs/{SyncChannelJob.php, IngestInboundEventJob.php}
    app/Console/Commands/{DispatchSync, PollChannels, ExtendInventory, Reconcile, VerifySync}.php
    app/Http/Controllers/{Extranet/*, Inbound/*}
    app/Http/Middleware/VerifyChannelSignature.php
    resources/views, resources/js/extranet.js
    tests/{Unit, Feature, Concurrency, Integration}
  otasim/                        (second Laravel app)
  docker/                        (Dockerfile.prod, compose.prod.yml, nginx.conf)
  scripts/                       (deploy.sh, teardown.sh, race_loop.sh)
  .github/workflows/ci.yml
  Makefile, README.md, DECISIONS.md, PROGRESS.md, BLOCKERS.md, .gitattributes
```

## 6. Data model

Laravel default tables: `jobs`, `failed_jobs`, `cache`, `cache_locks`, `sessions`, `users`.

* `hotels`: id, name, city, code, timezone (default Asia/Kolkata), currency (default INR).
* `room_types`: id, hotel_id, code (unique per hotel), name, total_rooms.
* `rate_plans`: id, room_type_id, code, name, base_price (unsigned int).
* `room_inventory`: id, room_type_id, stay_date, capacity smallint signed, booked smallint signed, available smallint STORED GENERATED `capacity - booked`, stop_sell bool, version bigint unsigned, updated_at. Unique `(room_type_id, stay_date)`. Columns must be signed: unsigned subtraction errors in MySQL. In Laravel use `->storedAs('capacity - booked')`.
* `rate_prices`: id, rate_plan_id, stay_date, price unsigned int, version bigint. Unique `(rate_plan_id, stay_date)`.
* `channels`: id, code unique, name, protocol enum(xml, json, csv), base_url, credentials (`encrypted:array`), webhook_secret (encrypted), markup_bps int, supports_webhook, supports_polling, supports_realtime, status enum(active, degraded, paused), consecutive_failures, circuit_open_until nullable, last_success_at, poll_cursor nullable.
* `channel_room_mappings`: id, channel_id, room_type_id, external_room_code. Unique `(channel_id, room_type_id)` and `(channel_id, external_room_code)`.
* `channel_rate_mappings`: id, channel_id, rate_plan_id, external_rate_code. Unique `(channel_id, rate_plan_id)` and `(channel_id, external_rate_code)`.
* `channel_sync_state`: id, channel_id, kind enum(availability, rate), subject_id (room_type_id or rate_plan_id), stay_date, pushed_version bigint signed default -1, attempts, blocked_at nullable, last_error nullable. Unique `(channel_id, kind, subject_id, stay_date)`. Rows exist only for mapped subjects. Starting at -1 makes the first run a full sync.
* `reservations`: id, hotel_id, room_type_id, rate_plan_id, channel_id, external_id, external_version int, status enum(confirmed, cancelled), check_in date, check_out date, guest_name, adults, total_price, currency, inbound_event_id nullable, received_at, timestamps. Unique `(channel_id, external_id)`. Index `(hotel_id, check_in)`.
* `reservation_nights`: id, reservation_id, room_type_id, stay_date. Unique `(reservation_id, stay_date)`. Rows exist only for nights currently held. This ledger drives modify, cancel, and reconcile.
* `inbound_events`: id, channel_id, external_event_id, kind, payload longtext, status enum(received, processed, failed, ignored), error nullable, received_at, processed_at. Unique `(channel_id, external_event_id)`.
* `sync_logs`: id, correlation_id, channel_id, direction enum(out, in), operation, request_body (redacted, max 64 KB), response_status nullable, response_body (max 64 KB), duration_ms, attempt, outcome enum(ok, transient_fail, permanent_fail), error nullable, created_at. Indexes `(channel_id, outcome, created_at)`, `(correlation_id)`.
* `alerts`: id, type enum(oversold, channel_blocked, unmapped_code, drift), hotel_id nullable, payload json, resolved_at nullable, acknowledged_at nullable, created_at.

## 7. Core implementations

### 7.1 Channel adapter contract

```php
interface ChannelAdapter
{
    public function capabilities(): Capabilities;
    /** @param list<AvailabilityRange> $ranges absolute values, clamped, mapped to external codes */
    public function pushAvailability(Channel $c, array $ranges, string $correlationId): void;
    /** @param list<RateRange> $ranges channel markup already applied */
    public function pushRates(Channel $c, array $ranges, string $correlationId): void;
    public function pullReservations(Channel $c, ?string $cursor): ReservationBatch; // events plus next cursor
    /** @return list<ReservationEvent> */
    public function parseInbound(Channel $c, string $rawBody, array $headers): array;
}
```

`ReservationEvent` (readonly): channelId, externalId, externalVersion, type (upsert, cancel), roomCode, rateCode, checkIn, checkOut, guestName, adults, totalPrice, currency, externalEventId.

Adapters throw only `TransientChannelException` or `PermanentChannelException`. Mapping: timeout, connection error, 5xx, 429 (carry `Retry-After`) are transient. Other 4xx and XML `<Errors>` validation codes are permanent. Each adapter call writes one `sync_logs` row, success or failure, with secrets redacted. `ChannelAdapterFactory` resolves by `channels.protocol`.

### 7.2 Inventory writes

```php
public function setCapacity(int $roomTypeId, array $dates, int $capacity): void
{
    DB::transaction(function () use ($roomTypeId, $dates, $capacity) {
        foreach (array_chunk($dates, 500) as $chunk) {
            DB::table('room_inventory')
              ->where('room_type_id', $roomTypeId)->whereIn('stay_date', $chunk)
              ->update(['capacity' => $capacity, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        }
    });
    SyncKick::allChannels(); // dispatch(...)->delay(3), after commit
}
```

Build the date list in PHP (Carbon `isoWeekday` for weekday filters). Do not use SQL `WEEKDAY()`. Reject capacity below current `booked` in the extranet (oversold states arise only from OTA notifications).

### 7.3 Sync planner and job

Dirty query (availability), excluding dates before hotel local today:

```sql
SELECT s.id AS state_id, s.subject_id AS room_type_id, s.stay_date,
       GREATEST(ri.available, 0) AS avail, ri.stop_sell, ri.version
FROM channel_sync_state s
JOIN room_inventory ri ON ri.room_type_id = s.subject_id AND ri.stay_date = s.stay_date
WHERE s.channel_id = ? AND s.kind = 'availability'
  AND s.blocked_at IS NULL AND ri.version > s.pushed_version
  AND s.stay_date >= ?
ORDER BY s.subject_id, s.stay_date
LIMIT 2000;
```

Coalesce consecutive dates with equal `(avail, stop_sell)` per room type into ranges. The batch object carries `[state_id => version_seen]`. Rates work the same with `rate_prices`; channel price is `intdiv(price * (10000 + markup_bps) + 5000, 10000)`.

`markPushed` must write the seen version per row, never the current one:

```sql
UPDATE channel_sync_state
SET pushed_version = CASE id WHEN :id1 THEN :v1 WHEN :id2 THEN :v2 /* ... */ END
WHERE id IN (:id1, :id2 /* ... */) AND pushed_version < CASE id WHEN :id1 THEN :v1 WHEN :id2 THEN :v2 /* ... */ END;
```

A row changed after the planner read it keeps `version > pushed_version` and is pushed again on the next loop.

```php
class SyncChannelJob implements ShouldQueue, ShouldBeUnique
{
    public int $tries = 6;
    public int $timeout = 90;
    public int $uniqueFor = 300;            // a crashed worker cannot hold the lock forever
    public function __construct(public int $channelId) { $this->onQueue('staybridge_sync'); }
    public function uniqueId(): string { return (string) $this->channelId; }
    public function backoff(): array { return [10, 30, 90, 270, 600]; } // add small random jitter

    public function handle(ChannelAdapterFactory $f, SyncPlanner $planner): void
    {
        $channel = Channel::findOrFail($this->channelId);
        if ($channel->circuitIsOpen()) return;
        $adapter = $f->for($channel);
        $corr = (string) Str::uuid();
        $deadline = now()->addSeconds(60);                 // time budget below the job timeout
        while (now()->lt($deadline) && ($batch = $planner->nextBatch($channel))) {   // re queries dirty rows each loop
            try {
                $batch->kind === 'availability'
                    ? $adapter->pushAvailability($channel, $batch->ranges, $corr)
                    : $adapter->pushRates($channel, $batch->ranges, $corr);
                $planner->markPushed($channel, $batch);
                $channel->recordSuccess();
            } catch (TransientChannelException $e) {
                $channel->recordFailure();                 // opens the circuit after 5
                $this->release($e->retryAfter ?? $this->backoff()[min($this->attempts() - 1, 4)]);
                return;
            } catch (PermanentChannelException $e) {
                $planner->quarantine($channel, $batch, $e->getMessage());  // sets blocked_at
                Alert::channelBlocked($channel, $e);
            }
        }
        // Leftovers (budget exhausted) are picked up by the every minute scheduler. Never self dispatch here.
    }

    public function failed(Throwable $e): void { /* log with channel and correlation context */ }
}
```

Why no self dispatch: the unique lock is held until `handle` returns, so a dispatch from inside would be dropped. Kicks arriving while the job runs are also dropped, which is safe because the loop re queries dirty rows and the scheduler is the backstop. Verify lock behavior on `release()` and `failed_jobs` retries on the installed Laravel version and cover it with a test.

Scheduler (`routes/console.php`):

```php
Schedule::command('staybridge:dispatch-sync')->everyMinute()->withoutOverlapping();
Schedule::command('staybridge:poll-channels')->everyMinute()->withoutOverlapping();
Schedule::command('staybridge:extend-inventory --days=540')->dailyAt('03:00');
Schedule::command('staybridge:reconcile')->hourly();
Schedule::command('model:prune')->daily();   // sync_logs older than 14 days, inbound_events older than 30 days
```

### 7.4 Reservation ingestion (notification mode)

Claim the reservation row first, then lock inventory in order:

```php
public function apply(ReservationEvent $e): IngestResult
{
    return DB::transaction(function () use ($e) {
        $room = ChannelRoomMapping::resolve($e->channelId, $e->roomCode);
        $rate = ChannelRateMapping::resolve($e->channelId, $e->rateCode);
        if (!$room || !$rate) throw new UnmappedCodeException($e);          // permanent

        // 1. claim: insert a version 0 placeholder if absent, then lock the row
        DB::table('reservations')->insertOrIgnore($this->placeholder($e, $room, $rate)); // external_version = 0
        $res = Reservation::where('channel_id', $e->channelId)
                ->where('external_id', $e->externalId)->lockForUpdate()->first();
        if ($e->externalVersion <= $res->external_version) return IngestResult::Duplicate;

        // 2. diff nights
        $old = $res->nights()->pluck('stay_date')->all();
        $oldRoom = $res->room_type_id;
        $new = $e->type === 'cancel' ? [] : Nights::between($e->checkIn, $e->checkOut); // checkout excluded
        // Room type change on modify: release all old nights on $oldRoom, take all new nights on $room->room_type_id.

        // 3. lock inventory rows ascending, verify they exist
        $rows = DB::table('room_inventory')->where('room_type_id', $room->room_type_id)
            ->whereIn('stay_date', $touchDates)->orderBy('stay_date')->lockForUpdate()->get();
        if ($rows->count() !== count($touchDates)) throw new MissingInventoryException($e);

        // 4. apply: booked = booked - 1 for released nights, + 1 for taken nights; version = version + 1
        // 5. update reservation fields, external_version, status; replace reservation_nights
        // 6. oversold check on taken nights: if any available < 0, Alert::oversold(...)
        return IngestResult::Applied;
    }, 5);   // retries on deadlock
}
```

When a modify touches two room types, lock in ascending `(room_type_id, stay_date)` order. After commit (outside the transaction), trigger outbound sync for all channels and re evaluate open oversold alerts (resolve when no night with `available < 0` remains in the alert's range).

Note for README: `UPDATE` already takes exclusive row locks and `booked = booked + 1` is atomic. The explicit locking select exists to enforce a consistent lock order, verify row existence, and serialize operations. The reservation row lock protects the ledger from concurrent duplicates, modifies, and cancels of one reservation.

### 7.5 Request mode (Sarai real time booking)

```php
try {
    DB::transaction(function () use ($roomTypeId, $dates, $evt) {
        if ($existing = Reservation::where('channel_id', $evt->channelId)->where('external_id', $evt->externalId)->first()) {
            $this->replay = $existing; return;                     // idempotent retry
        }
        $n = DB::update(
            'UPDATE room_inventory SET booked = booked + 1, version = version + 1
             WHERE room_type_id = ? AND stay_date IN (' . implode(',', array_fill(0, count($dates), '?')) . ')
               AND stop_sell = 0 AND capacity - booked >= 1',
            [$roomTypeId, ...$dates]);
        if ($n !== count($dates)) throw new SoldOutException();   // rolls back everything
        // insert reservation (external_version = value sent by the OTA, normally 1) and nights
    }, 5);
} catch (SoldOutException) { /* respond 409 rejected */ }
```

Respond 201 `{"status":"confirmed"}` or 409 `{"status":"rejected","reason":"sold_out"}`. A retried request for an existing `(channel, external_id)` returns the original 201. The later polled copy of the same reservation has an equal version and is ignored as a duplicate. Write an inbound `sync_logs` row. Sync fan out after commit.

### 7.6 Inbound authentication

`VerifyChannelSignature` middleware: read `X-Timestamp` and `X-Signature` (`sha256=<hex>`). Reject when skew exceeds 300 seconds. Compute `hash_hmac('sha256', $ts . '.' . $rawBody, $channel->webhook_secret)` and compare with `hash_equals`. Throttle inbound routes with a named limiter per channel. Responses: 401 bad signature, 400 malformed body, 413 body over 1 MB, 202 accepted.

### 7.7 Outbound authentication and limits

* Raahi (XML): static API key header.
* Sarai (JSON): OAuth2 client credentials. Cache the token until expiry minus 60 seconds. On 401, refresh once and retry within the same attempt (token refresh, not a retry layer).
* Mela (CSV): no auth, file based.
* Per channel outbound rate limit via job middleware (`RateLimited` with a named limiter, verify API). A 429 honors `Retry-After`.
* HTTP client: `Http::timeout(10)->connectTimeout(3)`, no `->retry()`.

### 7.8 Commands

* `staybridge:extend-inventory --days=540`: creates missing `room_inventory`, `rate_prices`, and `channel_sync_state` rows for mapped subjects only. Idempotent. Uses hotel local today.
* `staybridge:dispatch-sync`: dispatches `SyncChannelJob` for channels with dirty rows, or whose circuit half open is due.
* `staybridge:poll-channels`: pulls reservations for polling channels. Sarai uses an opaque cursor from the simulator (monotonic sequence number). Mela reads new files. Writes `inbound_events` and dispatches ingest. The cursor advances only after events are persisted. Overlap is safe because of unique keys.
* `staybridge:reconcile`: for each room type and date asserts `booked == count(reservation_nights)`. On drift, raise an alert and print a diff. Never auto fix.
* `staybridge:verify-sync`: first checks there are no dirty or blocked rows and no pending jobs; if any, print "not converged yet" and exit 2. Otherwise fetch `GET /sim/{channel}/state` from the simulator and compare with expected central values (clamped availability, marked up prices, future dates only). Exit 1 on mismatch, 0 on match.

### 7.9 Security checklist

* Parse inbound XML with `LIBXML_NONET`, never `LIBXML_NOENT`; reject DOCTYPE declarations. Cap request body size.
* CSRF on all extranet writes. Use `$fillable` for mass assignment. Scope all extranet queries to the user's hotel.
* Login throttling. Hashed passwords. `APP_DEBUG=false` outside local.
* Webhook secrets and OAuth credentials encrypted at rest. Redact `Authorization`, tokens, and guest names in `sync_logs`.
* Parameter binding in all raw SQL.

## 8. Simulator (otasim)

Protocols are simplified and inspired by OpenTravel Alliance message names. State this in README.

### Raahi Stays (XML, webhook notification mode)
* `POST /raahi/availability` with `OTA_HotelAvailNotifRQ`: `AvailStatusMessages@HotelCode`, `AvailStatusMessage@BookingLimit`, `StatusApplicationControl@Start,End,InvTypeCode`.
* `POST /raahi/rates` with `OTA_HotelRateAmountNotifRQ`.
* Responds `<OTA_HotelAvailNotifRS><Success/></OTA_HotelAvailNotifRS>` or `<Errors><Error Code="..." ShortText="..."/></Errors>`.
* Sends signed webhooks to `core` with `OTA_HotelResNotifRQ`, `ResStatus` in (Commit, Modify, Cancel), `UniqueID`, an integer version attribute, and a full reservation snapshot.

### Sarai Direct (JSON, polling plus real time)
* `POST /sarai/oauth/token` (client credentials, short expiry).
* `PUT /sarai/v1/properties/{p}/rooms/{r}/availability` body `{"items":[{"date":"2026-12-01","available":3}]}`. `PUT .../rates`.
* `GET /sarai/v1/reservations?cursor={opaque}&limit=100` returns `{"items":[...],"next_cursor":"..."}` for `core` to poll (modifications and cancellations).
* The simulator calls `core` `POST /api/inbound/sarai/booking-requests` for real time new bookings.
* Can return 429 with `Retry-After`, 500, and 401 (expired token).

### Mela Bulk (CSV, file drop)
* `core` writes `inventory_{YmdHis}.csv` to a shared volume folder `sftp_out/mela/`. Columns: `hotel_code,room_code,rate_code,date,available,price,stop_sell`.
* The simulator writes `reservations_{ts}.csv` to `sftp_in/mela/`. The poller ingests unprocessed files (dedupe by external_id plus version) and moves them to `processed/` only after persistence.
* File access sits behind `RemoteFileStore` so real SFTP (`league/flysystem-sftp-v3`) could replace the local disk.

### Simulator controls
* `sim_config` per channel: mode (ok, http500, http429, timeout, malformed, reject400, token_expired), latency_ms, fail_rate, duplicate_webhook_probability, out_of_order_enabled.
* Control panel: create booking, modify, cancel, deliver duplicate, deliver out of order, "book the last room on Raahi and Sarai simultaneously", set fault mode.
* `GET /sim/{channel}/state`: stored availability and prices, used by `verify-sync`.
* The simulator never turns a received push into a booking. Bookings originate only from control panel actions or scenario scripts. The simulator holds the same webhook secrets (from env) as `core`.

## 9. Extranet UI (Blade plus jQuery)

* Layout, hotel selector, nav: Calendar, Reservations, Channels, Sync logs, Alerts.
* Calendar: month grid; rows are room types and rate plans; columns are dates. Cells show capacity, booked, available, price. Oversold cells highlighted. Prices shown as `₹` with Indian digit grouping (for example `₹1,23,456`) through a helper.
* Inline edit: click a cell, `$.ajax` PATCH, FormRequest validation, inline error text. CSRF via `$.ajaxSetup({headers: {'X-CSRF-TOKEN': ...}})`.
* Bulk edit modal: date range, weekday checkboxes, set capacity, stop sell, or price. One request, one transaction.
* Sync badge per cell and per channel: synced, pending (version greater than pushed), blocked. Poll `GET /extranet/sync-status` every 5 seconds with `$.getJSON`.
* Channels page: status, circuit state, failures, last success; buttons: unblock quarantined rows, retry now, pause.
* Sync logs: filters channel, outcome, date range, correlation id; paginated; expandable redacted request and response.
* Reservations: filters channel and status; "oversold nights" view (D4). Failed inbound events list with a reprocess button. Alerts page with acknowledge.

## 10. Testing

Setup: tests run against MySQL. `DatabaseTruncation` for concurrency tests, `RefreshDatabase` for the rest. Child processes receive `DB_DATABASE` of the test database through the environment.

Unit:
* Range coalescing, markup rounding, night enumeration (checkout excluded, month and year boundaries), seen version `CASE` update.
* XML build and parse against golden files per adapter, including DOCTYPE rejection. CSV round trip. HMAC verify (valid, stale, tampered).
* Error classification (status code to Transient or Permanent).

Feature:
* Ingestion: same webhook twice, out of order versions, cancel before create (tombstone), modify before create (upsert), modify extending or shortening a stay, room type change, unmapped code marks the event failed and alerts.
* Request mode: sold out returns 409; retried request returns the original 201; polled copy ignored.
* Extranet: bulk edit bumps versions; capacity below booked rejected; hotel scoping enforced.
* Sync with `Http::fake`: 500 then 200 recovers; 400 quarantines; 429 honors Retry-After; circuit opens after 5 failures then half opens; an edit during an in flight push stays dirty.
* Planner: a bulk change of 365 days yields a bounded number of requests; past dates excluded.

Concurrency (`tests/Concurrency`): use `symfony/process` to spawn N=20 artisan commands. Each child waits at a start barrier (sleep until a shared `start_at` microtime passed as an argument) so they truly overlap. Use real MySQL. Raise `max_connections` if needed.
* Race 1, request mode last room: capacity 1, 20 distinct requests for the same night. Expect exactly 1 confirmed, 19 rejected, `booked == 1`, `available == 0`.
* Race 2, notification mode last room: capacity 1, 20 distinct reservations for the same night. Expect all 20 recorded, `booked == 20`, `available == -19`, exactly one oversold alert for the range, pushed value clamped to 0.
* Race 3, duplicate delivery: the same message (same version) delivered 20 times. Expect exactly one `reservations` row, `booked` equal to the nights of one reservation, 19 `Duplicate` results.
* Race 4, modify (version 2) and cancel (version 3) of one reservation concurrently. Expect the final state to equal the version 3 outcome with a consistent ledger.
* Race 5, overlapping multi night ranges in opposite date orders. Expect no unhandled deadlocks (retried) and all invariants intact.
* Invariants after every race: `booked == count(reservation_nights)` per room type and date; request mode never has `available < 0`.
* Negative controls (each enabled by a test only flag, and each must make its race fail):
  * NC1 request mode: replace the guarded `UPDATE` with `SELECT available` then an unconditional `UPDATE`. Race 1 must oversell.
  * NC2 notification mode: skip the reservation row claim and lock. Race 3 must double count `booked`.
  * NC3 read modify write in PHP (load the row, increment, save). Race 2 must lose updates.
* `scripts/race_loop.sh` repeats each race 100 times and records invariant violations (expected 0).

Convergence property test: a random sequence of 200 operations (capacity edits, price edits, bookings, cancels, fault toggles), then clear faults, drain queues, run `verify-sync`. It must exit 0 for all channels.

Integration (`make e2e`): compose up, run a scripted scenario through the real HTTP boundary to the simulator, assert with `verify-sync`.

Static: Pint check, Larastan level 5.

## 11. Local environment

* `composer create-project laravel/laravel core`, `composer require laravel/sail --dev`, `php artisan sail:install --with=mysql`. Create `otasim` the same way with SQLite, no auth, port 8081.
* Compose services: `app`, `mysql`, `worker` (`php artisan queue:work --queue=staybridge_ingest,staybridge_sync,default --tries=6 --max-time=3600`), `scheduler` (`php artisan schedule:work`), `otasim`, shared volume for the CSV folders.
* If Docker is unavailable in the execution environment, fall back to native PHP 8.3 plus a local MySQL 8 and run the worker, scheduler, and both apps as separate processes. Record the fallback in `DECISIONS.md`.
* Makefile targets: `up`, `migrate`, `seed`, `test`, `race`, `e2e`, `verify`, `reset`, `demo`. `make demo` runs the full scenario locally with seeded data and no network dependency.
* `.env.example` documents every variable. Channel base URLs seeded from env.
* Logging: JSON formatter to stderr, with `correlation_id` and `channel` in context.

## 12. AWS deployment (execute only if credentials exist, see section 0)

Region: use the configured default, else `ap-south-1`.

1. Budget: create an AWS Budgets alert at a low threshold before creating resources. Check current free tier and credit terms; do not assume.
2. IAM: EC2 instance profile with only: `sqs:SendMessage`, `sqs:ReceiveMessage`, `sqs:DeleteMessage`, `sqs:ChangeMessageVisibility`, `sqs:GetQueueAttributes`, `sqs:GetQueueUrl` on the two queues; `logs:CreateLogStream`, `logs:PutLogEvents` on the log group; `ssm:GetParameter` on `/staybridge/*`. No static access keys.
3. SQS: standard queues `staybridge_sync` and `staybridge_ingest`, each with a DLQ (`maxReceiveCount` 5). Visibility timeout 180 s (above job timeout 90 s). Laravel ignores `retry_after` for SQS; visibility timeout governs. SQS standard is at least once and unordered, which is why pushes carry absolute state and ingestion is idempotent. Layer roles: job tries and `failed_jobs` handle logical failures; the DLQ catches crashed or timed out consumers.
4. RDS: MySQL 8, `db.t4g.micro`, single AZ, private access only, security group allows 3306 from the EC2 security group only.
5. EC2: t3.small or t4g.small, Docker plus Compose. Security group: 80 and 443 public (443 only if TLS), 22 restricted to the operator IP. Check memory with `docker stats`.
6. Secrets: SSM Parameter Store SecureString for `APP_KEY`, DB password, channel secrets. `scripts/deploy.sh` renders `.env` from SSM.
7. App env: `QUEUE_CONNECTION=sqs`, `SQS_PREFIX=https://sqs.<region>.amazonaws.com/<account>`, `AWS_DEFAULT_REGION`, `CACHE_STORE=database`, `LOG_CHANNEL=stderr`, `APP_DEBUG=false`. Leave `AWS_ACCESS_KEY_ID` unset so the SDK uses the instance role.
8. Logs: Docker `awslogs` driver to a pre created CloudWatch log group. One metric filter on the string `oversold` and one alarm on DLQ depth greater than 0.
9. Simulator on the same instance, internal port only.
10. `scripts/deploy.sh`: `git pull`, `docker compose build`, `docker compose up -d`, `php artisan migrate --force`, `php artisan config:cache`. `scripts/teardown.sh` removes EC2, RDS, queues, and log group.
11. TLS optional.

## 13. CI (GitHub Actions)

```yaml
name: ci
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env: { MYSQL_ROOT_PASSWORD: root, MYSQL_DATABASE: staybridge_test }
        ports: ['3306:3306']
        options: >-
          --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=5
    defaults:
      run:
        working-directory: core
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3', coverage: none }
      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.ci .env && php artisan key:generate
      - run: vendor/bin/pint --test && vendor/bin/phpstan analyse
      - run: php artisan test
```

Concurrency tests run in CI against the MySQL service. Pin action versions to current majors (verify). `.env.ci` uses `DB_HOST=127.0.0.1`.

## 14. Build order and acceptance gates

Run phases in order. Commit and update `PROGRESS.md` after each.

Phase A, foundation. Create repo, `core` and `otasim`, compose, Makefile, `.gitattributes`, CI file, `.env.example`. Gate: `make up` runs, a trivial test passes.

Phase B, domain and extranet. All migrations, models, seeders (section 1 data), `extend-inventory`, mappings, `InventoryService`, `RateService`, auth, calendar view, inline and bulk edit, oversold view skeleton. Gate: editing capacity in the UI bumps `version`, unit and feature tests green.

Phase C, simulator and sync. `otasim` Raahi and Sarai, control panel, fault modes, `/state`. Adapters, factory, `SyncPlanner`, `SyncChannelJob`, circuit breaker, quarantine, `sync_logs`, sync logs UI, channels page, sync badges, `verify-sync`. Gate: a UI edit converges to both simulated channels and `verify-sync` exits 0; fault injection followed by recovery is visible in logs; sync tests green.

Phase D, reservations. Webhook auth, `inbound_events`, ingest job, `ReservationIngestService`, Sarai polling, request mode, alerts, reservations UI, reconcile, Mela CSV adapter. Gate: ingestion feature tests green.

Phase E, concurrency. Races 1 to 5, negative controls, convergence property test, `race_loop.sh`. Gate: all races pass 100 iterations with 0 violations; each negative control fails as designed.

Phase F, polish and docs. README (section 17), Mermaid diagram, `make demo`, `make e2e`. Gate: `make up && make seed && make e2e` passes from a clean clone.

Phase G, AWS (conditional per section 0 item 8). Gate: app reachable and logs visible in CloudWatch, or marked not executed with scripts and docs delivered.

Phase H, optional comparison page (section 16), only if all earlier gates passed.

## 15. Priority order if time or budget runs short

Cut in this order, first item first:
1. Comparison page (Phase H).
2. TLS, CD automation, CloudWatch alarms (keep logs).
3. Mela CSV adapter.
4. OAuth2 token flow on Sarai (use a static key).
5. Race 4 and Race 5 (keep 1 to 3 and their negative controls).
6. Reconcile UI (keep the command).
7. SQS (use the `database` queue on EC2, still use RDS).

Never cut: calendar and edit flow, Raahi and Sarai adapters, level triggered sync with `verify-sync`, ingestion with ledger and locking, races 1 to 3 with negative controls, sync log UI, circuit breaker and quarantine, README with honest limitations, `make demo`.

## 16. Optional comparison page

Public `/compare/{hotel}?checkin=&checkout=&adults=`. Per channel total price is the sum of nightly `rate_prices.price` with channel markup, shown only when `min(available) > 0` across the nights. One indexed query on `(rate_plan_id, stay_date)`, cached 60 seconds with `Cache::remember`. Include `EXPLAIN` output in the README. State that real comparison sites ingest partner feeds instead of reading one central database.

## 17. README requirements

Sections: what and why; scope and honest limitations; Mermaid architecture diagram; decisions D1 to D13 with one line reasons; data model; sync design and why level triggered; overbooking modes with measured race results and negative control evidence; failure handling matrix (transient, permanent, circuit, DLQ, `failed_jobs`); how to run (`make up`); how to test; AWS architecture and teardown note; what real OTA integrations add; roadmap.

Real integration gaps to list: OTA certification and onboarding, full OpenTravel schemas, per OTA codes and quirks, restrictions (minimum stay, closed to arrival, closed to departure), per person and child rates, taxes (GST) and fees, multi currency, PMS integration, stricter rate limits and SLAs, partial success handling, credential rotation policies, audit and compliance.

## 18. Definition of done

* `make up && make seed && make e2e` passes from a clean clone.
* `php artisan test` green locally and in CI, including concurrency tests.
* `staybridge:verify-sync` exits 0 after the convergence property test.
* README complete with measured race results and negative control evidence.
* `PROGRESS.md` shows every phase status. `BLOCKERS.md` lists anything stubbed. `DECISIONS.md` lists every assumption.
* AWS stage executed, or marked not executed with scripts and docs.
