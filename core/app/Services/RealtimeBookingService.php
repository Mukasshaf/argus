<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Dto\ReservationEvent;
use App\Channels\Exceptions\SoldOutException;
use App\Channels\Exceptions\UnmappedCodeException;
use App\Enums\ReservationStatus;
use App\Enums\SyncDirection;
use App\Enums\SyncOutcome;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Throwable;

class RealtimeBookingService
{
    // Test flag for negative control (NC1)
    public static bool $useUnguardedUpdate = false; // NC1: select then unconditional update

    public ?Reservation $replay = null;

    /**
     * @return array{status: int, data: array<string, mixed>}
     */
    public function book(ReservationEvent $evt): array
    {
        $startTime = hrtime(true);
        $correlationId = (string) \Illuminate\Support\Str::uuid();
        $this->replay = null;

        $dates = Nights::between($evt->checkIn, $evt->checkOut);
        if (empty($dates)) {
            return [
                'status' => 400,
                'data' => ['status' => 'rejected', 'reason' => 'invalid_dates'],
            ];
        }

        $room = ChannelRoomMapping::resolve($evt->channelId, $evt->roomCode);
        $rate = ChannelRateMapping::resolve($evt->channelId, $evt->rateCode);

        if (!$room || !$rate) {
            throw new UnmappedCodeException($evt, "Unmapped room or rate code in real-time booking.");
        }

        $roomTypeId = $room->room_type_id;
        $ratePlanId = $rate->rate_plan_id;
        $hotel = Hotel::first();
        $hotelId = $hotel ? $hotel->id : 1;

        try {
            DB::transaction(function () use ($roomTypeId, $ratePlanId, $hotelId, $dates, $evt) {
                // Idempotent retry: if reservation already exists, replay 201
                $existing = Reservation::where('channel_id', $evt->channelId)
                    ->where('external_id', $evt->externalId)
                    ->first();

                if ($existing) {
                    $this->replay = $existing;
                    return;
                }

                if (self::$useUnguardedUpdate) {
                    // NC1: check available then unconditional update (causes race condition)
                    $rows = DB::table('room_inventory')
                        ->where('room_type_id', $roomTypeId)
                        ->whereIn('stay_date', $dates)
                        ->get();

                    foreach ($rows as $r) {
                        if (($r->capacity - $r->booked) < 1 || $r->stop_sell) {
                            throw new SoldOutException();
                        }
                    }

                    // Artificial pause in NC1 to widen race window
                    usleep(10000);

                    DB::table('room_inventory')
                        ->where('room_type_id', $roomTypeId)
                        ->whereIn('stay_date', $dates)
                        ->update([
                            'booked' => DB::raw('booked + 1'),
                            'version' => DB::raw('version + 1'),
                            'updated_at' => now(),
                        ]);
                } else {
                    // Normal D4 atomic guarded decrement
                    $placeholders = implode(',', array_fill(0, count($dates), '?'));
                    $n = DB::update(
                        "UPDATE room_inventory
                         SET booked = booked + 1, version = version + 1, updated_at = NOW()
                         WHERE room_type_id = ?
                           AND stay_date IN ({$placeholders})
                           AND stop_sell = 0
                           AND capacity - booked >= 1",
                        [$roomTypeId, ...$dates]
                    );

                    if ($n !== count($dates)) {
                        throw new SoldOutException();
                    }
                }

                // Insert reservation and held nights
                $res = Reservation::create([
                    'hotel_id' => $hotelId,
                    'room_type_id' => $roomTypeId,
                    'rate_plan_id' => $ratePlanId,
                    'channel_id' => $evt->channelId,
                    'external_id' => $evt->externalId,
                    'external_version' => $evt->externalVersion ?: 1,
                    'status' => ReservationStatus::Confirmed,
                    'check_in' => $evt->checkIn,
                    'check_out' => $evt->checkOut,
                    'guest_name' => $evt->guestName,
                    'adults' => $evt->adults,
                    'total_price' => $evt->totalPrice,
                    'currency' => $evt->currency,
                    'received_at' => now(),
                ]);

                $nightInserts = [];
                foreach ($dates as $d) {
                    $nightInserts[] = [
                        'reservation_id' => $res->id,
                        'room_type_id' => $roomTypeId,
                        'stay_date' => $d,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('reservation_nights')->insert($nightInserts);
            }, 5);

            // Outside transaction: fanout sync
            SyncKick::allChannels();

            $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);

            SyncLog::create([
                'correlation_id' => $correlationId,
                'channel_id' => $evt->channelId,
                'direction' => SyncDirection::In,
                'operation' => 'realtimeBooking',
                'request_body' => json_encode($evt),
                'response_status' => 201,
                'response_body' => json_encode(['status' => 'confirmed']),
                'duration_ms' => $durationMs,
                'attempt' => 1,
                'outcome' => SyncOutcome::Ok,
                'error' => null,
                'created_at' => now(),
            ]);

            return [
                'status' => 201,
                'data' => ['status' => 'confirmed'],
            ];
        } catch (SoldOutException $e) {
            $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);

            SyncLog::create([
                'correlation_id' => $correlationId,
                'channel_id' => $evt->channelId,
                'direction' => SyncDirection::In,
                'operation' => 'realtimeBooking',
                'request_body' => json_encode($evt),
                'response_status' => 409,
                'response_body' => json_encode(['status' => 'rejected', 'reason' => 'sold_out']),
                'duration_ms' => $durationMs,
                'attempt' => 1,
                'outcome' => SyncOutcome::Ok, // Business rejection is valid outcome
                'error' => 'Sold out',
                'created_at' => now(),
            ]);

            return [
                'status' => 409,
                'data' => ['status' => 'rejected', 'reason' => 'sold_out'],
            ];
        }
    }
}
