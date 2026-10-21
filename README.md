# StayBridge Hotel Channel Manager (`Argus`)

> **Simulated Indian Hospitality Distribution Engine**  
> StayBridge is a full-featured, production-architected hotel channel manager paired with an OTA simulator (`otasim`). It manages room inventory and rate distribution for the flagship simulated property **Lotus Residency (Bengaluru, India)** across three heterogeneous OTA protocols, ingests reservation events in real-time, and provides mathematical guarantees against race conditions and overbooking.

---

## Table of Contents
1. [What and Why](#1-what-and-why)
2. [Scope & Honest Limitations](#2-scope--honest-limitations)
3. [Architecture & Data Flows (Mermaid)](#3-architecture--data-flows)
4. [Locked Architectural Decisions (D1 – D13)](#4-locked-architectural-decisions-d1--d13)
5. [Data Model](#5-data-model)
6. [Synchronization Engine: Why Level-Triggered](#6-synchronization-engine-why-level-triggered)
7. [Overbooking Modes & Concurrency Race Results](#7-overbooking-modes--concurrency-race-results)
8. [Failure Handling Matrix](#8-failure-handling-matrix)
9. [How to Run (Local Docker & Native)](#9-how-to-run)
10. [Test Suite & Verification Record](#10-test-suite--verification-record)
11. [AWS Cloud Architecture & Teardown](#11-aws-cloud-architecture--teardown)
12. [What Real-World OTA Integrations Add](#12-what-real-world-ota-integrations-add)
13. [Comparison Metasearch Page & EXPLAIN Analysis](#13-comparison-metasearch-page--explain-analysis)
14. [Roadmap](#14-roadmap)

---

## 1. What and Why

Hoteliers traditionally manage availability, pricing, and restrictions across dozens of disparate Online Travel Agencies (OTAs) such as Booking.com, Expedia, and Agoda. Without a centralized channel manager, inventory must be fragmented among channels or updated manually, leading to catastrophic overselling during booking spikes or stale pricing.

StayBridge provides:
- **Centralized Master Inventory & Pricing:** A single extranet console for real-time capacity and rate management with 10-day sliding window calendar views and bulk editing.
- **Heterogeneous Multi-Protocol Channel Adapters:**
  - **Raahi Stays:** XML over HTTP (`OTA_HotelAvailNotifRQ`, `OTA_HotelRateAmountNotifRQ`) with signed webhook notifications.
  - **Sarai Direct:** JSON REST API with OAuth2 client-credential token caching, polling, and synchronous real-time booking request mode.
  - **Mela Bulk:** CSV file exchange dropping inventory snapshots to SFTP volumes and ingesting reservation batches.
- **Mathematical Concurrency Safety:** Row-level ordering to eliminate deadlocks, atomic guarded conditional decrements in request mode, and snapshot ledger reconciliation for notification mode.

---

## 2. Scope & Honest Limitations

**StayBridge is a simulation engine.** While its architecture mirrors enterprise systems (SiteMinder, Cloudbeds, DerbySoft), the external OTAs are emulated by the companion `otasim` application.

### Honest Limitations:
1. **Simplified Message Formats:** While payload semantics follow OpenTravel Alliance (OTA) standards, schemas are stripped of deep legacy XML baggage (such as nested `<TPA_Extensions>`).
2. **Deterministic Timezones:** Single-property timezone (`Asia/Kolkata`) is enforced. Multi-timezone inventory roll-overs per property are not enabled.
3. **Currency & Tax:** Uses whole INR (`₹`) rupees. GST calculation splits (12% vs 18% slab rules based on declared tariff) are omitted from the core sync payload.
4. **Direct Channel Connections:** Connects directly to `otasim` rather than commercial GDS switches (Amadeus, Sabre).

---

## 3. Architecture & Data Flows

```mermaid
flowchart TD
    subgraph Central_Core["StayBridge Core (Laravel 11 / MySQL 8)"]
        UI["Extranet UI (Blade + jQuery)"]
        DB[(MySQL 8 Master)]
        SyncPlan["SyncPlanner (Dirty Query & Coalescing)"]
        SyncJob["SyncChannelJob (Queue Worker)"]
        IngestJob["IngestInboundEventJob (Queue Worker)"]
        RealtimeSvc["RealtimeBookingService (Guarded Decrement)"]
        IngestSvc["ReservationIngestService (Snapshot Ledger)"]
    end

    subgraph Adapters["Channel Adapters"]
        RaahiAdp["RaahiAdapter (XML / HTTP)"]
        SaraiAdp["SaraiAdapter (JSON REST / OAuth2)"]
        MelaAdp["MelaAdapter (CSV / SFTP)"]
    end

    subgraph OTASim["OTA Simulator (otasim :8081)"]
        RaahiSim["Raahi Stays (XML Listener & Webhook Sender)"]
        SaraiSim["Sarai Direct (OAuth2, JSON API, Polling)"]
        MelaSim["Mela Bulk (SFTP in/out File Drops)"]
        SimCtrl["Simulator Control Panel & Fault Injector"]
    end

    %% Outbound Flow
    UI -->|1. Capacity / Price Edit| DB
    DB -.->|2. Trigger After Commit| SyncJob
    SyncJob --> SyncPlan
    SyncPlan -->|3. Read Dirty Rows| DB
    SyncJob --> RaahiAdp & SaraiAdp & MelaAdp
    RaahiAdp -->|XML POST| RaahiSim
    SaraiAdp -->|JSON PUT| SaraiSim
    MelaAdp -->|CSV File Drop| MelaSim

    %% Inbound Notification Flow
    RaahiSim -->|Signed Webhook HMAC-SHA256| IngestJob
    SaraiSim -.->|Polled Cursor| IngestJob
    MelaSim -.->|CSV Ingest| IngestJob
    IngestJob --> IngestSvc
    IngestSvc -->|Lock Ascending & Apply| DB

    %% Inbound Request Mode Flow
    SaraiSim -->|POST /booking-requests (Realtime)| RealtimeSvc
    RealtimeSvc -->|Atomic Conditional UPDATE| DB
    RealtimeSvc -->|201 Confirmed / 409 Sold Out| SaraiSim
```

---

## 4. Locked Architectural Decisions (D1 – D13)

| ID | Decision | One-Line Rationale |
|---|---|---|
| **D1** | Central DB is single source of truth | Prevents split-brain state; channels converge via asynchronous level-triggered pushes. |
| **D2** | Inventory per room-type/date; Rates per rate-plan/date | Normalizes availability across plans while allowing rate plans (EP, CP) to diverge. |
| **D3** | `available` stored generated column `capacity - booked` | Signed math prevents unsigned MySQL subtraction underflow during notification overbookings. |
| **D4** | Dual reservation modes (Notification vs Request) | Accommodates OTAs that already charged the guest vs OTAs that check availability before checkout. |
| **D5** | Level-triggered sync with monotonic version tracking | Eliminates lost updates under high concurrency; pushes absolute state seen at read time. |
| **D6** | Inbound idempotency via monotonic external versions | Ensures replay safety, out-of-order rejection, and tombstone tracking for premature cancellations. |
| **D7** | Error classification & Per-channel Circuit Breaker | Isolates network drops (transient backoff) from bad payloads (quarantine), preventing infinite queue loops. |
| **D8** | Pre-generated 540-day inventory & Ascending row locks | Eliminates runtime row creation races and avoids deadlocks during multi-night reservations. |
| **D9** | Whole INR currency & Basis points markup | Avoids IEEE 754 floating-point rounding errors across rates and commission calculations. |
| **D10** | Blade + jQuery 3 + Plain CSS Extranet | Eliminates heavy frontend framework dependencies while delivering high-speed operational views. |
| **D11** | MySQL 8 InnoDB across all environments | Guarantees identical row locking (`FOR UPDATE`), deadlock retry, and stored column semantics. |
| **D12** | Database queue/cache locally; SQS/RDS on AWS | Minimizes local dev friction while enabling elastic enterprise scale in cloud deployments. |
| **D13** | No Eloquent model events dispatching external jobs | Keeps inventory writes deterministic, audited, and strictly confined to domain service classes. |

---

## 5. Data Model

The schema is built around explicit constraints:
- `room_inventory`: `(room_type_id, stay_date)` UNIQUE. Contains `capacity` (signed smallint), `booked` (signed smallint), and `available` (`storedAs('capacity - booked')`).
- `rate_prices`: `(rate_plan_id, stay_date)` UNIQUE. Holds nightly base prices in whole INR rupees.
- `channels`: Protocol (`xml`, `json`, `csv`), encrypted credentials, webhook secrets, basis point markup, circuit status (`active`, `degraded`, `paused`).
- `channel_sync_state`: `(channel_id, kind, subject_id, stay_date)` UNIQUE. Tracks `pushed_version`, `attempts`, `blocked_at`, and `last_error`. Starts at `-1` to ensure initial full sync.
- `reservations`: `(channel_id, external_id)` UNIQUE. Tracks `external_version`, guest name, check-in, check-out, and status (`confirmed`, `cancelled`).
- `reservation_nights`: `(reservation_id, stay_date)` UNIQUE. The physical ledger holding active nights; powers night diffing and drift reconciliation.
- `inbound_events`: `(channel_id, external_event_id)` UNIQUE. Stores immutable raw incoming payloads for audit and manual replay.
- `sync_logs`: High-resolution audit table tracking correlation ID, channel, direction, payload previews (redacted credentials/guest names), HTTP status, duration ms, and outcome.
- `alerts`: Tracks operational exceptions (`oversold`, `channel_blocked`, `unmapped_code`, `drift`).

---

## 6. Synchronization Engine: Why Level-Triggered

Edge-triggered systems emit delta events (e.g., `RoomBooked: -1 room`). Under network drops or out-of-order queue delivery, edge-triggered systems drift permanently.

StayBridge employs **Level-Triggered Synchronization**:
1. Any capacity change, rate change, or reservation update increments the database row's `version`.
2. `SyncPlanner` queries dirty rows where `ri.version > s.pushed_version AND s.blocked_at IS NULL`.
3. The engine coalesces contiguous dates with equal values into ranges (e.g. `2026-12-01 to 2026-12-10: 4 rooms`).
4. Values pushed are **absolute values** clamped to `max(0, available)` (or `0` when `stop_sell = 1`).
5. Upon successful transmission, `pushed_version` is advanced using a targeted SQL `CASE` statement writing **only the version seen at read time**:
```sql
UPDATE channel_sync_state
SET pushed_version = CASE id WHEN 101 THEN 5 WHEN 102 THEN 5 END
WHERE id IN (101, 102) AND pushed_version < CASE id WHEN 101 THEN 5 WHEN 102 THEN 5 END;
```
If an extranet operator or booking modifies inventory while the push is in flight, the central version is already incremented to `6`. The update does not advance past `5`, leaving the row dirty for immediate re-sync on the next cycle.

---

## 7. Overbooking Modes & Concurrency Race Results

StayBridge accommodates two fundamental OTA booking paradigms:

### A. Request Mode (Sarai Real-Time Booking)
The OTA queries StayBridge synchronously before taking guest payment.
- **Mechanism:** Atomic guarded conditional SQL decrement:
  ```sql
  UPDATE room_inventory
  SET booked = booked + 1, version = version + 1, updated_at = NOW()
  WHERE room_type_id = ? AND stay_date IN (?)
    AND stop_sell = 0 AND capacity - booked >= 1;
  ```
- **Guarantee:** If rows affected !== nights count, transaction aborts and returns `HTTP 409 {"status":"rejected","reason":"sold_out"}`. Never oversells.

### B. Notification Mode (Raahi Webhook & Mela CSV)
The OTA has already charged the guest and unilaterally notifies the hotel.
- **Mechanism:** Strict ascending row locks `(room_type_id, stay_date)`, row claim placeholder (`external_version = 0`), reservation locking, and night ledger diffing.
- **Guarantee:** Booking is always recorded. If `available < 0`, StayBridge raises an `oversold` alert, surfaces the night on the **D4 Oversold Deficit View**, and clamps outbound pushes to `0` across other OTAs.

### Concurrency Stress Test Results (`tests/Concurrency/RaceTest.php`)
Simulated with $N = 20$ parallel child worker processes synchronized at a microsecond start barrier:

| Concurrency Scenario | Injected Load | Expected Outcome | Measured Invariants |
|---|---|---|---|
| **Race 1: Request Mode Last Room** | 20 concurrent requests for 1 available room | Exactly 1 confirmed (201), 19 rejected (409) | `booked == 1`, `available == 0`, 0 oversells |
| **Race 2: Notification Mode Last Room** | 20 concurrent OTA commitments for 1 room | All 20 ingested, `booked == 20`, `available == -19` | Exactly 1 `oversold` alert, pushed clamped to 0 |
| **Race 3: Duplicate Delivery** | 20 identical webhook deliveries (same ID & version) | Exactly 1 applied, 19 detected as `Duplicate` | `reservations` count == 1, `booked == 1` |
| **Race 4: Concurrent Modify & Cancel** | Version 2 (extend) vs Version 3 (cancel) | Final state equals Version 3 tombstone | Nights held == 0, `booked == 0` |
| **Race 5: Multi-Night Overlap** | 10 workers booking overlapping ranges in opposite date orders | All succeed without unhandled deadlocks | `booked == count(reservation_nights)` on all dates |

### Negative Control Evidence (Failure Validation)
Each negative control intentionally disables a concurrency protection to prove the test suite catches real race conditions:
- **NC1 (Unguarded Update in Request Mode):** Replaces atomic update with `SELECT available` then unconditional `UPDATE`. **Result:** Race 1 oversells (`booked > 1`, `available < 0`), failing the invariant.
- **NC2 (Skipped Row Claim & Lock in Ingestion):** Skips version-0 claim lock. **Result:** Race 3 double-counts bookings under concurrent duplicate arrivals.
- **NC3 (PHP In-Memory Read-Modify-Write):** Loads Eloquent model, increments in PHP, saves. **Result:** Concurrent updates overwrite in-memory state; Race 2 loses updates (`booked < 20`).

---

## 8. Failure Handling Matrix

| Error Classification | Trigger Conditions | Engine Behavior | Recovery Mechanism |
|---|---|---|---|
| **Transient Error** | HTTP 5xx, 429 Too Many Requests, Connection Timeouts, Network Drops | Throws `TransientChannelException`. Queue worker releases job with exponential backoff & jitter (or `Retry-After`). | Automatic queue retry. Channel failure count increments. |
| **Permanent Error** | HTTP 4xx (except 429), XML `<Errors>` validation, Unmapped room/rate codes | Throws `PermanentChannelException`. Quarantines state row (`blocked_at = NOW()`), logs error, raises `channel_blocked` alert. | Never auto-retried. Operator resolves mapping / payload error in Extranet and clicks "Unblock". |
| **Circuit Breaker** | 5 consecutive transient failures on a single channel | Channel marked `degraded`. Circuit opens for 120 seconds. Sync jobs bail early. | Enters half-open state after 120s. A single successful probe restores channel to `active`. |
| **Deadlock Handling** | Database lock contention under concurrent multi-night bookings | Caught by `DB::transaction(..., 5)`. | PDO retries transaction up to 5 times with random backoff. |
| **Failed Job Escalation** | 6 exhausted job attempts | Job sent to `failed_jobs` table; error logged with correlation ID and channel context. | Surfaced in Sync Logs UI for manual retry or alert triage. |

---

## 9. How to Run

### Option A: Docker Compose (Recommended)
```bash
# 1. Clone and enter repo
git clone <repo-url> && cd Argus

# 2. Start services (core, otasim, mysql, queue worker, scheduler)
make up

# 3. Run database migrations and Indian seed data
make migrate
make seed

# 4. Access applications:
#    - Central Extranet: http://localhost:8000 (User: sunita@lotusresidency.in / Password: password)
#    - OTA Simulator:    http://localhost:8081
#    - Public Compare:   http://localhost:8000/compare/LOTUS01
```

### Option B: Native Host Environment
If Docker is not running:
```bash
# Core App
cd core
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000 &
php artisan queue:work --queue=staybridge_ingest,staybridge_sync,default &
php artisan schedule:work &

# OTA Simulator
cd ../otasim
php artisan migrate --seed
php artisan serve --port=8081 &
```

---

## 10. Test Suite & Verification Record

Run all tests via Makefile:
```bash
make test         # Runs Unit, Feature, and Concurrency suites
make race         # Runs 100-iteration stress loop via scripts/race_loop.sh
make verify       # Runs staybridge:verify-sync convergence assertion
make demo         # Executes full end-to-end operational demo script
```

### CI / CD Pipeline
The GitHub Actions pipeline (`.github/workflows/ci.yml`) runs on every commit:
1. Boots real MySQL 8.0 container service.
2. Runs Laravel Pint style enforcement.
3. Executes PHPStan / Larastan static analysis (Level 5).
4. Executes the full PHPUnit suite including `tests/Concurrency/RaceTest.php`.

---

## 11. AWS Cloud Architecture & Teardown

For cloud deployments on AWS:
- **Compute:** EC2 (`t4g.small` ARM64) running Docker Compose with Docker `awslogs` driver sending JSON structured logs to Amazon CloudWatch.
- **Database:** Amazon RDS MySQL 8.0 (`db.t4g.micro`), single-AZ, private subnet, accessible only from the EC2 security group.
- **Queues:** Amazon SQS standard queues (`staybridge_sync`, `staybridge_ingest`) paired with Dead-Letter Queues (DLQ) with `maxReceiveCount = 5` and visibility timeout set to 180 seconds.
- **Secrets:** AWS Systems Manager (SSM) Parameter Store (`/staybridge/*`) storing `APP_KEY`, DB passwords, and webhook secrets.
- **IAM Role:** EC2 instance profile granting least-privilege access (`sqs:*`, `logs:*`, `ssm:GetParameter`). No hardcoded AWS access keys.
- **Deployment & Teardown:**
  - `scripts/deploy.sh`: Fetches SSM parameters, renders production `.env`, runs migrations and container upgrades.
  - `scripts/teardown.sh`: Destroys EC2 instance, RDS database, SQS queues, and CloudWatch log groups to prevent ongoing charges.

---

## 12. What Real-World OTA Integrations Add

StayBridge provides an authentic architecture for multi-protocol distribution. When integrating with live commercial OTAs, the following production requirements must be addressed:
1. **Formal OTA Certification & Conformance Testing:** Booking.com and Expedia require passing comprehensive programmatic test suites (handling invalid credit cards, booking cancellations before arrival, rate plan exclusions) before granting API credentials.
2. **Complete OpenTravel Alliance (OTA) Specifications:** Full adherence to schemas including `<RatePlanInclusions>`, `<TaxPolicies>`, `<CancelPenalties>`, and `<MealPlanCodes>`.
3. **Advanced Channel Restrictions:** Support for Minimum Length of Stay (MLOS), Maximum Length of Stay (MAXLOS), Closed to Arrival (CTA), and Closed to Departure (CTD) per rate plan.
4. **Complex Occupancy & Person-Based Pricing:** Pricing curves for additional adults, children (categorized by age buckets: infant, child, teen), and extra bed supplements.
5. **Indian Taxation (GST) Compliance:** Dynamic computation of Goods & Services Tax based on declared room tariff (e.g., 12% for rooms $\le ₹7,500$; 18% for rooms $> ₹7,500$) with HSN/SAC code reporting.
6. **Property Management System (PMS) Bi-Directional Sync:** Ingesting walk-in bookings and telephone reservations from on-premise PMS systems (Opera, IDS Next, eZee) with sub-second inventory sync.
7. **Credit Card Tokenization & PCI-DSS:** Integrating PCI-compliant card tokenization proxies (e.g., PCI Booking, TokenEx) for secure transmission of guest payment details without storing raw PAN in application databases.

---

## 13. Comparison Metasearch Page & EXPLAIN Analysis

StayBridge includes a public rate comparison page at `/compare/{hotel}` allowing travelers to view real-time rates across connected channels.

### Query Optimization
The comparison engine queries nightly rate prices using the composite primary unique key `(rate_plan_id, stay_date)`:
```sql
SELECT * FROM rate_prices 
WHERE rate_plan_id = 1 AND stay_date IN ('2026-12-01', '2026-12-02');
```

### MySQL `EXPLAIN` Plan
```
+----+-------------+-------------+------------+-------+------------------------------------------+------------------------------------------+---------+------+------+----------+-----------------------+
| id | select_type | table       | partitions | type  | possible_keys                            | key                                      | key_len | ref  | rows | filtered | Extra                 |
+----+-------------+-------------+------------+-------+------------------------------------------+------------------------------------------+---------+------+------+----------+-----------------------+
|  1 | SIMPLE      | rate_prices | NULL       | range | rate_prices_rate_plan_id_stay_date_unique| rate_prices_rate_plan_id_stay_date_unique| 11      | NULL |    2 |   100.00 | Using index condition |
+----+-------------+-------------+------------+-------+------------------------------------------+------------------------------------------+---------+------+------+----------+-----------------------+
```
- **Type:** `range` (Optimal index seek across specified stay dates)
- **Key Used:** `rate_prices_rate_plan_id_stay_date_unique`
- **Rows Examined:** Exactly equal to the number of stay dates requested.
- **Caching:** Output is cached in Redis/database cache for 60 seconds via `Cache::remember`.

---

## 14. Roadmap

- [x] **Phase A:** Multi-app repository layout, Docker Compose, CI pipeline, and environment configuration.
- [x] **Phase B:** Domain models, signed generated inventory math, rate service, and 5-page Stitch Extranet Blade views.
- [x] **Phase C:** OTA Simulator (`otasim`), multi-protocol adapters (Raahi XML, Sarai JSON, Mela CSV), level-triggered sync planner, and circuit breaker.
- [x] **Phase D:** Dual reservation ingestion modes, HMAC webhook security, night ledger diffing, and automated drift reconciliation.
- [x] **Phase E:** Concurrency race test suite ($N=20$), negative controls (NC1–NC3), and convergence verification.
- [x] **Phase F:** Comprehensive documentation, Mermaid architectural models, and public rate comparison view.
- [ ] **Phase G:** Automated AWS cloud infrastructure provisioning (Terraform / CloudFormation).
