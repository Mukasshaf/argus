# Argus Hotel Channel Manager

Argus is an Indian hospitality distribution engine and channel manager paired with an independent OTA simulator (`otasim`). It synchronizes room availability and rate plans across multiple online travel agencies, ingests reservations in real time, and prevents overbooking under concurrent traffic.

---

## At a Glance

| Service | URL | Default Credentials | Description |
|---|---|---|---|
| **Central Extranet** | [http://localhost:8000](http://localhost:8000) | `sunita.rao@lotusresidency.in` / `secret`<br>`admin@argus.local` / `secret` | Hotel operations dashboard, rates, channels, alerts |
| **OTA Simulator** | [http://localhost:8081](http://localhost:8081) | *(Public webhooks & controls)* | Mock OTA endpoints for Raahi, Sarai, and Mela |
| **Rate Comparison** | [http://localhost:8000/compare/LOTUS01](http://localhost:8000/compare/LOTUS01) | *(No login required)* | Live public parity check across channels (supports `LOTUS01`, `MYSORE01`, `MALABAR01`) |

---

## Quick Start

### Docker Setup

```bash
# 1. Clone & enter repository
git clone <repo-url> && cd Argus

# 2. Launch all services (core app, otasim, mysql, worker, scheduler)
docker compose up -d

# 3. Seed initial hotel data into MySQL
docker compose exec app php artisan db:seed
```

> **Note:** Database migrations run automatically on startup. Seeding populates hotel properties (`LOTUS01`, `MYSORE01`, `MALABAR01`), room types, rate plans, channel mappings, and staff logins.

### Local PHP Setup (Without Docker)

```bash
# Terminal 1: Core Extranet & API
cd core
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000

# Terminal 2: Queue Worker & Scheduler
cd core
php artisan queue:work --queue=argus_ingest,argus_sync,default &
php artisan schedule:work

# Terminal 3: OTA Simulator
cd otasim
touch database/otasim.sqlite
php artisan migrate --seed
php artisan serve --port=8081
```

---

## Key Features

### 1. Central Extranet Operations
* **Multi-Property Switcher:** Manage multiple hotel properties (Lotus Residency, Mysore Palace View, Malabar Heritage Resort) from a single unified console.
* **Interactive Calendar:** View daily capacity, booked rooms, remaining availability, base prices, and stop-sell flags across a 30-to-60 day window.
* **Inline Edits:** Update room counts, rate plan prices, and stop-sell toggles inline with immediate level-triggered sync dispatch.

### 2. Multi-Channel Distribution
* **Outbound Synchronization:** Pushes absolute availability and rate updates to all connected OTAs automatically whenever inventory or prices change.
* **Channel Markup Engine:** Configurable basis points markup per channel (e.g. +10% on Raahi, +12% on Sarai, +8% on Mela) computed dynamically over base rates.
* **Sync Health Monitoring:** Inspect outbound delivery logs, response payloads, execution timestamps, and retry attempts per channel.

### 3. Dual Inbound Reservation Processing
* **Real-time Request Mode:** Synchronous HTTP booking checks for partners that verify availability before charging guests. Returns `201 Created` if inventory is open, or `409 Conflict` if sold out.
* **Notification Webhook Ingestion:** Ingests signed commit webhooks (`OTA_HotelResNotifRQ`) with HMAC-SHA256 signature verification and idempotent deduplication.

### 4. Operational Alerting & Quarantine
* **Automatic Error Separation:** Transient network failures (timeouts, 5xx) automatically retry with backoff; permanent errors (validation failures, bad codes) quarantine the affected row without halting other sync jobs.
* **Deficit & Oversold Alerts:** If external commitments arrive after rooms are booked, Argus records the reservation, raises an operator alert, and clamps outbound pushes to 0 to stop further bookings.

### 5. Multi-Protocol OTA Simulator (`otasim`)
* **Three Mock OTA Endpoints:**
  * **Raahi Stays:** XML over HTTP listener with signed webhook trigger.
  * **Sarai Direct:** JSON REST API with real-time booking requests and change polling.
  * **Mela Bulk:** CSV file-drop batch exports and imports via shared volume.
* **Fault Injection:** Toggle artificial latency (ms) or simulate errors (HTTP 500, 429 rate limit, 400 validation reject, 401 expired token) from the simulator control panel.

### 6. Public Rate Comparison Portal
* Public metasearch parity page ([/compare/LOTUS01](http://localhost:8000/compare/LOTUS01)) showing live calculated channel tariffs across room categories.
* Includes check-in/check-out date picker and an instant property switcher to compare rates across all 3 seeded hotels.

---

## Connected Channels

| Channel | Format | Outbound Sync | Inbound Mode | Default Markup |
|---|---|---|---|---|
| **Raahi Stays** | XML / HTTP | `OTA_HotelAvailNotifRQ`<br>`OTA_HotelRateAmountNotifRQ` | Signed webhook (`OTA_HotelResNotifRQ`) with HMAC-SHA256 | +10% (+1000 bps) |
| **Sarai Direct** | JSON REST | `PUT /availability`<br>`PUT /rates` | Synchronous real-time request (`POST /booking-requests`) + poller | +12% (+1200 bps) |
| **Mela Bulk** | CSV File Drops | Batch export (`inventory_*.csv`) | Polled batch import (`reservations_*.csv`) | +8% (+800 bps) |

---

## Architecture Overview

![Argus Architecture Diagram](assets/architectural%20diagram.png)

---

## Development & Testing Commands

```bash
# Run full PHPUnit test suite (Unit, Feature, Concurrency)
docker compose exec app php artisan test

# Run only concurrency race tests
docker compose exec app php artisan test --filter RaceTest

# Verify sync convergence between Core and Simulator
docker compose exec app php artisan argus:verify-sync

# View live container logs
docker compose logs -f
```
