# Architecture & Implementation Decisions

## Locked Spec Decisions (D1 - D13)
* **D1**: Central database is the single source of truth. Channels are replicas that converge via a push model.
* **D2**: Inventory is stored per room type per date (`room_inventory`). Rate is stored per rate plan per date (`rate_prices`). Unique keys: `(room_type_id, stay_date)` and `(rate_plan_id, stay_date)`.
* **D3**: `room_inventory` capacity is staff-editable; booked is updated only by reservation logic. `available` is a signed STORED GENERATED column `capacity - booked`. Negative available indicates oversold. Pushes clamp to `max(0, available)` and 0 when `stop_sell = 1`.
* **D4**: Two reservation modes:
  - Notification mode (OTA already committed booking): Always record snapshot, `available` can drop below 0, triggers oversold alert. Alerts auto-resolve once `available >= 0`.
  - Request mode (Sarai real-time): Atomic conditional decrement `capacity - booked >= 1 AND stop_sell = 0`, rejects with 409 `sold_out` when unavailable.
* **D5**: Level-triggered synchronization. Any change to capacity, booked, or price increments `version`. Pushes send absolute values read at execution time. `channel_sync_state.pushed_version` tracks what each channel has received and is advanced only to the version seen at read time.
* **D6**: Inbound idempotency: `inbound_events` unique on `(channel_id, external_event_id)`; `reservations` unique on `(channel_id, external_id)` with monotonic `external_version`. Out-of-order versions are rejected/ignored. Unknown cancellation creates a tombstone. Unknown modification performs an upsert.
* **D7**: Error classification:
  - `TransientChannelException` (timeouts, network drops, 429, 5xx): retried by queue worker with backoff and jitter.
  - `PermanentChannelException` (4xx client errors, validation `<Errors>`, unmapped codes): quarantined in `channel_sync_state` (`blocked_at != null`) and alerts raised; never automatically retried.
  - Circuit breaker: opens after 5 consecutive failures, enters half-open test state after 2 minutes.
* **D8**: Inventory pre-generated 540 days ahead. Row-lock acquisition order is strictly ascending by `room_type_id`, then `stay_date` to prevent deadlocks.
* **D9**: Currency is whole INR rupees (unsigned integer). Channel markup in basis points (`intdiv(price * (10000 + markup_bps) + 5000, 10000)`). Timestamps stored in UTC. `stay_date` is DATE in hotel local timezone (`Asia/Kolkata`). Nights are half-open interval `[check_in, check_out)`.
* **D10**: Frontend: Clean Blade templates with jQuery 3 and Tailwind CSS matching the 5 Stitch HTML designs.
* **D11**: MySQL 8 InnoDB in production, Docker, and CI. Native support for stored generated columns, row locking (`FOR UPDATE`), and deadlock retries.
* **D12**: Queue connection `database` locally (`staybridge_ingest`, `staybridge_sync`, `default`), `sqs` on AWS. Cache store `database`.
* **D13**: No Eloquent model events dispatch jobs or invoke remote APIs. Inventory rows are updated strictly via `InventoryService`, `ReservationIngestService`, and `RealtimeBookingService`. `markPushed` writes only `channel_sync_state`.

## Environmental & Engineering Decisions
* **DEC-101**: Unified Docker Compose setup defines `app` (`core`), `otasim`, `mysql`, `worker`, and `scheduler` with shared SFTP volumes (`sftp_in`, `sftp_out`) for Mela CSV file drop integration.
* **DEC-102**: Stitch design integration retains the exact typography (`Lora` / `Noto Serif` + `Inter`), palette (Deep Ink, Sage, Terracotta, Warm Limestone), and component hierarchy across all 5 pages (`calendar`, `reservations`, `channels`, `sync-logs`, `alerts`).
* **DEC-103**: Test suite architecture provides standard PHPUnit tests (`tests/Unit`, `tests/Feature`), negative control tests (`tests/Feature/NegativeControlsTest.php`), and real concurrency stress tests (`tests/Concurrency/RaceTest.php`) using `symfony/process` and barrier synchronization.
